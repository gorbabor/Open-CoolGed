<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('color');
            $table->foreignId('personal_user_id')->nullable()->after('is_personal')->constrained('users')->nullOnDelete();
            $table->unique(['tenant_id', 'personal_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'personal_user_id']);
            $table->dropConstrainedForeignId('personal_user_id');
            $table->dropColumn('is_personal');
        });
    }
};
