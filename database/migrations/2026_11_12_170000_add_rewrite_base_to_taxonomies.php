<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Przedrostek publicznego adresu terminów, np. /kategoria/nowosci.
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->string('rewrite_base', 50)->nullable()->unique()->after('key');
        });

        foreach (['category' => 'kategoria', 'tag' => 'tag'] as $key => $base) {
            DB::table('taxonomies')->where('key', $key)->update(['rewrite_base' => $base]);
        }

        DB::table('taxonomies')->whereNull('rewrite_base')->get(['id', 'key'])->each(
            fn ($t) => DB::table('taxonomies')->where('id', $t->id)->update(['rewrite_base' => str_replace('_', '-', $t->key)]),
        );
    }

    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->dropUnique(['rewrite_base']);
            $table->dropColumn('rewrite_base');
        });
    }
};
