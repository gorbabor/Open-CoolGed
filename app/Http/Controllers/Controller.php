<?php

namespace App\Http\Controllers;

use App\Models\AiResult;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Share;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowTask;

abstract class Controller
{
    /*
     * Manual model resolution: runs AFTER auth + tenant context middlewares,
     * so the tenant global scope applies. Route model binding is avoided
     * because it would run before the tenant context exists.
     */

    protected function doc(int $id): Document
    {
        return Document::findOrFail($id);
    }

    protected function version(int $id): DocumentVersion
    {
        return DocumentVersion::findOrFail($id);
    }

    protected function space(int $id): Space
    {
        return Space::findOrFail($id);
    }

    protected function folder(int $id): Folder
    {
        return Folder::findOrFail($id);
    }

    protected function workflow(int $id): Workflow
    {
        return Workflow::findOrFail($id);
    }

    protected function task(int $id): WorkflowTask
    {
        return WorkflowTask::findOrFail($id);
    }

    protected function notification(int $id): Notification
    {
        return Notification::findOrFail($id);
    }

    protected function shareModel(int $id): Share
    {
        return Share::findOrFail($id);
    }

    protected function aiResult(int $id): AiResult
    {
        return AiResult::findOrFail($id);
    }

    protected function group(int $id): Group
    {
        return Group::findOrFail($id);
    }

    protected function role(int $id): Role
    {
        return Role::findOrFail($id);
    }

    protected function tenant(int $id): Tenant
    {
        return Tenant::findOrFail($id);
    }

    protected function tenantUser(int $id): User
    {
        $user = User::findOrFail($id);

        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(404);
        }

        return $user;
    }
}
