<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Anciennes catégories texte renommées lors de la migration.
     */
    private const ALIASES = [
        'Épicerie' => 'Épicerie & courses',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained()->restrictOnDelete();
        });

        // Chaque ancienne valeur texte devient une ligne de categories.
        foreach (DB::table('stores')->distinct()->pluck('category') as $old) {
            $name = self::ALIASES[$old] ?? $old;
            $slug = Str::slug($name);

            $categoryId = DB::table('categories')->where('slug', $slug)->value('id')
                ?? DB::table('categories')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('stores')->where('category', $old)->update(['category_id' => $categoryId]);
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('category')->default('')->after('name');
        });

        foreach (DB::table('categories')->get(['id', 'name']) as $category) {
            DB::table('stores')->where('category_id', $category->id)->update(['category' => $category->name]);
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
