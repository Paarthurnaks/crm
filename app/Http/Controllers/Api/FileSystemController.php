<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiskEntityTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Filters\DiskEntityFilter;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Resources\DiskEntityResource;
use App\Http\Resources\ListResource;
use App\Models\DiskEntity;
use App\Services\FileSystemService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileSystemController extends Controller
{
    public function __construct(
        private readonly FileSystemService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/file-system/content",
     *     operationId="content",
     *     tags={"DiskEntity"},
     *     description="Получение списка элементов диска",
     *     summary="Получение списка элементов диска",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          description="Количество элементов на странице",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="search",
     *          description="поиск",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="type",
     *          description="фильтр по типу",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="path",
     *          description="фильтр по пути",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="who_created",
     *          description="фильтр по создателю",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="who_changed",
     *          description="фильтр по тому, кто изменил",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="size",
     *          description="фильтр по размеру",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="deleted",
     *          description="фильтр по тому, удален ли файл или нет (true - страница корзины, false - страница диска)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="size",
     *          description="фильтр по размеру",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр по дате создания (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр по дате создания (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр по дате обновления (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр по дате обновления (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *
     *                     @OA\Items(
     *                        ref="#/components/schemas/DiskEntity"
     *                     )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @return JsonResponse
     */
    public function content(Request $request, DiskEntityFilter $filter): mixed
    {
        $limit = $request->get('limit') ?? config('constants.paginator.limit');

        $diskEntities = DiskEntity::filter($filter)->paginate($limit);

        return ResponseService::success(
            ListResource::make(DiskEntityResource::class, $diskEntities, $diskEntities->toArray()['total'])
        );
    }

    /**
     * @OA\Post(
     *     path="/api/file-system/store-folder",
     *     operationId="storeFolder",
     *     tags={"DiskEntity"},
     *     description="Метод для создания папки",
     *     summary="Метод для создания папки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="path", type="string", example="/"),
     *              @OA\Property(property="title", type="string", example="Новая папка"),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DiskEntity"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function storeFolder(StoreFolderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = $validated['path'];

        $disk = Storage::disk('public');
        $folderPath = 'entities/available/'.trim($path, '/').'/'.$validated['title'];
        $disk->makeDirectory($folderPath, 0777, true);

        $folderSize = File::size($disk->path($folderPath));

        $diskEntity = DiskEntity::create([
            'title' => $validated['title'],
            'type' => DiskEntityTypeEnum::FOLDER->value,
            'who_created_id' => Auth::id(),
            'path' => substr_count($path, '/') > 1 ? rtrim($path, '/') : $path,
            'size' => $folderSize,
        ]);
        Auth::user()->role->availableDiskEntities()->attach($diskEntity);

        return ResponseService::success(
            DiskEntityResource::make($diskEntity)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/file-system/store-file",
     *     operationId="storeFile",
     *     tags={"DiskEntity"},
     *     description="Метод для загрузки файла",
     *     summary="Метод для загрузки файла",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="path", type="string", example="/"),
     *              @OA\Property(property="file", type="binary"),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DiskEntity"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function storeFile(StoreFileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($request->hasFile('file')) {
            $path = $validated['path'];

            $file = $validated['file'];
            $filePath = 'entities/available'.$path;
            $filename = $file->getClientOriginalName();

            $file->storeAs($filePath, $filename, 'public');

            $size = File::size($file);

            $diskEntity = DiskEntity::create([
                'title' => $filename,
                'type' => DiskEntityTypeEnum::FILE->value,
                'who_created_id' => Auth::id(),
                'path' => substr_count($path, '/') > 1 ? rtrim($path, '/') : $path,
                'size' => $size,
                'mime_type' => $file->getClientMimeType(),
            ]);

            Auth::user()->role->availableDiskEntities()->attach($diskEntity);

            return ResponseService::success(
                DiskEntityResource::make($diskEntity)
            );
        }

        return ResponseService::badRequest();
    }

    /**
     * @OA\Delete(
     *     path="/api/file-system/delete-disk-entity/",
     *     operationId="deleteDiskEntity",
     *     tags={"DiskEntity"},
     *     description="Метод для удаления элемента диска в корзину",
     *     summary="Метод для удаления элемента диска в корзину",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="disk_entity_id",
     *          description="id элемента диска",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DiskEntity"
     *                 ),
     *              )
     *          )
     *     ),
     * )
     */
    public function deleteDiskEntity(Request $request): JsonResponse
    {
        $diskEntity = DiskEntity::find($request->query('disk_entity_id'));

        if (! $diskEntity) {
            return ResponseService::badRequest(
                message: __('Файл или директория не найдены.')
            );
        }

        $this->service->deleteDiskEntity($diskEntity);

        return ResponseService::success();
    }

    /**
     * @OA\Post(
     *     path="/api/file-system/return-disk-entity",
     *     operationId="returnDiskEntity",
     *     tags={"DiskEntity"},
     *     description="Метод для возврата элемента диска из корзины",
     *     summary="Метод для возврата элемента диска из корзины",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="disk_entity_id",
     *          description="id элемента диска",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DiskEntity"
     *                 ),
     *              )
     *          )
     *     ),
     * )
     */
    public function returnDiskEntity(Request $request): JsonResponse
    {
        $queryParams = $request->all();
        $diskEntity = DiskEntity::find($queryParams['disk_entity_id']);

        if (! $diskEntity) {
            return ResponseService::notFound(
                message: __('Файл или директория не найдены.')
            );
        }

        $this->service->restore($diskEntity);

        return ResponseService::success();
    }

    /**
     * @OA\Post(
     *     path="/api/file-system/export",
     *     operationId="export",
     *     tags={"DiskEntity"},
     *     description="Метод для экспорта элементов диска",
     *     summary="Метод для экспорта элементов диска",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="path",
     *          description="путь экспорта",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *     ),
     * )
     */
    public function export(Request $request): Response
    {
        try {
            $path = $request->input('path', '/');
            $zipFilePath = $this->service->createExportedZip($path);

            return response()->download($zipFilePath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }
}
