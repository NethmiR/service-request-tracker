<?php

// Computes the aggregate overview figures: the status counts, the monthly created and resolved figures, the tag counts and the staff workload.
// Every boundary is bound as a parameter, and the number of queries never grows with the number of requests or users.
// The current assignee is the assignee history record with the highest id per request, the same rule latestOfMany applies on the model.

namespace App\Services\Overview;

use App\Data\Overview\MonthlyTrend;
use App\Data\Overview\ReportingMonth;
use App\Data\Overview\StaffWorkload;
use App\Data\Overview\StatusCount;
use App\Data\Overview\TagCount;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestService;
use App\Support\Database\DurationExpression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ServiceRequestStatistics
{
    private const string REQUESTS_TABLE = 'service_requests';

    private const string STATUS_HISTORIES_TABLE = 'service_request_status_histories';

    private const string ASSIGNEE_HISTORIES_TABLE = 'service_request_assignee_histories';

    private const string REQUEST_TAG_TABLE = 'service_request_tag';

    private const string TAGS_TABLE = 'tags';

    private const string ID_COLUMN = 'id';

    private const string STATUS_COLUMN = 'status';

    private const string CREATED_AT_COLUMN = 'created_at';

    private const string SERVICE_REQUEST_ID_COLUMN = 'service_request_id';

    private const string TO_STATUS_COLUMN = 'to_status';

    private const string ASSIGNED_TO_USER_ID_COLUMN = 'assigned_to_user_id';

    private const string TAG_ID_COLUMN = 'tag_id';

    private const string TAG_NAME_COLUMN = 'name';

    private const string LATEST_RESOLUTIONS_ALIAS = 'latest_resolutions';

    private const string LATEST_ASSIGNMENTS_ALIAS = 'latest_assignments';

    private const string RESOLVED_AT_ALIAS = 'resolved_at';

    private const string LATEST_ID_ALIAS = 'latest_id';

    private const string REQUEST_COUNT_ALIAS = 'request_count';

    private const string MONTH_COUNT_ALIAS_PREFIX = 'month_';

    private const string MONTH_AVERAGE_ALIAS_PREFIX = 'average_';

    private const string COUNT_TEMPLATE = 'COUNT(*) AS %s';

    private const string MAX_TEMPLATE = 'MAX(%s) AS %s';

    private const string ALIASED_TEMPLATE = '%s AS %s';

    private const string MONTH_COUNT_TEMPLATE = 'SUM(CASE WHEN %1$s >= ? AND %1$s < ? THEN 1 ELSE 0 END)';

    private const string MONTH_AVERAGE_TEMPLATE = 'AVG(CASE WHEN %1$s >= ? AND %1$s < ? THEN %2$s END)';

    private const array COUNTED_STATUSES = [
        ServiceRequestStatus::Open,
        ServiceRequestStatus::Assigned,
        ServiceRequestStatus::InProgress,
    ];

    private const array WORKLOAD_STATUSES = [
        ServiceRequestStatus::Assigned,
        ServiceRequestStatus::InProgress,
    ];

    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
    ) {}

    public function statusCounts(): array
    {
        $counts = DB::table(self::REQUESTS_TABLE)
            ->select(self::STATUS_COLUMN)
            ->selectRaw($this->countExpression())
            ->whereIn(self::STATUS_COLUMN, $this->statusValues(self::COUNTED_STATUSES))
            ->groupBy(self::STATUS_COLUMN)
            ->pluck(self::REQUEST_COUNT_ALIAS, self::STATUS_COLUMN);

        return array_map(
            fn (ServiceRequestStatus $status): StatusCount => new StatusCount($status, (int) $counts->get($status->value, 0)),
            self::COUNTED_STATUSES,
        );
    }

    public function monthlyTrends(array $months): array
    {
        $created = $this->createdPerMonth($months);
        $resolved = $this->resolvedPerMonth($months);

        $trends = [];

        foreach ($months as $index => $month) {
            $average = $resolved[self::MONTH_AVERAGE_ALIAS_PREFIX.$index] ?? null;

            $trends[] = new MonthlyTrend(
                $month->key,
                (int) ($created[self::MONTH_COUNT_ALIAS_PREFIX.$index] ?? 0),
                (int) ($resolved[self::MONTH_COUNT_ALIAS_PREFIX.$index] ?? 0),
                $average === null ? null : (float) $average,
            );
        }

        return $trends;
    }

    public function tagCounts(ReportingMonth $firstMonth, ReportingMonth $lastMonth): array
    {
        $requestCreatedAt = $this->column(self::REQUESTS_TABLE, self::CREATED_AT_COLUMN);

        $counts = DB::table(self::REQUEST_TAG_TABLE)
            ->join(
                self::TAGS_TABLE,
                $this->column(self::TAGS_TABLE, self::ID_COLUMN),
                '=',
                $this->column(self::REQUEST_TAG_TABLE, self::TAG_ID_COLUMN),
            )
            ->join(
                self::REQUESTS_TABLE,
                $this->column(self::REQUESTS_TABLE, self::ID_COLUMN),
                '=',
                $this->column(self::REQUEST_TAG_TABLE, self::SERVICE_REQUEST_ID_COLUMN),
            )
            ->select($this->column(self::TAGS_TABLE, self::TAG_NAME_COLUMN))
            ->selectRaw($this->countExpression())
            ->where($requestCreatedAt, '>=', $firstMonth->startsAt)
            ->where($requestCreatedAt, '<', $lastMonth->endsAt)
            ->groupBy($this->column(self::TAGS_TABLE, self::TAG_NAME_COLUMN))
            ->pluck(self::REQUEST_COUNT_ALIAS, self::TAG_NAME_COLUMN);

        return array_map(
            fn (ServiceRequestTag $tag): TagCount => new TagCount($tag, (int) $counts->get($tag->value, 0)),
            ServiceRequestTag::cases(),
        );
    }

    public function staffWorkload(): array
    {
        $requestStatus = $this->column(self::REQUESTS_TABLE, self::STATUS_COLUMN);
        $assignedTo = $this->column(self::ASSIGNEE_HISTORIES_TABLE, self::ASSIGNED_TO_USER_ID_COLUMN);

        $rows = DB::table(self::ASSIGNEE_HISTORIES_TABLE)
            ->joinSub(
                $this->latestAssignmentsQuery(),
                self::LATEST_ASSIGNMENTS_ALIAS,
                $this->column(self::LATEST_ASSIGNMENTS_ALIAS, self::LATEST_ID_ALIAS),
                '=',
                $this->column(self::ASSIGNEE_HISTORIES_TABLE, self::ID_COLUMN),
            )
            ->join(
                self::REQUESTS_TABLE,
                $this->column(self::REQUESTS_TABLE, self::ID_COLUMN),
                '=',
                $this->column(self::ASSIGNEE_HISTORIES_TABLE, self::SERVICE_REQUEST_ID_COLUMN),
            )
            ->select([$assignedTo, $requestStatus])
            ->selectRaw($this->countExpression())
            ->whereIn($requestStatus, $this->statusValues(self::WORKLOAD_STATUSES))
            ->groupBy($assignedTo, $requestStatus)
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $userId = (int) $row->{self::ASSIGNED_TO_USER_ID_COLUMN};
            $counts[$userId][$row->{self::STATUS_COLUMN}] = (int) $row->{self::REQUEST_COUNT_ALIAS};
        }

        return $this->serviceRequestService->listAssignableUsers()
            ->map(fn (User $user): StaffWorkload => new StaffWorkload(
                $user,
                $counts[$user->id][ServiceRequestStatus::Assigned->value] ?? 0,
                $counts[$user->id][ServiceRequestStatus::InProgress->value] ?? 0,
            ))
            ->all();
    }

    private function createdPerMonth(array $months): array
    {
        $createdAt = $this->column(self::REQUESTS_TABLE, self::CREATED_AT_COLUMN);

        $query = DB::table(self::REQUESTS_TABLE)
            ->where($createdAt, '>=', $months[array_key_first($months)]->startsAt)
            ->where($createdAt, '<', $months[array_key_last($months)]->endsAt);

        $this->selectPerMonth($query, $months, $createdAt, self::MONTH_COUNT_TEMPLATE, self::MONTH_COUNT_ALIAS_PREFIX);

        return (array) ($query->first() ?? []);
    }

    private function resolvedPerMonth(array $months): array
    {
        $resolvedAt = $this->column(self::LATEST_RESOLUTIONS_ALIAS, self::RESOLVED_AT_ALIAS);
        $resolutionSeconds = DurationExpression::secondsBetween(
            $this->column(self::REQUESTS_TABLE, self::CREATED_AT_COLUMN),
            $resolvedAt,
        );

        $query = DB::table(self::REQUESTS_TABLE)
            ->joinSub(
                $this->latestResolutionsQuery(),
                self::LATEST_RESOLUTIONS_ALIAS,
                $this->column(self::LATEST_RESOLUTIONS_ALIAS, self::SERVICE_REQUEST_ID_COLUMN),
                '=',
                $this->column(self::REQUESTS_TABLE, self::ID_COLUMN),
            )
            ->where($this->column(self::REQUESTS_TABLE, self::STATUS_COLUMN), ServiceRequestStatus::Resolved->value)
            ->where($resolvedAt, '>=', $months[array_key_first($months)]->startsAt)
            ->where($resolvedAt, '<', $months[array_key_last($months)]->endsAt);

        $this->selectPerMonth($query, $months, $resolvedAt, self::MONTH_COUNT_TEMPLATE, self::MONTH_COUNT_ALIAS_PREFIX);
        $this->selectPerMonth(
            $query,
            $months,
            $resolvedAt,
            self::MONTH_AVERAGE_TEMPLATE,
            self::MONTH_AVERAGE_ALIAS_PREFIX,
            $resolutionSeconds,
        );

        return (array) ($query->first() ?? []);
    }

    private function latestResolutionsQuery(): Builder
    {
        return DB::table(self::STATUS_HISTORIES_TABLE)
            ->select(self::SERVICE_REQUEST_ID_COLUMN)
            ->selectRaw(sprintf(self::MAX_TEMPLATE, $this->wrap(self::CREATED_AT_COLUMN), $this->wrap(self::RESOLVED_AT_ALIAS)))
            ->where(self::TO_STATUS_COLUMN, ServiceRequestStatus::Resolved->value)
            ->groupBy(self::SERVICE_REQUEST_ID_COLUMN);
    }

    private function latestAssignmentsQuery(): Builder
    {
        return DB::table(self::ASSIGNEE_HISTORIES_TABLE)
            ->selectRaw(sprintf(self::MAX_TEMPLATE, $this->wrap(self::ID_COLUMN), $this->wrap(self::LATEST_ID_ALIAS)))
            ->groupBy(self::SERVICE_REQUEST_ID_COLUMN);
    }

    private function selectPerMonth(
        Builder $query,
        array $months,
        string $dateColumn,
        string $template,
        string $aliasPrefix,
        string $valueExpression = '',
    ): void {
        $wrappedDateColumn = $this->wrap($dateColumn);

        foreach ($months as $index => $month) {
            $query->selectRaw(
                sprintf(
                    self::ALIASED_TEMPLATE,
                    sprintf($template, $wrappedDateColumn, $valueExpression),
                    $this->wrap($aliasPrefix.$index),
                ),
                [$month->startsAt, $month->endsAt],
            );
        }
    }

    private function countExpression(): string
    {
        return sprintf(self::COUNT_TEMPLATE, $this->wrap(self::REQUEST_COUNT_ALIAS));
    }

    private function statusValues(array $statuses): array
    {
        return array_map(fn (ServiceRequestStatus $status): string => $status->value, $statuses);
    }

    private function column(string $table, string $column): string
    {
        return $table.'.'.$column;
    }

    private function wrap(string $value): string
    {
        return DB::connection()->getQueryGrammar()->wrap($value);
    }
}
