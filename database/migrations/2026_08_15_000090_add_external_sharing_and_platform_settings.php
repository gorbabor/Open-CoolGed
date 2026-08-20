<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('created_by');
            $table->string('token', 64)->nullable()->unique()->after('is_external');
            $table->string('password_hash')->nullable()->after('token');
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('shares', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['is_external', 'token', 'password_hash']);
        });
    }
};
