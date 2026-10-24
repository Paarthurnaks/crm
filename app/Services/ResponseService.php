<?php

namespace App\Services;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ResponseService
{
    private static function responseParams($status, $errors = [], $data = [], $message = []): array
    {
        return [
            'status' => $status ? 'success' : 'error',
            'message' => $message,
            'data' => (object) $data,
            'errors' => (object) $errors,
        ];
    }

    public static function sendJsonResponse($status, $code = 200, $errors = [], $data = [], $message = []): JsonResponse
    {
        return response()->json(
            self::responseParams($status, $errors, $data, $message),
            $code
        );
    }

    public static function success($data = [], $message = []): JsonResponse
    {
        return self::sendJsonResponse(true, 200, [], $data, $message);
    }

    public static function unSuccess($data = [], $message = []): JsonResponse
    {
        return self::sendJsonResponse(false, 200, [], $data, $message);
    }

    public static function created($data = [], $message = 'Created'): JsonResponse
    {
        return self::sendJsonResponse(true, 201, [], $data, $message);
    }

    public static function badRequest($data = [], $message = 'Bad request'): JsonResponse
    {
        return self::sendJsonResponse(false, 400, [], $data, $message);
    }

    public static function unauthorized($data = [], $message = 'Unauthorized'): JsonResponse
    {
        return self::sendJsonResponse(false, 401, [], $data, $message);
    }

    public static function forbidden($data = [], $message = 'Forbidden'): JsonResponse
    {
        return self::sendJsonResponse(false, 403, [], $data, $message);
    }

    public static function notFound($data = [], $message = 'Not Found'): JsonResponse
    {
        return self::sendJsonResponse(false, 404, [], $data, $message);
    }

    public static function fromException(Throwable $exception, string $operation): JsonResponse
    {
        Log::error('API operation failed', [
            'operation' => $operation,
            'request_id' => request()->header('X-Request-ID'),
            'exception_class' => get_class($exception),
            'previous_exception_class' => $exception->getPrevious() ? get_class($exception->getPrevious()) : null,
            'exception_code' => $exception->getCode(),
        ]);

        if ($exception instanceof AuthorizationException) {
            return self::forbidden(message: 'Недостаточно прав');
        }

        if ($exception instanceof ModelNotFoundException) {
            return self::notFound();
        }

        if ($exception instanceof InvalidArgumentException || $exception instanceof DomainException) {
            return self::badRequest(message: 'Некорректный запрос');
        }

        return self::sendJsonResponse(false, 500, [], [], 'Внутренняя ошибка сервера');
    }
}
