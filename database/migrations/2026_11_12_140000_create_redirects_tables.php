<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            // Ścieżka źródłowa bez domeny i parametrów, małymi literami, np. "/stara-strona".
            $table->string('source')->unique();
            // Ścieżka wewnętrzna ("/nowa") albo pełny adres http(s); puste dla kodu 410.
            $table->string('target', 2048)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301); // 301 | 302 | 307 | 308 | 410
            $table->boolean('active')->default(true);
            // Utworzone automatycznie po zmianie adresu treści (znikają, gdy adres wraca do użycia).
            $table->boolean('auto')->default(false);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        // Monitor błędów 404: najczęściej wywoływane nieistniejące adresy.
        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedBigInteger('hits')->default(1);
            $table->string('last_referrer', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
    }
};
