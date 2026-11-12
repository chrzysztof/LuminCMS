<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Definicje typów treści (page, post oraz własne)
        Schema::create('content_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label');
            $table->string('label_plural');
            $table->string('icon', 50)->nullable();
            $table->boolean('hierarchical')->default(false);
            // Co typ obsługuje: title, editor, builder, excerpt, thumbnail, seo, page_attributes...
            $table->json('supports')->nullable();
            // Prefiks adresu URL, np. "blog" dla wpisów; pusty dla stron
            $table->string('url_prefix')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Treści wszystkich typów w jednej tabeli
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('status', 20)->default('draft'); // draft | published | scheduled
            $table->text('excerpt')->nullable();
            // Bez klucza obcego do czasu powstania modułu mediów
            $table->unsignedBigInteger('featured_media_id')->nullable();
            $table->string('template')->nullable();
            // Struktura edytora wizualnego (GrapesJS) oraz wyrenderowany, zbuforowany HTML
            $table->json('builder_data')->nullable();
            $table->longText('html_cache')->nullable();
            // Wartości pól własnych, klucze to nazwy pól
            $table->json('fields')->nullable();
            $table->integer('menu_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['content_type_id', 'status', 'published_at']);
            $table->index(['content_type_id', 'parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
        Schema::dropIfExists('content_types');
    }
};
