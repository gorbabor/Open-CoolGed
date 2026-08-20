<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadAcknowledgement;
use App\Models\Referential;
use App\Services\V02DocumentService;
use App\Support\TenantContext;
use Tests\TestCase;

class V02Test extends TestCase
{
    public function test_approval_blockers_enforce_rules_1_to_5(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $service = app(V02DocumentService::class);

        // Sans propriétaire/dates → 3 erreurs (le fichier+version existent via makeDocument).
        $blockers = $service->approvalBlockers($doc);
        $this->assertCount(3, $blockers);
        $this->assertStringContainsString('propriétaire', $blockers[0]);

        // Compléter les champs → plus aucun bloqueur.
        $doc->update([
            'owner_id' => $user->id,
            'effective_date' => now()->toDateString(),
            'next_review_date' => now()->addYear()->toDateString(),
        ]);
        $this->assertSame([], $service->approvalBlockers($doc->fresh()));
    }

    public function test_approve_sets_applicable_and_obsoletes_previous(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $admin = $this->makeUser($tenant, 'tenant_admin');

        $old = $this->makeDocument($tenant, $user, $space);
        $old->update(['document_code' => 'KAE-DOC-POL-001', 'owner_id' => $user->id,
            'effective_date' => now()->toDateString(), 'next_review_date' => now()->addYear()->toDateString(),
            'status' => 'approuve_applicable', 'is_active_version' => true]);

        $new = $this->makeDocument($tenant, $user, $space);
        $new->update(['document_code' => 'KAE-DOC-POL-001', 'owner_id' => $user->id,
            'effective_date' => now()->toDateString(), 'next_review_date' => now()->addYear()->toDateString()]);

        $service = app(V02DocumentService::class);
        TenantContext::set($tenant->id);
        $approved = $service->approve($new->fresh(), $admin);
        TenantContext::set(null);

        // Règle 9 : l'ancienne version applicable devient obsolète + lien remplace/remplacé.
        $this->assertSame('approuve_applicable', $approved->status);
        $oldReloaded = Document::withoutGlobalScopes()->find($old->id);
        $this->assertSame('obsolete_archive', $oldReloaded->status);
        $this->assertFalse((bool) $oldReloaded->is_active_version);
        $this->assertSame($approved->id, $oldReloaded->replaced_by_document_id);
        $this->assertSame($old->id, $approved->replaces_document_id);
    }

    public function test_obsolete_documents_hidden_from_standard_users(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['status' => 'obsolete_archive']);

