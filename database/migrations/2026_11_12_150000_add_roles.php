<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // administrator | editor | author | contributor | subscriber (jak w WordPressie)
            $table->string('role', 30)->default('subscriber')->after('email')->index();
        });

        // Istniejące konta powstały przed rolami: właściciel instalacji ma pozostać administratorem.
        DB::table('users')->update(['role' => 'administrator']);

        Schema::table('content_types', function (Blueprint $table) {
            // Zestaw uprawnień treści: "post" (edit_posts...) albo "page" (edit_pages...).
            $table->string('capability_type', 20)->default('post')->after('url_prefix');
        });

        DB::table('content_types')->where('key', 'page')->update(['capability_type' => 'page']);
        DB::table('content_types')->where('hierarchical', true)->update(['capability_type' => 'page']);
    }

    public function down(): void
    {
        Schema::table('content_types', fn (Blueprint $table) => $table->dropColumn('capability_type'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
