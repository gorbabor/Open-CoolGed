<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiels V02 génériques par tenant (domaines, processus, postes,
        // départements, directions, sites, entités, pays).
        Schema::create('referentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type'); // domain | process | job | department | direction | site | entity | country
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'type', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable()->after('last_login_at');
            $table->unsignedBigInteger('department_id')->nullable()->after('job_id');
            $table->unsignedBigInteger('direction_id')->nullable()->after('department_id');
            $table->unsignedBigInteger('site_id')->nullable()->after('direction_id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('site_id');
            $table->unsignedBigInteger('country_id')->nullable()->after('entity_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_code')->nullable()->after('reference');
            $table->unsignedBigInteger('domain_id')->nullable()->after('document_type_id');
            $table->unsignedBigInteger('process_id')->nullable()->after('domain_id');
            $table->unsignedBigInteger('owner_id')->nullable()->after('process_id');
            $table->unsignedBigInteger('reviewer_id')->nullable()->after('owner_id');
            $table->unsignedBigInteger('approver_id')->nullable()->after('reviewer_id');
            $table->string('criticality')->default('standard')->after('approver_id'); // standard | important | critical
            $table->string('review_frequency')->nullable()->after('criticality'); // annual | semi_annual | quarterly | monthly
            $table->date('next_review_date')->nullable()->after('review_frequency');
            $table->date('effective_date')->nullable()->after('next_review_date');
            $table->unsignedBigInteger('replaces_document_id')->nullable()->after('effective_date');
            $table->unsignedBigInteger('replaced_by_document_id')->nullable()->after('replaces_document_id');
            $table->boolean('is_active_version')->default(true)->after('replaced_by_document_id');
        });

        // Documents applicables par dimensions (multi-valeurs V02).
        Schema::create('document_referential', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('referential_id')->constrained('referentials')->cascadeOnDelete();
            $table->string('type');
            $table->unique(['document_id', 'referential_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_referential');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'document_code', 'domain_id', 'process_id', 'owner_id', 'reviewer_id',
                'approver_id', 'criticality', 'review_frequency', 'next_review_date',
                'effective_date', 'replaces_document_id', 'replaced_by_document_id',
                'is_active_version',
            ]);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_id', 'department_id', 'direction_id', 'site_id', 'entity_id', 'country_id']);
        });
        Schema::dropIfExists('referentials');
    }
};
