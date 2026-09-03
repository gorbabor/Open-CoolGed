<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharePublicController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\V02Controller;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/mfa/verify', [AuthController::class, 'showMfaVerify'])->name('mfa.verify');
    Route::post('/mfa/verify', [AuthController::class, 'mfaVerify'])->name('mfa.verify.post');
    Route::get('/sso/start', [SsoController::class, 'start'])->name('sso.start');
    Route::get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

    // External shares: token-scoped access (RM-012), optional password, expiry.
    Route::get('/share/{token}', [SharePublicController::class, 'show'])->name('share.external.show');
    Route::post('/share/{token}/unlock', [SharePublicController::class, 'unlock'])->name('share.external.unlock');
    Route::get('/share/{token}/download', [SharePublicController::class, 'download'])->name('share.external.download');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'tenant.context', 'tenant.active', 'app.locale'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile/mfa/enable', [ProfileController::class, 'enableMfa'])->name('profile.mfa.enable');
    Route::post('/profile/mfa/disable', [ProfileController::class, 'disableMfa'])->name('profile.mfa.disable');
    Route::post('/profile/theme-mode', [ProfileController::class, 'updateThemeMode'])->name('profile.theme-mode');
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::post('/profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
    Route::post('/profile/update-password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/upload-version', [DocumentController::class, 'uploadVersion'])->name('documents.upload-version');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'download'])->name('documents.download-version');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/versions/{version}/restore', [DocumentController::class, 'restoreVersion'])->name('documents.restore-version');
    Route::post('/documents/{document}/trash', [DocumentController::class, 'trash'])->name('documents.trash');
    Route::post('/documents/{document}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
    Route::post('/documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::post('/documents/{document}/unarchive', [DocumentController::class, 'unarchive'])->name('documents.unarchive');
    Route::delete('/documents/{document}', [DocumentController::class, 'deletePermanently'])->name('documents.delete-permanent');
    Route::post('/documents/{document}/comments', [DocumentController::class, 'comment'])->name('documents.comment');
    Route::post('/documents/{document}/share', [DocumentController::class, 'share'])->name('documents.share');
    Route::post('/documents/{document}/shares/{share}/revoke', [DocumentController::class, 'revokeShare'])->name('shares.revoke');
    Route::post('/documents/{document}/metadata', [DocumentController::class, 'updateMetadata'])->name('documents.metadata');
    Route::post('/documents/{document}/publish', [DocumentController::class, 'publishPersonal'])->name('documents.publish');

    Route::get('/spaces', [SpaceController::class, 'index'])->name('spaces.index');
    Route::post('/spaces', [SpaceController::class, 'store'])->name('spaces.store');
    Route::post('/spaces/{space}/rename', [SpaceController::class, 'renameSpace'])->name('spaces.rename');
    Route::post('/spaces/{space}/folders', [SpaceController::class, 'addFolder'])->name('folders.store');
    Route::post('/folders/{folder}/rename', [SpaceController::class, 'renameFolder'])->name('folders.rename');
    Route::delete('/folders/{folder}', [SpaceController::class, 'deleteFolder'])->name('folders.delete');
    Route::delete('/spaces/{space}', [SpaceController::class, 'destroy'])->name('spaces.delete');

    Route::get('/search', SearchController::class)->name('search');

    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
    Route::post('/workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('workflows.toggle');
    Route::post('/documents/{document}/workflows/start', [WorkflowController::class, 'start'])->name('workflows.start');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/decide', [TaskController::class, 'decide'])->name('tasks.decide');

    // Périmètre V02
    Route::get('/mes-documents', [V02Controller::class, 'myDocuments'])->name('v02.my-documents');
    Route::post('/documents/{document}/acknowledge', [V02Controller::class, 'acknowledge'])->name('v02.acknowledge');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::post('/documents/{document}/ai', [AiController::class, 'dispatch'])->name('ai.dispatch');
    Route::post('/documents/{document}/ocr', [AiController::class, 'runOcr'])->name('ai.ocr');
    Route::post('/ai/results/{result}/validate', [AiController::class, 'validateResult'])->name('ai.results.validate');
    Route::post('/ai/results/{result}/apply', [AiController::class, 'applyResult'])->name('ai.results.apply');
    Route::get('/admin/ai', [AiController::class, 'index'])->name('admin.ai');

    Route::get('/office/{document}/start', [OfficeController::class, 'start'])->name('office.start');
    Route::get('/office/download/{token}', [OfficeController::class, 'download'])->name('office.download');
    Route::post('/office/return/{token}', [OfficeController::class, 'returnFile'])->name('office.return');
    Route::post('/documents/{document}/office/reimport', [OfficeController::class, 'reimport'])->name('office.reimport');

    Route::get('/office/viewer/{document}', [OfficeController::class, 'viewer'])->name('office.viewer');
    Route::get('/documents/{document}/versions/{version}/content', [OfficeController::class, 'previewContent'])->name('documents.preview-content');
    Route::get('/office/embedded/{token}', [OfficeController::class, 'embedded'])->name('office.embedded');
    Route::post('/office/embedded/save/{token}', [OfficeController::class, 'embeddedSave'])->name('office.embedded.save');
    Route::get('/office/pdf/{token}', [OfficeController::class, 'pdf'])->name('office.pdf');
    Route::post('/office/pdf/save/{token}', [OfficeController::class, 'pdfSave'])->name('office.pdf.save');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::post('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword'])->name('users.reset-password');
        Route::post('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');

        Route::get('/groups', [AdminController::class, 'groups'])->name('groups');
        Route::post('/groups', [AdminController::class, 'storeGroup'])->name('groups.store');
        Route::post('/groups/{group}', [AdminController::class, 'updateGroup'])->name('groups.update');
        Route::post('/groups/{group}/members', [AdminController::class, 'groupMembers'])->name('groups.members');
        Route::post('/groups/{group}/roles', [AdminController::class, 'groupRoles'])->name('groups.roles');
        Route::delete('/groups/{group}', [AdminController::class, 'deleteGroup'])->name('groups.delete');

        Route::get('/roles', [AdminController::class, 'roles'])->name('roles');
        Route::post('/roles', [AdminController::class, 'storeRole'])->name('roles.store');
        Route::post('/roles/{role}', [AdminController::class, 'updateRole'])->name('roles.update');
        Route::post('/roles/{role}/rename', [AdminController::class, 'updateRoleName'])->name('roles.rename');
        Route::post('/roles/{role}/duplicate', [AdminController::class, 'duplicateRole'])->name('roles.duplicate');
        Route::delete('/roles/{role}', [AdminController::class, 'deleteRole'])->name('roles.delete');

        Route::get('/types', [AdminController::class, 'types'])->name('types');
        Route::post('/types', [AdminController::class, 'storeType'])->name('types.store');
        Route::post('/types/{type}', [AdminController::class, 'updateType'])->name('types.update');
        Route::delete('/types/{type}', [AdminController::class, 'deleteType'])->name('types.delete');
        Route::post('/metadata-definitions', [AdminController::class, 'storeMetadataDefinition'])->name('metadata.store');
        Route::post('/metadata-definitions/{definition}', [AdminController::class, 'updateMetadataDefinition'])->name('metadata.update');
        Route::delete('/metadata-definitions/{definition}', [AdminController::class, 'deleteMetadataDefinition'])->name('metadata.delete');

        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/audit/export', [AdminController::class, 'auditExport'])->name('audit.export');

        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/reset-content', [AdminController::class, 'resetContent'])->name('settings.reset-content');
        Route::post('/settings/branding', [AdminController::class, 'updateBranding'])->name('settings.branding');
        Route::post('/settings/test-mail', [AdminController::class, 'testMail'])->name('settings.test-mail');
        Route::post('/settings/test-ai', [AdminController::class, 'testAi'])->name('settings.test-ai');

        Route::get('/referentials', [V02Controller::class, 'referentials'])->name('referentials');
        Route::post('/referentials', [V02Controller::class, 'storeReferential'])->name('referentials.store');
        Route::post('/referentials/{referential}', [V02Controller::class, 'updateReferential'])->name('referentials.update');
        Route::delete('/referentials/{referential}', [V02Controller::class, 'deleteReferential'])->name('referentials.delete');

        // Import CSV (registre V02) — formulaire, template et traitement.
        Route::get('/import-csv', [V02Controller::class, 'importCsvForm'])->name('import-csv');
        Route::get('/import-csv/template', [V02Controller::class, 'downloadTemplate'])->name('import-csv.template');
        Route::post('/import-csv', [V02Controller::class, 'importCsv'])->name('import-csv.post');
    });
});

Route::middleware(['auth', 'tenant.context', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('tenants.store');
    Route::post('/tenants/{tenant}/admin', [SuperAdminController::class, 'storeTenantAdmin'])->name('tenants.admin');
    Route::post('/tenants/{tenant}/toggle', [SuperAdminController::class, 'toggleTenant'])->name('tenants.toggle');
    Route::post('/tenants/{tenant}/reset', [SuperAdminController::class, 'resetTenant'])->name('tenants.reset');
    Route::post('/users/{user}/toggle', [SuperAdminController::class, 'toggleSuperAdmin'])->name('users.toggle');
    Route::post('/super-admins', [SuperAdminController::class, 'storeSuperAdmin'])->name('superadmins.store');
    Route::get('/settings', [SuperAdminController::class, 'platformSettings'])->name('settings');
    Route::post('/settings', [SuperAdminController::class, 'updatePlatformSettings'])->name('settings.update');
    Route::post('/settings/test-ai', [SuperAdminController::class, 'testAi'])->name('settings.test-ai');
});
