<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Własne style elementów z edytora wizualnego (klasy lm-x-*), osobno od HTML-a.
        Schema::table('contents', function (Blueprint $table) {
            $table->longText('css_cache')->nullable()->after('html_cache');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('css_cache');
        });
    }
};
