<?php

namespace App\Http\Middleware;

use App\Enums\DefaultRolesEnum;
use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckClientDealMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        if ($user->hasRole(DefaultRolesEnum::CLIENT->value) && ! $user->partnerDeals()->exists()) {
            Auth::logout();

            return ResponseService::unauthorized(message: 'У вас нет привязанных сделок. В доступе отказано.');
        }

        return $next($request);
    }
}
