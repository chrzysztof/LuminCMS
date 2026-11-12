<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->unsignedInteger('width')->default(0);   // 0 = bez ograniczenia
            $table->unsignedInteger('height')->default(0);
            $table->boolean('crop')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->default('');
            $table->string('title')->default('');
            $table->text('caption')->nullable();
            $table->json('variants')->nullable();          // nazwa => {path,width,height,size}
            $table->json('crops')->nullable();             // nazwa rozmiaru => {x,y,width,height} w px oryginału
            $table->float('focal_x')->default(0.5);        // punkt ogniskowy 0..1
            $table->float('focal_y')->default(0.5);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_sizes');
    }
};
