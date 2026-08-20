<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accusés de lecture (V02 §16, Lot D).
        Schema::create('read_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->unique(['document_version_id', 'user_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('read_ack_required')->default(false)->after('is_active_version');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('read_ack_required');
        });
        Schema::dropIfExists('read_acknowledgements');
    }
};
