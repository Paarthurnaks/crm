<?php

namespace App\Http\Middleware;

use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Mappers\ActionToPermissionMapper;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\User;
use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionMiddleware
{
    public function __construct(private readonly ActionToPermissionMapper $actionToPermissionMapper) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $operation, string $entity): Response
    {
        $action = $operation === 'resource'
            ? $this->actionToPermissionMapper->map($request->route()?->getActionMethod() ?? '')
            : $operation;
        $user = $request->user();

        Log::debug('Permission check started', [
            'actor_id' => $user?->id,
            'entity' => $entity,
            'action' => $action,
            'route' => $request->route()?->getName(),
        ]);

        if (! $user) {
            return ResponseService::unauthorized();
        }

        if (! $action || ! in_array($action, ['view', 'create', 'update', 'delete', 'import', 'export'], true)) {
            return $this->deny($request, $user, $entity, $action);
        }

        if ($this->isAllowed($request, $user, $entity, $action)) {
            Log::debug('Permission check passed', ['actor_id' => $user->id, 'entity' => $entity, 'action' => $action]);

            return $next($request);
        }

        return $this->deny($request, $user, $entity, $action);
    }

    private function isAllowed(Request $request, User $user, string $entity, string $action): bool
    {
        if ($entity === PermissionEntityEnum::DEALS->value && $request->routeIs('deals.*') && $user->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $deal = $request->route('deal');

            return $action === 'view'
                && ($deal === null || ($deal instanceof Deal && Deal::query()
                    ->where('client', (string) $user->id)
                    ->whereKey($deal->id)
                    ->exists()));
        }

        $permissionEntity = $entity;
        if ($entity === PermissionEntityEnum::USERS->value
            && $request->routeIs('users.*')
            && $user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $permissionEntity = 'clients';
        }

        if (! $user->role_id) {
            return false;
        }

        $permission = Permission::query()
            ->where('role_id', $user->role_id)
            ->where('entity', $permissionEntity)
            ->first();
        $scope = $permission ? $permission->{$action} : null;

        if (! in_array($scope, [PermissionTypeEnum::ALL->value, PermissionTypeEnum::OWN->value, PermissionTypeEnum::OPENED->value], true)) {
            return false;
        }

        if ($scope === PermissionTypeEnum::ALL->value || $action === 'create') {
            return true;
        }

        if ($entity === PermissionEntityEnum::USERS->value && $request->routeIs('users.*')) {
            $target = $request->route('user');

            return $user->hasRole(DefaultRolesEnum::PARTNER->value)
                && ($target === null || ($target instanceof User && $user->clients()->whereKey($target->id)->exists()));
        }

        if ($entity === PermissionEntityEnum::DEALS->value && $request->routeIs('deals.*')) {
            $deal = $request->route('deal');
            if ($deal === null) {
                return $action === 'view';
            }

            if (! $deal instanceof Deal) {
                return false;
            }

            $scopeMethod = $scope === PermissionTypeEnum::OWN->value ? 'ownDeals' : 'openedDeals';

            return Deal::query()->{$scopeMethod}()->whereKey($deal->id)->exists();
        }

        return false;
    }

    private function deny(Request $request, User $user, string $entity, ?string $action): Response
    {
        Log::warning('Permission denied', [
            'actor_id' => $user->id,
            'entity' => $entity,
            'action' => $action,
            'route' => $request->route()?->getName(),
        ]);

        return ResponseService::forbidden(message: 'У вас нет прав на совершение данного действия');
    }
}
