<?php

// Loads the four overview highlight lists, each limited to a few requests with the requester and the current assignee eager loaded.
// Every request carries the time of its latest status record, which orders and filters the stale and recently resolved lists.

namespace App\Services\Overview;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class OverviewHighlightsRepository
{
    public const int HIGHLIGHT_LIMIT = 5;

    private const string LAST_STATUS_CHANGED_AT = 'last_status_changed_at';

    private const string DATE_TIME_CAST = 'immutable_datetime';

    private const string MAX_TEMPLATE = 'MAX(%s)';

    private const array RELATIONS = [
        'requester',
        'currentAssigneeHistory.assignedTo',
    ];

    private const array UNRESOLVED_STATUSES = [
        ServiceRequestStatus::Open,
        ServiceRequestStatus::Assigned,
        ServiceRequestStatus::InProgress,
    ];

    private const array ACTIVE_ASSIGNMENT_STATUSES = [
        ServiceRequestStatus::Assigned,
        ServiceRequestStatus::InProgress,
    ];

    public function __construct(
        #[Config('overview.stale_after_days')]
        private readonly int $staleAfterDays,
    ) {}

    public function oldestOpen(): Collection
    {
        return $this->baseQuery()
            ->where($this->requestColumn('status'), ServiceRequestStatus::Open->value)
            ->orderBy($this->requestColumn('created_at'))
            ->orderBy($this->requestColumn('id'))
            ->get();
    }

    public function assignedTo(User $viewer): Collection
    {
        return $this->baseQuery()
            ->whereIn($this->requestColumn('status'), $this->statusValues(self::ACTIVE_ASSIGNMENT_STATUSES))
            ->whereHas(
                'currentAssigneeHistory',
                fn (Builder $query): Builder => $query->where('assigned_to_user_id', $viewer->getKey()),
            )
            ->orderBy($this->requestColumn('created_at'))
            ->orderBy($this->requestColumn('id'))
            ->get();
    }

    public function stale(CarbonImmutable $now): Collection
    {
        return $this->baseQuery()
            ->whereIn($this->requestColumn('status'), $this->statusValues(self::UNRESOLVED_STATUSES))
            ->where($this->lastStatusChangeQuery(), '<', $now->subDays($this->staleAfterDays))
            ->orderBy(self::LAST_STATUS_CHANGED_AT)
            ->orderBy($this->requestColumn('id'))
            ->get();
    }

    public function recentlyResolved(): Collection
    {
        return $this->baseQuery()
            ->where($this->requestColumn('status'), ServiceRequestStatus::Resolved->value)
            ->orderByDesc(self::LAST_STATUS_CHANGED_AT)
            ->orderByDesc($this->requestColumn('id'))
            ->get();
    }

    public function staleAfterDays(): int
    {
        return $this->staleAfterDays;
    }

    private function baseQuery(): Builder
    {
        return ServiceRequest::query()
            ->select($this->requestColumn('*'))
            ->selectSub($this->lastStatusChangeQuery(), self::LAST_STATUS_CHANGED_AT)
            ->withCasts([self::LAST_STATUS_CHANGED_AT => self::DATE_TIME_CAST])
            ->with(self::RELATIONS)
            ->limit(self::HIGHLIGHT_LIMIT);
    }

    private function lastStatusChangeQuery(): QueryBuilder
    {
        $statusHistory = new ServiceRequestStatusHistory;

        return DB::table($statusHistory->getTable())
            ->selectRaw(sprintf(
                self::MAX_TEMPLATE,
                DB::connection()->getQueryGrammar()->wrap($statusHistory->qualifyColumn('created_at')),
            ))
            ->whereColumn($statusHistory->qualifyColumn('service_request_id'), $this->requestColumn('id'));
    }

    private function requestColumn(string $column): string
    {
        return (new ServiceRequest)->qualifyColumn($column);
    }

    private function statusValues(array $statuses): array
    {
        return array_map(fn (ServiceRequestStatus $status): string => $status->value, $statuses);
    }
}
