<?php

namespace App\Services;

use App\Http\Filters\Abstract\QueryFilter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EntityActivityService
{
    public function getActivity(
        Model $model,
        QueryFilter $filter,
        array $activityMap,
        array $pagination = [],
        ?Closure $scope = null
    ): LengthAwarePaginator
    {
        $limit = $pagination['limit'] ?? config('constants.paginator.limit');

        $query = $model->activities()->with('activityable');
        if ($scope !== null) {
            $scope($query);
        }

        $activities = $query
            ->filter($filter)
            ->latest()
            ->paginate($limit);

        Log::debug('entity_activity.list.loaded', ['entity_type' => $model::class, 'entity_id' => $model->getKey(), 'count' => $activities->count()]);

        $transformedItems = $activities->getCollection()
            ->filter(fn ($activity): bool => array_key_exists($activity->activityable_type, $activityMap))
            ->map(function ($activity) use ($activityMap): array {
                $resourceClass = $activityMap[$activity->activityable_type]['resource'];

                return [
                    'type' => $activityMap[$activity->activityable_type]['type'],
                    'data' => new $resourceClass($activity->activityable),
                ];
            });

        return new LengthAwarePaginator(
            $transformedItems,
            $activities->total(),
            $activities->perPage(),
            $activities->currentPage(),
            [
                'path' => $pagination['path'] ?? '',
                'query' => $pagination['query'] ?? [],
            ]
        );
    }
}
