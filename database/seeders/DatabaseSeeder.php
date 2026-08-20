<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\Group;
use App\Models\MetadataDefinition;
use App\Models\Space;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Services\SystemRoleService;
use App\Services\V02DocumentService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SystemRoleService::seedPermissions();

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@kaeged.local'],
            [
                'name' => 'Super Admin',
                'password' => 'superadmin123',
                'is_super_admin' => true,
                'status' => 'active',
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Entreprise Démo SA',
                'status' => 'active',
                'plan' => 'standard',
                'storage_quota_mb' => 5120,
                'user_quota' => 50,
                'settings' => ['ai_enabled' => true, 'ai_providers' => ['mock']],
            ]
        );

        TenantContext::set($tenant->id);

        $roles = app(SystemRoleService::class)->ensureFor($tenant);

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Démo',
                'password' => 'password123',
                'status' => 'active',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$roles['tenant_admin']->id => ['tenant_id' => $tenant->id]]);

        $user = User::firstOrCreate(
            ['email' => 'user@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Utilisatrice Démo',
                'password' => 'password123',
                'status' => 'active',
            ]
        );
        $user->roles()->syncWithoutDetaching([$roles['user']->id => ['tenant_id' => $tenant->id]]);

        $validator = User::firstOrCreate(
            ['email' => 'validator@demo.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Validateur Démo',
                'password' => 'password123',
                'status' => 'active',
            ]
        );
        $validator->roles()->syncWithoutDetaching([$roles['validator']->id => ['tenant_id' => $tenant->id]]);

        $group = Group::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Direction'],
            ['tenant_id' => $tenant->id, 'name' => 'Direction']
        );
        $group->users()->syncWithoutDetaching([$admin->id => ['tenant_id' => $tenant->id]]);

        $types = [
            'Contrat' => ['obligations' => 'text', 'montant' => 'number'],
            'Facture' => ['numero' => 'text', 'montant_ht' => 'number'],
        ];
        foreach ($types as $name => $schema) {
            DocumentType::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($name).'-demo'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'metadata_schema' => $schema,
                    'retention_days' => $name === 'Facture' ? 3650 : null,
                ]
            );
        }

        foreach ([['Référence', 'reference', 'text', false], ['Montant HT', 'amount', 'number', false], ['Service', 'department', 'list', false]] as [$name, $key, $type, $req]) {
            MetadataDefinition::firstOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $key],
                ['tenant_id' => $tenant->id, 'name' => $name, 'type' => $type, 'required' => $req, 'options' => $type === 'list' ? ['Finance', 'RH', 'Juridique', 'Projets'] : null]
            );
        }

        // Familles documentaires V02 (POL, MAN, REF, PRO, PRC, INS, FOR, REG, MOD, RAP, PRE, MAT, TDB, CHK, AUT).
        $v02Families = [
            'POL' => 'Politique', 'MAN' => 'Manuel', 'REF' => 'Référentiel', 'PRO' => 'Processus',
            'PRC' => 'Procédure', 'INS' => 'Instruction', 'FOR' => 'Formulaire', 'REG' => 'Registre',
            'MOD' => 'Modèle', 'RAP' => 'Rapport', 'PRE' => 'Preuve', 'MAT' => 'Matrice',
            'TDB' => 'Tableau de bord', 'CHK' => 'Checklist', 'AUT' => 'Autre',
        ];
        foreach ($v02Families as $code => $name) {
            DocumentType::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => strtolower($code).'-v02'],
                ['tenant_id' => $tenant->id, 'name' => $code.' - '.$name, 'slug' => strtolower($code).'-v02']
            );
        }

        // Référentiels V02 de démonstration.
        foreach ([['domain', 'DOC', 'Documentation'], ['domain', 'GOV', 'Gouvernance'], ['domain', 'QMS', 'Qualité'],
            ['process', null, 'Gouvernance documentaire'], ['process', null, 'Publication et diffusion'],
            ['job', null, 'Responsable Qualité'], ['job', null, 'Directeur Général'],
            ['department', null, 'Qualité'], ['department', null, 'Direction Générale'],
            ['site', null, 'Siège'], ['entity', null, 'Entité Groupe'], ['country', null, 'Côte d\'Ivoire']] as [$type, $code, $name]) {
            V02DocumentService::ensureReferential($tenant->id, $type, $name, $code);
        }

        $space = Space::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Direction'],
            ['tenant_id' => $tenant->id, 'name' => 'Direction', 'description' => 'Documents de direction', 'color' => '#0d6efd']
        );
        Space::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'RH'],
            ['tenant_id' => $tenant->id, 'name' => 'RH', 'description' => 'Ressources humaines', 'color' => '#198754']
        );
        Space::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Finances'],
            ['tenant_id' => $tenant->id, 'name' => 'Finances', 'description' => 'Factures et paiements', 'color' => '#dc3545']
        );

        $folder = Folder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'space_id' => $space->id, 'name' => 'Contrats 2026'],
            ['tenant_id' => $tenant->id, 'space_id' => $space->id, 'name' => 'Contrats 2026']
        );

        $contractType = DocumentType::where('tenant_id', $tenant->id)->where('name', 'Contrat')->first();

        $wf = Workflow::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'validation-contrats'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Validation des contrats',
                'document_type_id' => $contractType?->id,
                'is_active' => true,
            ]
        );
        if ($wf->steps()->count() === 0) {
            $wf->steps()->createMany([
                ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Révision juridique', 'assignee_type' => 'role', 'assignee_id' => $roles['validator']->id, 'is_final' => false],
                ['tenant_id' => $tenant->id, 'position' => 2, 'name' => 'Validation direction', 'assignee_type' => 'role', 'assignee_id' => $roles['tenant_admin']->id, 'is_final' => true],
            ]);
        }

        $sample = Storage::disk('local')->put(
            'documents/'.$tenant->id.'/sample-contrat.txt',
            "Contrat de prestation de services entre Entreprise Démo SA et un fournisseur.\nObjet : maintenance des équipements informatiques pour l'année 2026.\nMontant : 24 000 EUR HT. Durée : 12 mois. Conditions de paiement : 30 jours.\n"
        );

        $doc = Document::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Contrat maintenance informatique 2026', 'reference' => 'CTR-2026-001'],
            [
                'tenant_id' => $tenant->id,
                'space_id' => $space->id,
                'folder_id' => $folder->id,
                'document_type_id' => $contractType?->id,
                'title' => 'Contrat maintenance informatique 2026',
                'reference' => 'CTR-2026-001',
                'description' => 'Contrat de maintenance des équipements.',
                'status' => 'approved',
                'confidentiality' => 'confidential',
                'created_by' => $admin->id,
            ]
        );

        if ($doc->versions()->count() === 0) {
            $version = DocumentVersion::create([
                'tenant_id' => $tenant->id,
                'document_id' => $doc->id,
                'version' => '1.0',
                'file_path' => 'documents/'.$tenant->id.'/sample-contrat.txt',
                'file_name' => 'contrat-maintenance-2026.txt',
                'mime_type' => 'text/plain',
                'size' => 260,
                'checksum' => hash_file('sha256', Storage::disk('local')->path('documents/'.$tenant->id.'/sample-contrat.txt')),
                'extracted_text' => "Contrat de prestation de services entre Entreprise Démo SA et un fournisseur.\nObjet : maintenance des équipements informatiques pour l'année 2026.\nMontant : 24 000 EUR HT. Durée : 12 mois. Conditions de paiement : 30 jours.\n",
                'comment' => 'Version initiale',
                'created_by' => $admin->id,
            ]);
            $doc->update(['current_version_id' => $version->id]);
        }

        $tag = Tag::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'contrat'], ['tenant_id' => $tenant->id, 'name' => 'contrat']);
        $doc->tags()->syncWithoutDetaching([$tag->id => ['tenant_id' => $tenant->id]]);

        TenantContext::set(null);
    }
}
