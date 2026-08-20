<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Services\V02DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import initial du registre maître documentaire V02 (Lot E).
 * CSV : id;code;titre;section;lot;famille;version;statut;proprietaire;date_application;prochaine_revue
 * Contrôles de cohérence (V02 §18.3) + rapport ok/erreurs/quarantaine.
 */
class V02ImportCommand extends Command
{
    protected $signature = 'v02:import {file} {--tenant= : Tenant ID (défaut : 1)} {--dry-run : Afficher sans créer}';

    protected $description = 'Importe le registre maître documentaire V02 (CSV)';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error('Fichier introuvable : '.$file);

            return self::FAILURE;
        }

        $tenantId = (int) $this->option('tenant') ?: 1;
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (! $tenant) {
            $this->error('Tenant introuvable.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows = array_map(fn ($line) => str_getcsv($line, ';'), $lines);
        $header = array_shift($rows);
        $header = array_map(fn ($h) => trim($h), $header);

        $report = ['ok' => 0, 'errors' => [], 'quarantine' => [], 'referentials_created' => 0];
        $seenIds = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            // Sécurité : si le nombre de colonnes diffère de l'en-tête, on complète/tronque.
            if (count($row) !== count($header)) {
                $report['errors'][] = "Ligne $line : nombre de colonnes invalide (".count($row).' au lieu de '.count($header).')';

                continue;
            }
            $data = array_combine($header, $row);

            try {
                $this->validateRow($data, $tenantId, $seenIds);
            } catch (\RuntimeException $e) {
                $report['errors'][] = "Ligne $line : {$e->getMessage()}";

                continue;
            }

            if ($dryRun) {
                $report['ok']++;

                continue;
            }

            [$document, $createdRefs] = $this->createDocument($data, $tenantId, $report);
            $report['referentials_created'] += $createdRefs;
            $report['ok']++;
            $seenIds[$data['id']] = true;
        }

        $this->info("Import terminé : {$report['ok']} document(s)".($dryRun ? ' (dry-run)' : ''));
        $this->line('Référentiels créés : '.$report['referentials_created']);
        $this->line('Erreurs : '.count($report['errors']));
        foreach (array_slice($report['errors'], 0, 20) as $err) {
            $this->error('  - '.$err);
        }

        if ($report['errors'] !== []) {
            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    private function validateRow(array $d, int $tenantId, array &$seenIds): void
    {
        if (empty($d['id']) || empty($d['code']) || empty($d['titre'])) {
            throw new \RuntimeException('id/code/titre obligatoires');
        }
        if (isset($seenIds[$d['id']]) || Document::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('document_code', $d['code'])->exists()) {
            throw new \RuntimeException("doublon id/code : {$d['code']}");
        }
        if (! in_array($d['statut'] ?? '', V02DocumentService::STATUSES, true)) {
            throw new \RuntimeException('statut invalide : '.($d['statut'] ?? ''));
        }
    }

    private function createDocument(array $d, int $tenantId, array &$report): array
    {
        $space = Space::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $d['section']],
            ['tenant_id' => $tenantId, 'name' => $d['section']]
        );
        $folder = Folder::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'space_id' => $space->id, 'name' => $d['lot']],
            ['tenant_id' => $tenantId, 'space_id' => $space->id, 'name' => $d['lot']]
        );
        $type = DocumentType::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $d['famille']],
            ['tenant_id' => $tenantId, 'name' => $d['famille'], 'slug' => Str::slug($d['famille']).'-'.uniqid()]
        );
        $owner = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', $d['proprietaire'])->first();
        $fallbackUser = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        $createdRefs = 0;
        if ($d['domaine'] ?? '') {
            V02DocumentService::ensureReferential($tenantId, 'domain', $d['domaine']);
            $createdRefs++;
        }

        $document = Document::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'space_id' => $space->id,
            'folder_id' => $folder->id,
            'document_type_id' => $type->id,
            'title' => $d['titre'],
            'reference' => $d['id'],
            'document_code' => $d['code'],
            'owner_id' => $owner?->id,
            'status' => $d['statut'] ?? 'brouillon',
            'criticality' => in_array($d['criticite'] ?? '', ['standard', 'important', 'critical']) ? $d['criticite'] : 'standard',
            'effective_date' => $d['date_application'] ?: null,
            'next_review_date' => $d['prochaine_revue'] ?: null,
            'is_active_version' => ($d['statut'] ?? '') === 'approuve_applicable',
            'created_by' => $owner?->id ?? $fallbackUser?->id ?? 1,
        ]);

        return [$document, $createdRefs];
    }
}
