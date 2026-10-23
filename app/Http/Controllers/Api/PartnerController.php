<?php

namespace App\Http\Controllers\Api;

use App\Enums\DefaultRolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerLogo\SetLogoRequest;
use App\Http\Resources\MediaResource;
use App\Models\PartnerClient;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PartnerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/partner-logo",
     *     tags={"Партнеры"},
     *     summary="Get client or partner logo",
     *     description="Retrieve the logo for the authenticated user or their partner",
     *     operationId="getLogo",
     *     security={
     *          {"bearer_token": {}}
     *      },
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logo retrieved successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/MediaResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Logo not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function getLogo(): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            $logo = $user->getFirstMedia('logo') ?? $user->partner?->getFirstMedia('logo');

            if ($logo) {
                return ResponseService::success(
                    MediaResource::make($logo)
                );
            }

            return ResponseService::notFound();
        }

        return ResponseService::forbidden();
    }

    /**
     * @OA\Post(
     *     path="/api/partner-logo",
     *     tags={"Партнеры"},
     *     summary="Set user logo",
     *     description="Set the logo for the authenticated partner user",
     *     operationId="setLogo",
     *     security={
     *          {"bearer_token": {}}
     *      },
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"logo"},
     *
     *             @OA\Property(property="logo", type="string", format="binary", description="The logo image file")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logo uploaded successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/MediaResource")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function setLogo(SetLogoRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $user->clearMediaCollection('logo');
            $logo = $user->addMedia($request->validated('logo'))->toMediaCollection('logo');

            return ResponseService::success(
                MediaResource::make($logo)
            );
        }

        return ResponseService::forbidden();
    }

    #[OA\Post(
        path: '/api/partner-color',
        summary: 'Установить персональный цвет партнера.',
        security: ['{{"bearer_token":{}}}'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\JsonContent(
                    properties: [
                        new OA\Property('color'),
                    ],
                ),
            ]
        ),
        tags: ['Партнеры'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful Operation',
                content: new OA\JsonContent(
                    type: 'string'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function setColor(Request $request): JsonResponse
    {
        $request->validate([
            'color' => ['required', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $partner = $request->user();

        if ($partner->hasRole(DefaultRolesEnum::PARTNER->value)) {
            $userIds = PartnerClient::where('partner_id', $partner->id)
                ->get()
                ->pluck('client_id')
                ->toArray();

            // Идентификаторы клиентов и партнера у которых нужно обновить цвет.
            $userIds[] = $partner->id;

            User::whereIn('id', $userIds)->update([
                'color' => $request->input('color'),
            ]);

            return ResponseService::success($request->input('color'));
        }

        return ResponseService::forbidden();
    }
}
