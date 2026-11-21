<?php

// Creates service requests, lists the requests of one user or of everyone for the dashboard and loads a request with its histories.
// Both lists filter by status, assignee and priority and search the title, and creation writes the request with its priority, its tags and the initial status record in one transaction.
// Also lists the staff users a request can be assigned to.

namespace App\Services\ServiceRequests;

use App\Data\ServiceRequests\NewServiceRequest;
use App\Data\ServiceRequests\ServiceRequestFilters;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use App\Models\PriorityTag;
use App\Models\ServiceRequest;
use App\Models\Tag;
use App\Models\User;
use App\Support\Database\TitleSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ServiceRequestService
{
    public const int PAGE_SIZE = 15;

    private const array SUMMARY_RELATIONS = [
        'tags',
        'priorityTag',
        'requester',
        'currentAssigneeHistory.assignedTo',
    ];

    private const array DETAIL_RELATIONS = [
        ...self::SUMMARY_RELATIONS,
        'statusHistories.changedBy',
        'assigneeHistories.assignedFrom',
        'assigneeHistories.assignedTo',
    ];

    public function __construct(
        private readonly ServiceRequestHistoryRecorder $historyRecorder,
    ) {}

    public function createRequest(User $requester, NewServiceRequest $newRequest): ServiceRequest
    {
        $serviceRequest = DB::transaction(function () use ($requester, $newRequest): ServiceRequest {
            $serviceRequest = new ServiceRequest([
                'title' => $newRequest->title,
                'description' => $newRequest->description,
            ]);
            $serviceRequest->requester()->associate($requester);
            $serviceRequest->priorityTag()->associate(
                PriorityTag::query()->where('name', $newRequest->priority->value)->firstOrFail(),
            );
            $serviceRequest->status = ServiceRequestStatus::Open;
            $serviceRequest->save();

            $tagIds = Tag::query()
                ->whereIn('name', array_map(fn (ServiceRequestTag $tag): string => $tag->value, $newRequest->tags))
                ->pluck('id')
                ->all();
            $serviceRequest->tags()->attach($tagIds);

            $this->historyRecorder->recordCreation($serviceRequest);

            return $serviceRequest;
        });

        return $this->loadDetail($serviceRequest);
    }

    public function paginateRequestsOf(User $requester, ServiceRequestFilters $filters): LengthAwarePaginator
    {
        return $this->summaryQuery($filters)
            ->where('requested_by_user_id', $requester->getKey())
            ->paginate(self::PAGE_SIZE);
    }

    public function paginateAllRequests(ServiceRequestFilters $filters): LengthAwarePaginator
    {
        return $this->summaryQuery($filters)->paginate(self::PAGE_SIZE);
    }

    public function listAssignableUsers(): Collection
    {
        return $this->userReferenceQuery()
            ->whereIn('role', UserRole::staffValues())
            ->get();
    }

    public function listCurrentAssigneesOfRequestsBy(User $requester): Collection
    {
        $assigneeId = (new User)->qualifyColumn('id');

        return $this->userReferenceQuery()
            ->whereExists(
                ServiceRequest::query()
                    ->where('requested_by_user_id', $requester->getKey())
                    ->whereHas(
                        'currentAssigneeHistory',
                        fn (Builder $history): Builder => $history->whereColumn(
                            $history->qualifyColumn('assigned_to_user_id'),
                            $assigneeId,
                        ),
                    ),
            )
            ->get();
    }

    public function loadDetail(ServiceRequest $serviceRequest): ServiceRequest
    {
        return $serviceRequest->load(self::DETAIL_RELATIONS);
    }

    private function summaryQuery(ServiceRequestFilters $filters): Builder
    {
        return ServiceRequest::query()
            ->with(self::SUMMARY_RELATIONS)
            ->when(
                $filters->status !== null,
                fn (Builder $query): Builder => $query->where('status', $filters->status),
            )
            ->when(
                $filters->assigneeId !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'currentAssigneeHistory',
                    fn (Builder $history): Builder => $history->where(
                        $history->qualifyColumn('assigned_to_user_id'),
                        $filters->assigneeId,
                    ),
                ),
            )
            ->when(
                $filters->priority !== null,
                fn (Builder $query): Builder => $query->whereRelation('priorityTag', 'name', $filters->priority->value),
            )
            ->when(
                $filters->search !== null,
                fn (Builder $query): Builder => TitleSearch::apply($query, 'title', $filters->search),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function userReferenceQuery(): Builder
    {
        return User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->orderBy('id');
    }
}
