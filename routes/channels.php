<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.*', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return $user->isMemberOfChat($chatId);
});

Broadcast::channel('chat.{chatId}.user.{userId}', function ($user, $chatId, $userId) {
    return $user->id === (int) $userId && $user->isMemberOfChat($chatId);
});

Broadcast::channel('global-chats.{userId}', function ($user, $userId) {
    return true;
});

Broadcast::channel('global-notifications.{userId}', function ($user, $userId) {
    return true;
});

Broadcast::channel('partner.{userId}', function ($user, $userId) {
    return $user->id === (int) $userId && $user->hasRole(\App\Enums\DefaultRolesEnum::PARTNER->value);
});
