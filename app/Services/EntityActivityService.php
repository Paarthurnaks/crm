<?php

namespace App\Services;

use App\Http\Filters\Abstract\QueryFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class EntityActivityService
{
    public function getActivity(Model $model, QueryFilter $filter, array $activityMap): LengthAwarePaginator
    {
        $limit = request()->input('limit', config('constants.paginator.limit'));

        $activities = $model->activities()
            ->with('activityable')
            ->filter($filter)
            ->latest()
            ->paginate($limit);

        $transformedItems = $activities->getCollection()->map(function ($activity) use ($activityMap) {
            if (array_key_exists($activity->activityable_type, $activityMap)) {
                $resourceClass = $activityMap[$activity->activityable_type]['resource'];

                return [
                    'type' => $activityMap[$activity->activityable_type]['type'],
                    'data' => new $resourceClass($activity->activityable),
                ];
            }

            return null;
        })->filter();

        return new LengthAwarePaginator(
            $transformedItems,
            $activities->total(),
            $activities->perPage(),
            $activities->currentPage(),
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
