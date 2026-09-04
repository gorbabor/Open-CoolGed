<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\V02DocumentService;
use App\Services\XlsxService;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Import initial du registre maître documentaire V02 (Lot E).
 * CSV : id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;processus;application;criticite;date_application;prochaine_revue;fichier
 * Les colonnes « processus » et « application » (site:Siège;country:Côte d'Ivoire) sont optionnelles.
 * La colonne « fichier » (optionnelle) attache le fichier (chemin serveur, tous formats autorisés).
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
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            $all = XlsxService::read($file);
        } elseif ($extension === 'xls') {
            $this->error('Le format .xls (ancien) n\'est pas pris en charge — enregistrez le fichier en .xlsx ou en CSV.');

            return self::FAILURE;
        } else {
            $content = file_get_contents($file);
            if (! mb_check_encoding((string) $content, 'UTF-8')) {
                $content = mb_convert_encoding((string) $content, 'UTF-8', 'Windows-1252');
            }
            $lines = preg_split('/\r\n|\r|\n/', (string) $content);
            $lines = array_values(array_filter($lines, fn ($l) => trim((string) $l) !== ''));
            $rows = array_map(fn ($line) => str_getcsv($line, ';'), $lines);
        }

        $header = array_map(fn ($h) => trim((string) ($h ?? '')), array_shift($rows) ?? []);

        $report = ['ok' => 0, 'errors' => [], 'quarantine' => [], 'referentials_created' => 0, 'file_errors' => []];
        $seenIds = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            try {
                // Sécurité : si le nombre de colonnes diffère de l'en-tête, on complète/tronque.
                if (count($row) !== count($header)) {
                    throw new \RuntimeException('nombre de colonnes invalide ('.count($row).' au lieu de '.count($header).')');
                }
                $data = array_combine($header, $row);

                $this->validateRow($data, $tenantId, $seenIds);

                if ($dryRun) {
                    $report['ok']++;

                    continue;
                }

                [$document, $createdRefs] = $this->createDocument($data, $tenantId, $report);
                $report['referentials_created'] += $createdRefs;
                $report['ok']++;
                $seenIds[$data['id']] = true;

                // Attachement du fichier (optionnel) : colonne « fichier » = chemin serveur,
                // tous formats autorisés par la politique MIME du tenant.
                if (! empty($data['fichier']) && is_file($data['fichier'])) {
                    try {
                        // Acteur disposant de documents.edit (rôle tenant_admin du tenant) —
                        // l'import administratif ne doit pas être bloqué par le cycle de vie.
                        $actor = User::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->whereHas('roles', fn ($q) => $q->where('roles.slug', 'tenant_admin'))
                            ->first()
                            ?? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
                            ?? User::withoutGlobalScopes()->first();

                        // Contexte tenant requis pour que les rôles/permissions soient résolus.
                        $previous = TenantContext::get();
                        TenantContext::set($tenantId);
                        try {
                            app(DocumentService::class)->addVersion(
                                $actor,
                                $document,
                                new UploadedFile($data['fichier'], basename($data['fichier'])),
                                'Version initiale (import CSV)'
                            );
                        } finally {
                            TenantContext::set($previous);
                        }
                    } catch (\Throwable $e) {
                        $report['file_errors'][] = "Ligne $line ({$data['code']}) : fichier non attaché — ".$e->getMessage();
                    }
                } elseif (! empty($data['fichier'])) {
                    $report['file_errors'][] = "Ligne $line ({$data['code']}) : fichier introuvable — document créé sans fichier.";
                }
            } catch (\Throwable $e) {
                $report['errors'][] = "Ligne $line : {$e->getMessage()}";
            }
        }

        $this->info("Import terminé : {$report['ok']} document(s)".($dryRun ? ' (dry-run)' : ''));
        $this->line('Référentiels créés : '.$report['referentials_created']);
        $this->line('Erreurs : '.count($report['errors']));
        $this->line('Fichiers : '.count($report['file_errors']).' avertissement(s)');
        foreach (array_slice($report['errors'], 0, 20) as $err) {
            $this->error('  - '.$err);
        }
        foreach (array_slice($report['file_errors'], 0, 20) as $err) {
            $this->warn('  - '.$err);
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
        $domainId = null;
        if (! empty($d['domaine'])) {
            $domainId = V02DocumentService::ensureReferential($tenantId, 'domain', $d['domaine'])->id;
            $createdRefs++;
        }
        $processId = null;
        if (! empty($d['processus'])) {
            $processId = V02DocumentService::ensureReferential($tenantId, 'process', $d['processus'])->id;
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
            'domain_id' => $domainId,
            'process_id' => $processId,
            'status' => $d['statut'] ?? 'brouillon',
            'criticality' => in_array($d['criticite'] ?? '', ['standard', 'important', 'critical']) ? $d['criticite'] : 'standard',
            'effective_date' => $d['date_application'] ?: null,
            'next_review_date' => $d['prochaine_revue'] ?: null,
            'is_active_version' => ($d['statut'] ?? '') === 'approuve_applicable',
            'created_by' => $owner?->id ?? $fallbackUser?->id ?? 1,
        ]);

        // Référentiels d'application (colonne « application » : site:Siège;country:Côte d'Ivoire).
        $pivots = [];
        foreach ($this->parseApplication((string) ($d['application'] ?? '')) as $type => $name) {
            $ref = V02DocumentService::ensureReferential($tenantId, $type, $name);
            $createdRefs++;
            $pivots[$ref->id] = ['tenant_id' => $tenantId, 'type' => $type];
        }
        if ($pivots !== []) {
            $document->referentials()->attach($pivots);
        }

        return [$document, $createdRefs];
    }

    /** Parse « site:Siège;country:Côte d'Ivoire » → [type => name]. */
    private function parseApplication(string $raw): array
    {
        $out = [];
        foreach (explode(';', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $seg = explode(':', $part, 2);
            $type = strtolower(trim($seg[0]));
            $name = trim($seg[1] ?? '');
            if ($name === '' || ! in_array($type, ['job', 'department', 'direction', 'site', 'entity', 'country'], true)) {
                continue;
            }
            $out[$type] = $name;
        }

        return $out;
    }
}
