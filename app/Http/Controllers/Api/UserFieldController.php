<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserFieldTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Filters\UserFieldFilter;
use App\Http\Requests\UserField\AddAttachmentRequest;
use App\Http\Requests\UserField\DeleteAttachmentRequest;
use App\Http\Requests\UserField\StoreRequest;
use App\Http\Requests\UserField\UpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\UserFieldResource;
use App\Http\Resources\UserFieldValueResource;
use App\Models\UserField;
use App\Services\ResponseService;
use App\Services\UserFieldService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFieldController extends Controller
{
    public function __construct(private readonly UserFieldService $service) {}

    public function index(Request $request, UserFieldFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $fields = $this->service->getList($filter, $queryParams);
        $total = $fields->toArray()['total'];

        return ResponseService::success(
            ListResource::make(UserFieldResource::class, $fields, $total)
        );
    }

    public function show(int $id): JsonResponse
    {
        $userField = UserField::with('userFieldEnumValues')->find($id);

        if (! $userField) {
            return ResponseService::notFound(
                message: __('Пользовательское поле не найдено')
            );
        }

        return ResponseService::success(
            UserFieldResource::make($userField)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $userField = $this->service->create($validated);

            return ResponseService::created(
                UserFieldResource::make($userField)
            );
        } catch (Exception $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        try {
            $userField = UserField::find($id);

            if (! $userField) {
                return ResponseService::notFound(
                    message: __('Пользовательское поле не найдено')
                );
            }

            $validated = $request->validated();

            $userField = $this->service->update($userField, $validated);

            return ResponseService::success(
                UserFieldResource::make($userField)
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $userField = UserField::find($id);

            if (! $userField) {
                return ResponseService::notFound(message: __('Пользовательское поле не найдено'));
            }

            $this->service->delete($userField);

            return ResponseService::success(message: __('Пользовательское поле удалено'));
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }
    }

    public function types(): JsonResponse
    {
        $types = UserFieldTypeEnum::values();

        $response = [];

        foreach ($types as $type) {
            $response[] = [
                'code' => $type,
                'description' => trans('userFields.'.$type),
            ];
        }

        return ResponseService::success(collect($response));
    }

    public function addAttachment(AddAttachmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userFieldValue = $this->service->addAttachments($data['entity_id'], $data['user_field_id'], $data['attachments']);

        return ResponseService::success(UserFieldValueResource::make($userFieldValue));
    }

    public function deleteAttachment(DeleteAttachmentRequest $request, int $attachmentId): JsonResponse
    {
        $data = $request->validated();
        $userFieldValue = $this->service->deleteAttachment($data['entity_id'], $data['user_field_id'], $attachmentId);
        if ($userFieldValue === null) {
            return ResponseService::notFound();
        }

        return ResponseService::success(
            UserFieldValueResource::make($userFieldValue)
        );
    }
}
