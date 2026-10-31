<?php

namespace App\Services;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NotificationService
{
    public function getNotifications(array $data, User $actor): array
    {
        $query = $actor->notifications();
        if (isset($data['search_term'])) {
            $this->applySearchFilter($query, $data['search_term']);
        }
        if (isset($data['date'])) {
            $this->applyDateFilter($query, $data['date']);
        }

        $notifications = $query->orderByDesc('created_at')->paginate(config('notifications.pagination_items'));

        $totalNotifications = $actor->unreadNotifications()->count();

        return [
            'notifications' => NotificationResource::collection($notifications)->response()->getData(true),
            'total_unread_notifications' => $totalNotifications,
        ];
    }

    private function applySearchFilter(MorphMany $query, string $searchTerm): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $expression = match ($driver) {
            'mysql' => "REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.html')), '<[^>]+>', '')",
            'pgsql' => "REGEXP_REPLACE(data::jsonb->>'html', '<[^>]+>', '', 'g')",
            default => null,
        };

        if ($expression === null) {
            Log::error('Notification search does not support database driver', ['driver' => $driver]);
            throw new RuntimeException('Поиск уведомлений не поддерживается для этой базы данных');
        }

        $query->whereRaw($expression.' LIKE ?', ["%$searchTerm%"]);
    }

    private function applyDateFilter(MorphMany $query, string $dateValue): void
    {
        $date = Carbon::createFromFormat('Y-m-d', $dateValue);
        $query->where('created_at', '>=', $date->startOfDay())
            ->where('created_at', '<=', $date->copy()->endOfDay());
    }

    public function markAsRead(array $data, User $actor): void
    {
        $actor->unreadNotifications()
            ->whereIn('id', $data['notification_ids'])
            ->get()
            ->markAsRead();
    }

    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }

    public function markAsReadAll(User $actor): void
    {
        $actor->unreadNotifications()
            ->get()
            ->markAsRead();
    }
}
