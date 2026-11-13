<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grupy pól własnych. Definicje pól (także zagnieżdżone: repeater, flexible content)
        // trzymane jako drzewo JSON, co ułatwia import i eksport.
        Schema::create('field_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // np. group_oferta
            $table->string('title');
            $table->json('fields');
            // Reguły lokalizacji: lista grup (OR), każda grupa to lista reguł (AND)
            $table->json('location_rules');
            $table->string('position', 20)->default('normal'); // after_title | normal | side
            $table->string('style', 20)->default('default');   // default | seamless
            $table->string('label_placement', 20)->default('top'); // top | left
            $table->string('instruction_placement', 20)->default('label'); // label | field
            $table->json('hide_on_screen')->nullable();
            $table->integer('menu_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_groups');
    }
};
