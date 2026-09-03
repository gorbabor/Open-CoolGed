<?php

namespace App\Services;

use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantResetService
{
    /** Tables du contenu métier scopées par tenant_id (purge). */
    private const CONTENT_TABLES = [
        'read_acknowledgements', 'rag_chunks', 'ai_results', 'ai_jobs',
        'office_sessions', 'shares', 'audit_logs', 'notifications', 'comments',
        'workflow_tasks', 'workflow_instances', 'workflow_steps', 'workflows',
        'document_referential', 'metadata_values', 'metadata_definitions',
        'document_tag', 'tags', 'referentials', 'document_versions', 'documents',
        'folders', 'spaces', 'document_types', 'api_tokens',
    ];

    public function reset(Tenant $tenant, bool $recreatePersonalSpaces = true): array
    {
        $counts = ['documents' => 0, 'files_deleted' => 0];

        DB::transaction(function () use ($tenant, &$counts) {
            $counts['documents'] = DB::table('documents')->where('tenant_id', $tenant->id)->count();

            // Fichiers du storage (hors webroot) avant suppression des lignes.
            $paths = DB::table('document_versions')
                ->where('tenant_id', $tenant->id)
                ->pluck('file_path');
            foreach ($paths as $path) {
                if ($path && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                    $counts['files_deleted']++;
                }
            }

            foreach (self::CONTENT_TABLES as $table) {
                DB::table($table)->where('tenant_id', $tenant->id)->delete();
            }
        });

        if ($recreatePersonalSpaces) {
            $users = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->get();
            foreach ($users as $user) {
                app(PersonalSpaceService::class)->ensure($user);
            }
        } else {
            Space::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        }

        return $counts;
    }
}
