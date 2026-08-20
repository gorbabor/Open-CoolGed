<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | suspended
            $table->string('plan')->default('standard');
            $table->unsignedBigInteger('storage_quota_mb')->default(5120);
            $table->unsignedInteger('user_quota')->default(50);
            $table->unsignedBigInteger('max_file_size_mb')->default(50);
            $table->json('settings')->nullable();
            $table->json('branding')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('tenant_id');
            $table->string('status')->default('active')->after('password'); // active | invited | suspended
            $table->boolean('mfa_enabled')->default(false)->after('status');
            $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            $table->timestamp('last_login_at')->nullable()->after('mfa_secret');
            $table->softDeletes()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['is_super_admin', 'status', 'mfa_enabled', 'mfa_secret', 'last_login_at', 'deleted_at']);
        });
        Schema::dropIfExists('tenants');
    }
};
