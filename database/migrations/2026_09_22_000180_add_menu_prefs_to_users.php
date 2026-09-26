<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('menu_hidden')->nullable()->after('my_doc_columns');
            $table->string('menu_startup')->nullable()->after('menu_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['menu_hidden', 'menu_startup']);
        });
    }
};