        $service = app(V02DocumentService::class);
        $this->assertFalse($service->isVisibleToStandardUser($doc));
    }

    public function test_read_acknowledgement_is_recorded_and_audited(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);
        $doc->update(['read_ack_required' => true]);

        $service = app(V02DocumentService::class);

        $this->assertFalse($service->hasAcknowledged($doc, $user));

        $ack = $service->acknowledge($doc, $user);
        $this->assertNotNull($ack->id);
        $this->assertTrue($service->hasAcknowledged($doc->fresh(), $user));
        $this->assertDatabaseHas('read_acknowledgements', ['document_id' => $doc->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'v02.read_acknowledged']);

        // Idempotent : second appel ne crée pas de doublon.
        $service->acknowledge($doc->fresh(), $user);
        $this->assertSame(1, ReadAcknowledgement::withoutGlobalScopes()->where('document_id', $doc->id)->count());
    }

    public function test_my_documents_filters_by_user_dimensions(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);

        $job = Referential::create(['tenant_id' => $tenant->id, 'type' => 'job', 'name' => 'Responsable Qualité']);
        $user = $this->makeUser($tenant, 'user');
        $user->update(['job_id' => $job->id]);

        $owner = $this->makeUser($tenant, 'user');
        $other = $this->makeUser($tenant, 'user');

        $applicable = $this->makeDocument($tenant, $owner, $space, ['status' => 'approuve_applicable']);
        $applicable->referentials()->attach($job->id, ['tenant_id' => $tenant->id, 'type' => 'job']);

        $notApplicable = $this->makeDocument($tenant, $owner, $space, ['status' => 'approuve_applicable']);

        $this->actingAsUser($user);
        $response = $this->get(route('v02.my-documents'));
        $response->assertOk();

        $docs = $response->viewData('documents');
        $titles = $docs->pluck('title')->all();
        $this->assertContains($applicable->title, $titles);
        $this->assertNotContains($notApplicable->title, $titles);
    }

    public function test_referential_crud_and_dedup(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.referentials.store'), ['type' => 'job', 'name' => 'Responsable Qualité', 'code' => 'RQ'])
            ->assertRedirect();

        $this->assertDatabaseHas('referentials', ['type' => 'job', 'name' => 'Responsable Qualité']);

        // Doublon orthographique (casse différente) → pas de second enregistrement.
        $this->post(route('admin.referentials.store'), ['type' => 'job', 'name' => 'responsable qualité']);
        $this->assertSame(1, Referential::withoutGlobalScopes()->where('type', 'job')->count());
    }

    public function test_referential_can_be_renamed_and_deleted(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $item = V02DocumentService::ensureReferential($tenant->id, 'domain', 'Domaine Test');

        $this->post(route('admin.referentials.update', $item), ['name' => 'Domaine Renommé', 'code' => 'DOM'])
            ->assertRedirect();
        $this->assertDatabaseHas('referentials', ['id' => $item->id, 'name' => 'Domaine Renommé', 'code' => 'DOM']);

        // La page de gestion rend les formulaires de renommage/suppression.
        $this->get(route('admin.referentials', ['type' => 'domain']))
            ->assertOk()
            ->assertSee('Domaine Renommé');

        $this->delete(route('admin.referentials.delete', $item))->assertRedirect();
        $this->assertDatabaseMissing('referentials', ['id' => $item->id]);
    }

    public function test_referential_in_use_cannot_be_deleted(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $admin = $this->makeUser($tenant, 'tenant_admin');

        $domain = V02DocumentService::ensureReferential($tenant->id, 'domain', 'Domaine Utilisé');
        $this->makeDocument($tenant, $user, $space, ['domain_id' => $domain->id]);

        $this->actingAsUser($admin);
        $this->delete(route('admin.referentials.delete', $domain))
            ->assertSessionHasErrors('referential');
        $this->assertDatabaseHas('referentials', ['id' => $domain->id]);
    }

    public function test_referentials_pages_require_admin_permission(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->get(route('admin.referentials'))->assertForbidden();
        $this->post(route('admin.referentials.store'), ['type' => 'domain', 'name' => 'X'])->assertForbidden();
    }

    public function test_review_alerts_command_notifies(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);
        $doc->update([
            'status' => 'approuve_applicable',
            'owner_id' => $user->id,
            'next_review_date' => now()->subDay()->toDateString(), // en retard
        ]);

        $this->artisan('v02:review-alerts')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['type' => 'review.late', 'user_id' => $user->id]);
    }

    public function test_import_command_creates_documents_and_reports_errors(): void
    {
        $tenant = $this->makeTenant();
        $this->makeUser($tenant, 'user'); // utilisateur pour created_by

        $csv = tempnam(sys_get_temp_dir(), 'v02').'.csv';
        file_put_contents($csv, "id;code;titre;section;lot;famille;version;statut;proprietaire;date_application;prochaine_revue;domaine;criticite\n".
            "001;KAE-DOC-POL-001;Politique gouvernance;00 - Gouvernance;Lot 00;POL;V01;approuve_applicable;;2026-01-01;2027-01-01;GOV;critical\n".
            "002;KAE-DOC-POL-002;Doublon;00 - Gouvernance;Lot 00;POL;V01;brouillon;;;;GOV;standard\n");

        $this->artisan('v02:import', ['file' => $csv, '--tenant' => $tenant->id])->assertSuccessful();

        $this->assertDatabaseHas('documents', ['tenant_id' => $tenant->id, 'document_code' => 'KAE-DOC-POL-001']);
        $this->assertDatabaseHas('documents', ['tenant_id' => $tenant->id, 'document_code' => 'KAE-DOC-POL-002']);

        // Une ligne invalide (statut inconnu) est signalée en erreur sans créer de document.
        file_put_contents($csv, "id;code;titre;section;lot;famille;version;statut;proprietaire;date_application;prochaine_revue;domaine;criticite\n".
            "003;KAE-DOC-POL-003;Invalide;00 - Gouvernance;Lot 00;POL;V01;statut_inconnu;;;;GOV;standard\n");
        $this->artisan('v02:import', ['file' => $csv, '--tenant' => $tenant->id])->assertSuccessful();
        $this->assertDatabaseMissing('documents', ['tenant_id' => $tenant->id, 'document_code' => 'KAE-DOC-POL-003']);

        @unlink($csv);
    }
}
