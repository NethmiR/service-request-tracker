<?php

// Creates service requests, lists the requests of one user or of everyone for the dashboard and loads a request with its histories.
// Also lists the staff users a request can be assigned to, and creation writes the request, its tags and the initial status record in one transaction.

namespace App\Services\ServiceRequests;

use App\Data\ServiceRequests\NewServiceRequest;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ServiceRequestService
{
    public const int PAGE_SIZE = 15;

    private const array SUMMARY_RELATIONS = [
        'tags',
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

    public function paginateRequestsOf(User $requester): LengthAwarePaginator
    {
        return $this->summaryQuery()
            ->where('requested_by_user_id', $requester->getKey())
            ->paginate(self::PAGE_SIZE);
    }

    public function paginateAllRequests(): LengthAwarePaginator
    {
        return $this->summaryQuery()->paginate(self::PAGE_SIZE);
    }

    public function listAssignableUsers(): Collection
    {
        return User::query()
            ->select(['id', 'name'])
            ->whereIn('role', UserRole::staffValues())
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function loadDetail(ServiceRequest $serviceRequest): ServiceRequest
    {
        return $serviceRequest->load(self::DETAIL_RELATIONS);
    }

    private function summaryQuery(): Builder
    {
        return ServiceRequest::query()
            ->with(self::SUMMARY_RELATIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
