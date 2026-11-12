<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Definicje taksonomii (category, tag oraz własne)
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label');
            $table->string('label_plural');
            $table->boolean('hierarchical')->default(false); // true: jak kategorie, false: jak tagi
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Które typy treści używają danej taksonomii
        Schema::create('taxonomy_content_type', function (Blueprint $table) {
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
            $table->primary(['taxonomy_id', 'content_type_id']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
        });

        Schema::create('content_term', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->primary(['content_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_term');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('taxonomy_content_type');
        Schema::dropIfExists('taxonomies');
    }
};
