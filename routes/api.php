<?php

use App\Enums\PermissionEntityEnum;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\ChatCategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatMemberController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\DealEventController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\Api\FileSystemController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesFunnelController;
use App\Http\Controllers\Api\SalesStageController;
use App\Http\Controllers\Api\SalesTunnelController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskEventController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserFieldController;
use App\Http\Controllers\Api\UserTableSettingsController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('change-password/send-link', 'sendLinkChangePassword');
    Route::post('change-password/change', 'changePassword');

    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('logout', 'logout');
        Route::post('refresh', 'refresh');
        Route::get('me', 'me');
        Route::post('change-user-avatar', 'changeAvatar');
    });

});

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('user-fields/types', [UserFieldController::class, 'types']);
    Route::post('user-field-values/attachments', [UserFieldController::class, 'addAttachment']);
    Route::delete('user-field-values/attachments/{attachmentId}', [UserFieldController::class, 'deleteAttachment']);

    Route::apiResource('reports', ReportController::class);
    Route::get('reports/{id}/download', [ReportController::class, 'export']);

    // api ресурсы
    Route::apiResource('users', UserController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::USERS->value);

    Route::get('user-table-settings', [UserTableSettingsController::class, 'index']);
    Route::put('user-table-settings', [UserTableSettingsController::class, 'update']);

    Route::middleware(['role:partner'])->group(function () {
        Route::get('stats/deals/in-progress', [DealController::class, 'inProgress']);
    });

    Route::apiResource('roles', RoleController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::USERS->value);
    Route::apiResource('sales-funnels', SalesFunnelController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::DEALS->value);
    Route::apiResource('sales-stages', SalesStageController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::DEALS->value);
    Route::apiResource('sales-tunnels', SalesTunnelController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::DEALS->value);
    Route::apiResource('deals', DealController::class)
        ->middleware('check_permissions:resource,'.PermissionEntityEnum::DEALS->value);
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{task}/attachments', [TaskController::class, 'addAttachment']);
    Route::delete('tasks/{task}/attachments/{attachmentId}', [TaskController::class, 'deleteAttachment']);
    Route::apiResource('comments', CommentController::class);
    Route::apiResource('user-fields', UserFieldController::class);
    Route::apiResource('sections', SectionController::class);
    Route::get('sections/{entity}/{id}', [SectionController::class, 'getSectionsByEntity']);
    Route::apiResource('document-templates', DocumentController::class);
    Route::get('fields/{entity}', [FieldController::class, 'getFieldsAndUserFields']);
    Route::put('fields', [FieldController::class, 'updateFieldsOrUserFields']);

    // комментарии к сущностям
    Route::get('/deals/{dealId}/comments', [DealController::class, 'getComments']);
    Route::post('/deals/{dealId}/comments', [DealController::class, 'addComment']);
    Route::get('/tasks/{dealId}/comments', [TaskController::class, 'getComments']);
    Route::post('/tasks/{dealId}/comments', [TaskController::class, 'addComment']);

    Route::get('/deals/{dealId}/activity', [DealController::class, 'getActivity']);
    Route::get('/tasks/{task}/activity', [TaskController::class, 'getActivity']);

    Route::get('/deals/{dealId}/events', [DealEventController::class, 'getDealEvents'])->name('deals.events');
    Route::get('/tasks/{taskId}/events', [TaskEventController::class, 'getTaskEvents'])->name('tasks.events');

    Route::post('/deals/{dealId}/generateDocument/{templateId}', [DealController::class, 'generateDocument']);
    Route::post('/document-templates/{templateId}/uploadNewTemplate', [DocumentController::class, 'updateTemplate']);
    Route::get('/deals/{dealId}/documents', [DealController::class, 'documents']);
    Route::get('/deals/documents/{id}', [DealController::class, 'getDocument']);

    // чаты
    Route::get('chat-categories', ChatCategoryController::class);

    Route::apiResource('chats', ChatController::class);
    Route::prefix('chats')->group(function () {
        // маршруты для работы с участниками чата
        Route::get('/members/{id}', [ChatMemberController::class, 'show']);
        Route::delete('/members/{id}', [ChatMemberController::class, 'destroy']);
        Route::get('/{chatId}/members', [ChatMemberController::class, 'index']);
        Route::post('/{chatId}/members', [ChatMemberController::class, 'store']);

        // маршруты для работы с сообщениями в чате
        Route::post('/messages/{message}/hide', [ChatMessageController::class, 'hideMessage']);
        Route::post('/messages/forward', [ChatMessageController::class, 'forwardMessage']);
        Route::put('/{id}/messages/status', [ChatMessageController::class, 'updateMessageStatus']);
        Route::get('/{chatId}/messages/', [ChatMessageController::class, 'index']);
        Route::post('/{chatId}/messages/', [ChatMessageController::class, 'store']);
        Route::get('/messages/{id}', [ChatMessageController::class, 'show']);
        Route::put('/messages/{id}', [ChatMessageController::class, 'update']);
        Route::delete('/messages/{id}', [ChatMessageController::class, 'destroy']);

        Route::get('/{id}/attachments/images', [ChatAttachmentController::class, 'getAllMediaImages']);
        Route::get('/{id}/attachments/documents', [ChatAttachmentController::class, 'getAllMediaDocuments']);
        Route::get('/{id}/attachments/links', [ChatAttachmentController::class, 'getAllLinks']);
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAsReadAll']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'delete']);

    Route::group(['prefix' => '/file-system'], function () {
        Route::get('/content', [FileSystemController::class, 'content']);
        Route::post('/store-folder', [FileSystemController::class, 'storeFolder']);
        Route::post('/store-file', [FileSystemController::class, 'storeFile']);
        Route::delete('/delete-disk-entity', [FileSystemController::class, 'deleteDiskEntity']);
        Route::post('/return-disk-entity', [FileSystemController::class, 'returnDiskEntity']);
        Route::post('/export', [FileSystemController::class, 'export']);
    });

    Route::get('/download/{id}', DownloadController::class)
        ->whereNumber('id');

    Route::apiResource('knowledge-bases', KnowledgeBaseController::class);

    Route::get('partner-logo', [PartnerController::class, 'getLogo']);
    Route::post('partner-logo', [PartnerController::class, 'setLogo']);

    Route::post('partner-color', [PartnerController::class, 'setColor']);
});
