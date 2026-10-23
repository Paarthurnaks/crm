<?php

namespace App\Http\Middleware;

use App\Enums\PermissionTypeEnum;
use App\Mappers\ActionToPermissionMapper;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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
        // if ($operation == "resource") {
        //     $actionName = Route::getCurrentRoute()->getActionMethod();
        //     $operation = $this->actionToPermissionMapper->map($actionName);
        // }

        // $permission = Permission::where([
        //     "role_id" => Auth::user()->role ? Auth::user()->role?->id : null,
        //     "entity" => $entity
        // ])->first();

        // if ((!Role::first()) && (!$permission || $permission->{$operation} == PermissionTypeEnum::FORBIDDEN->value))
        // {
        //     return ResponseService::forbidden(message: "У вас нет прав на совершение данного действия");
        // }

        return $next($request);
    }
}
