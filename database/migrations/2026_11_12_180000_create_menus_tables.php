<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            // Lokalizacja zadeklarowana przez motyw (np. primary); jedna lokalizacja = jedno menu.
            $table->string('location', 50)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            // content | term | custom | heading (nagłówek bez odnośnika)
            $table->string('type', 20);
            $table->string('label', 150)->nullable();
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('url', 500)->nullable();
            $table->boolean('new_tab')->default(false);
            // Megamenu: tylko pozycje najwyższego poziomu.
            $table->boolean('mega')->default(false);
            $table->string('mega_layout', 20)->default('container');
            $table->foreignId('mega_part_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
