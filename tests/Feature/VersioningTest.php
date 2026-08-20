<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Services\DocumentService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VersioningTest extends TestCase
{
    public function test_full_history_and_checksums_after_multiple_changes(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $service = app(DocumentService::class);
        $this->actingAsUser($user);

        $service->addVersion($user, $doc, UploadedFile::fake()->createWithContent('v2.txt', 'contenu v2', 'text/plain'), 'Révision majeure');
        $service->addVersion($user, $doc, UploadedFile::fake()->createWithContent('v3.txt', 'contenu v3', 'text/plain'), '[mineur] retouche');

        $doc->refresh();

        // CA-004: 3 versions, previous ones kept with their checksum.
        $this->assertSame(3, $doc->versions()->count());
        $this->assertSame('2.1', $doc->currentVersion->version);
        $this->assertNotEmpty($doc->versions[2]->checksum);
        $this->assertNotSame($doc->versions[0]->checksum, $doc->versions[1]->checksum);
    }

    public function test_restore_creates_new_version_without_deleting_history(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $service = app(DocumentService::class);
        $this->actingAsUser($user);

        $service->addVersion($user, $doc, UploadedFile::fake()->createWithContent('v2.txt', 'contenu v2', 'text/plain'), 'Révision');
        $doc->refresh();
        $v1 = $doc->versions()->orderBy('id')->first();
        $countBefore = $doc->versions()->count();

        $service->restoreVersion($user, $doc, $v1);
        $doc->refresh();

        // CA-005 / RM-006: restore = new current version, nothing deleted, checksum preserved.
        $this->assertSame($countBefore + 1, $doc->versions()->count());
        $this->assertSame('3.0', $doc->currentVersion->version);
        $this->assertSame($v1->checksum, $doc->currentVersion->checksum);
    }

    public function test_upload_never_overwrites_history(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $this->post(route('documents.upload-version', $doc), [
            'file' => UploadedFile::fake()->createWithContent('v2.txt', 'nouveau contenu', 'text/plain'),
            'comment' => 'Version 2',
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('2.0', $doc->currentVersion->version);
        $this->assertSame('1.0', DocumentVersion::where('document_id', $doc->id)->orderBy('id')->value('version'));
    }

    public function test_upload_with_empty_comment_uses_default(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);

        // Regression: ConvertEmptyStringsToNull turns "" into null; the service
        // requires a string comment, so the default must be applied (no TypeError).
        $response = $this->post(route('documents.upload-version', $doc), [
            'file' => UploadedFile::fake()->createWithContent('v2.txt', 'contenu v2', 'text/plain'),
            'comment' => '',
        ]);

        $response->assertRedirect();
        $doc->refresh();

        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('Nouvelle version', $doc->currentVersion->comment);
    }
}
