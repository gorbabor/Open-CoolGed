<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleViewPrefsNotificationsTest extends TestCase
{
    public function test_locale_switch_persists_and_translates_layout(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // Bascule vers l'anglais.
        $this->post(route('profile.locale'), ['locale' => 'en'])->assertRedirect();
        $user->refresh();
        $this->assertSame('en', $user->locale);

        // La page suivante est rendue en anglais (sidebar).
        $response = $this->get(route('documents.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('My documents', $html);
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringNotContainsString('>Mes documents<', $html);
    }

    public function test_locale_falls_back_to_tenant_language(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['language' => 'en'])]);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $this->assertSame('en', App::getLocale());
        $this->assertStringContainsString('My documents', $response->getContent());
    }

    public function test_locale_ignores_invalid_value(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.locale'), ['locale' => 'de'])->assertSessionHasErrors('locale');
        $user->refresh();
        $this->assertNull($user->locale);
    }

    public function test_doc_view_persists_in_database(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space']))->assertOk();
        $user->refresh();
        $this->assertSame('cards', $user->doc_view);
        $this->assertSame('space', $user->doc_group);

        // Nouvelle requête sans paramètre view → le mode persisté est appliqué.
        $response = $this->get(route('documents.index'));
        $response->assertOk();
        $this->assertStringContainsString('Regrouper par', $response->getContent());
    }

    public function test_doc_group_persists_in_database(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        $this->get(route('documents.index', ['view' => 'cards', 'group' => 'status']))->assertOk();
        $user->refresh();
        $this->assertSame('status', $user->doc_group);

        $response = $this->get(route('documents.index', ['view' => 'cards']));
        $response->assertOk();
        $this->assertStringContainsString('value="status" selected', $response->getContent());
    }

    public function test_notification_badge_shows_unread_count(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'type' => 'test', 'title' => 'N1', 'body' => 'B1']);
        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'type' => 'test', 'title' => 'N2', 'body' => 'B2', 'read_at' => now()]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $this->assertStringContainsString('id="notifBadge"', $response->getContent());
        $this->assertStringContainsString('>1</span>', $response->getContent());
    }

    public function test_unread_count_endpoint_returns_json(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'type' => 'test', 'title' => 'N1', 'body' => 'B1']);

        $response = $this->getJson(route('notifications.unread-count'));
        $response->assertOk()->assertJson(['count' => 1]);
    }
}
