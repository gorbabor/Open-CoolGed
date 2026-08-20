<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\WorkflowTask;
use App\Services\PermissionService;
use App\Services\StorageService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PermissionService $permissions, StorageService $storage)
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.tenants');
        }

        $accessibleIds = $permissions->accessibleDocumentIds($user);

        $recent = Document::whereIn('id', $accessibleIds ?: [0])
            ->with(['currentVersion', 'type', 'space'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $favorites = Document::whereIn('id', $accessibleIds ?: [0])
            ->whereHas('tags', fn ($q) => $q->where('name', 'favori'))
            ->limit(5)
            ->get();

        $tasks = WorkflowTask::where('assignee_user_id', $user->id)
            ->orWhere('delegated_to_id', $user->id)
            ->where('status', 'pending')
            ->with(['instance.document'])
            ->orderBy('due_at')
            ->limit(10)
            ->get();

        $notifications = $user->notifications()->limit(8)->get();

        return view('dashboard', [
            'recent' => $recent,
            'favorites' => $favorites,
            'tasks' => $tasks,
            'notifications' => $notifications,
            'storageUsedMb' => $storage->tenantStorageMb($user->tenant),
            'storageQuotaMb' => $user->tenant->storage_quota_mb,
        ]);
    }
}
