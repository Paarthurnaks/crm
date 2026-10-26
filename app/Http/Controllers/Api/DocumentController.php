<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DocumentTemplateFilter;
use App\Http\Requests\Document\UploadRequest;
use App\Http\Requests\Document\UploadUpdateRequest;
use App\Http\Resources\DocumentTemplateResource;
use App\Http\Resources\ListResource;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function index(DocumentTemplateFilter $filter, Request $request): JsonResponse
    {
        $queryParams = $request->all();
        $documentTemplates = $this->service->getList($filter, $queryParams);
        $total = $documentTemplates->toArray()['total'];

        return ResponseService::success(
            ListResource::make(DocumentTemplateResource::class, $documentTemplates, $total)
        );
    }

    public function show(int $id): JsonResponse
    {
        $documentTemplate = DocumentTemplate::find($id);

        if (! $documentTemplate) {
            return ResponseService::notFound(message: __('document template not found'));
        }

        return ResponseService::success(
            DocumentTemplateResource::make($documentTemplate)
        );
    }

    public function store(UploadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $uploadTemplate = $this->service->create($validated, $request->user(), $request->file('template'));

        return ResponseService::success(
            DocumentTemplateResource::make($uploadTemplate)
        );
    }

    public function update(int $id, UploadUpdateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $documentTemplate = $this->service->update($id, $validated);

            return ResponseService::success(
                DocumentTemplateResource::make($documentTemplate)
            );
        } catch (NotFoundResourceException) {
            return ResponseService::notFound(message: __('document template not found'));
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $documentTemplate = DocumentTemplate::find($id);

        if (! $documentTemplate) {
            return ResponseService::notFound(message: __('document template not found'));
        }

        $this->service->delete($documentTemplate);

        return ResponseService::success(message: __('document has been deleted'));
    }

    public function updateTemplate(int $templateId, Request $request): JsonResponse
    {
        if (! $request->hasFile('template')) {
            return ResponseService::badRequest(message: __('template field required'));
        }

        try {
            $this->service->updateTemplate($templateId, $request->file('template'));

            return ResponseService::success(message: __('document template has been updated'));
        } catch (NotFoundResourceException $e) {
            return ResponseService::notFound(message: __('template not found'));
        }
    }
}
