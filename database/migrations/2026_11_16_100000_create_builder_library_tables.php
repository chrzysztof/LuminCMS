<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Własne bloki użytkownika: zapisane sekcje edytora wizualnego, do wielokrotnego wstawiania.
        Schema::create('saved_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Czysty HTML sekcji (klasy lm-*, bez stylów inline); przy zapisie strony i tak przechodzi przez whitelistę renderera.
            $table->longText('html');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Historia wersji edytora wizualnego: ręczne zapisy, publikacje i autozapisy.
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // save | publish | autosave
            $table->string('kind', 20);
            $table->char('hash', 40);
            $table->longText('builder_data');
            $table->timestamps();

            $table->index(['content_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('saved_blocks');
    }
};
