<?php

namespace App\Http\Controllers\Api;

use App\Enums\DefaultRolesEnum;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportStoreRequest;
use App\Http\Requests\ReportUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\ReportDetailResource;
use App\Http\Resources\ReportListResource;
use App\Models\Report;
use App\Models\RoleUserField;
use App\Services\ReportService;
use App\Services\ResponseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    /**
     * @OA\Get(
     *     path="/api/reports",
     *     operationId="getReports",
     *     tags={"Reports"},
     *     description="Метод для просмотра отчётов",
     *     summary="Метод для просмотра отчётов",
     *     security={{"bearer_token":{}}},
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
     *                     ref="#/components/schemas/Report"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $queryParams = $request->all();
        $reports = $this->reportService->getReports($queryParams);
        $total = $reports->toArray()['total'];

        return ResponseService::success(
            ListResource::make(ReportListResource::class, $reports, $total)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/reports",
     *     operationId="createReports",
     *     tags={"Reports"},
     *     description="Метод для создания нового отчета (конструктор отчетов)",
     *     summary="Метод для создания нового отчета (конструктор отчетов)",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="title"),
     *              @OA\Property(property="description", type="string", example="description"),
     *              @OA\Property(property="period_from", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="period_to", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="filter", type="string", example=""),
     *              @OA\Property(
     *                  property="report_fields",
     *                  type="array",
     *
     *                  @OA\Items(
     *
     *                      @OA\Property(property="field_id", type="int64", example=4),
     *                      @OA\Property(property="user_field_id", type="int64", example=7),
     *                      @OA\Property(property="position", type="int64", example=1),
     *                  ),
     *              )
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
     *                     ref="#/components/schemas/Deal"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function store(ReportStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $report = $this->reportService->createReport($validated);

        return ResponseService::success(
            ReportDetailResource::make($report, 10)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/reports/{id}",
     *     operationId="getReport",
     *     tags={"Reports"},
     *     description="Метод для детального просмотра отчёта",
     *     summary="Метод для детального просмотра отчёта",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id отчёта",
     *          required=true,
     *          in="path",
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
     *                     ref="#/components/schemas/Report"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        if (! $report->canBeViewedBy((int) Auth::id())) {
            Log::warning('Report access denied', ['report_id' => $report->id, 'actor_id' => Auth::id(), 'action' => 'view']);

            return ResponseService::forbidden(message: 'Вы не имеете доступ к этому отчёту');
        }

        // Проверяем доступ к полям, для всех ролей кроме Руководительа.
        if (Auth::user()->role->original_title !== DefaultRolesEnum::SUPER_ADMIN->value) {
            // Идентификаторы пользовательских полей доступных
            // для просмотра авторизованному пользователю.
            $userFieldIds = RoleUserField::where('role_id', '=', Auth::user()->role_id)
                ->get()
                ->pluck('user_field_id');

            $report->load([
                'fields' => function ($query) use ($userFieldIds) {
                    $query->with(['field', 'userField'])
                        ->whereIn('user_field_id', $userFieldIds)
                        ->orWhereNull('user_field_id');
                },
            ]);
        }

        $report->deals = $this->reportService->getDealsByFilter($report->filter, $request->all());

        foreach ($report->deals as $deal) {
            $this->reportService->setReportFieldsValues($report, $deal);
        }

        return ResponseService::success(
            ReportDetailResource::make($report)
        );
    }

    public function export(Request $request, int $id): JsonResponse
    {
        $report = Report::find($id);

        if (! $report) {
            return ResponseService::notFound(message: 'Отчёт не найден');
        }

        if (! $report->canBeViewedBy((int) Auth::id())) {
            Log::warning('Report access denied', ['report_id' => $report->id, 'actor_id' => Auth::id(), 'action' => 'export']);

            return ResponseService::forbidden(message: 'Вы не имеете доступ к этому отчёту');
        }

        // Проверяем доступ к полям, для всех ролей кроме Руководительа.
        if (Auth::user()->role->original_title !== DefaultRolesEnum::SUPER_ADMIN->value) {
            // Идентификаторы пользовательских полей доступных
            // для просмотра авторизованному пользователю.
            $userFieldIds = RoleUserField::where('role_id', '=', Auth::user()->role_id)
                ->get()
                ->pluck('user_field_id');

            $report->load([
                'fields' => function ($query) use ($userFieldIds) {
                    $query->with(['field', 'userField'])
                        ->whereIn('user_field_id', $userFieldIds)
                        ->orWhereNull('user_field_id');
                },
            ]);
        }

        $report->deals = $this->reportService->getDealsByFilterWithoutPaginate($report->filter ?? [], $request->all());

        foreach ($report->deals as $deal) {
            $this->reportService->setReportFieldsValues($report, $deal);
        }

        $exportData = collect([]);

        foreach ($report->deals as $deal) {
            $datum = [];
            foreach ($report->fields as $field) {
                $datum[$field->field->name] = $deal->{$field->field->code};
            }
            $exportData->push($datum);
        }

        $fileContent = Excel::raw(new ReportExport($exportData->toArray()), \Maatwebsite\Excel\Excel::XLSX);

        $fileName = uniqid().'_report.xlsx';
        Storage::disk('public')->put($fileName, $fileContent);

        return ResponseService::success(
            ['url' => Storage::disk('public')->url($fileName)]
        );
    }

    /**
     * @OA\Put(
     *     path="/api/reports/{id}",
     *     operationId="updateReport",
     *     tags={"Reports"},
     *     description="Метод для обновления отчета",
     *     summary="Метод для обновления отчета",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id отчёта",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="title"),
     *              @OA\Property(property="description", type="string", example="description"),
     *              @OA\Property(property="period_from", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="period_to", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="filter", type="string", example=""),
     *              @OA\Property(
     *                  property="report_fields",
     *                  type="array",
     *
     *                  @OA\Items(
     *
     *                      @OA\Property(property="id", type="int64", example=1),
     *                      @OA\Property(property="field_id", type="int64", example=4),
     *                      @OA\Property(property="user_field_id", type="int64", example=7),
     *                      @OA\Property(property="position", type="int64", example=1),
     *                  ),
     *              )
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
     *                     ref="#/components/schemas/Deal"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function update(ReportUpdateRequest $request, Report $report): JsonResponse
    {
        $validated = $request->validated();
        $report = $this->reportService->updateReport($report, $validated);

        return ResponseService::success(
            ReportDetailResource::make($report)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Report $report): JsonResponse
    {
        try {
            $this->reportService->deleteReport($report);

            return ResponseService::success(message: 'Отчёт успешно удалён');
        } catch (AuthorizationException $e) {
            return ResponseService::fromException($e, 'report.destroy');
        }
    }
}
