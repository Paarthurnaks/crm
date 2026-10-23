<?php

namespace App\Services;

use App\Http\Resources\NotificationResource;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * @return LengthAwarePaginator
     */
    public function getNotifications(array $data): array
    {
        // Получаем пагинированный список уведомлений
        $notifications = auth()->user()
            ->notifications()
            ->when(isset($data['search_term']), function ($query) use ($data) {
                $searchTerm = $data['search_term'];

                // Очистка HTML и поиск по содержимому
                $query->whereRaw("REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.html')), '<[^>]+>', '') LIKE ?", ["%$searchTerm%"]);
            })
            ->when(isset($data['date']), function ($query) use ($data) {
                $date = Carbon::createFromFormat('Y-m-d', $data['date']);
                $startOfDay = $date->startOfDay();
                $endOfDay = $date->copy()->endOfDay();

                $query->where('created_at', '>=', $startOfDay)
                    ->where('created_at', '<=', $endOfDay);
            })
            ->orderByDesc('created_at')
            ->paginate(config('notifications.pagination_items'));

        $totalNotifications = auth()->user()->unreadNotifications()->count();

        return [
            'notifications' => NotificationResource::collection($notifications)->response()->getData(true),
            'total_unread_notifications' => $totalNotifications,
        ];
    }

    public function markAsRead(array $data): void
    {
        auth()->user()->unreadNotifications()
            ->whereIn('id', $data['notification_ids'])
            ->get()
            ->markAsRead();
    }

    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }

    public function markAsReadAll(): void
    {
        auth()->user()->unreadNotifications()
            ->get()
            ->markAsRead();
    }
}
