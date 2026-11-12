<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ustawienia witryny: jedna grupa (np. "seo") = jeden wiersz z JSON-em.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Ustawienia SEO pojedynczej treści (tytuł, opis, canonical, robots, Open Graph).
        Schema::table('contents', function (Blueprint $table) {
            $table->json('seo')->nullable()->after('fields');
        });
    }

    public function down(): void
    {
        Schema::table('contents', fn (Blueprint $table) => $table->dropColumn('seo'));
        Schema::dropIfExists('settings');
    }
};
