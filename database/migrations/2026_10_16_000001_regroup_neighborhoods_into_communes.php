<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les « zones » des livreurs ne sont plus Nord / Centre / Est / Sud mais les trois communes du
 * Grand Libreville : Libreville, Akanda, Owendo. Un livreur voit les courses de toute sa commune.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('neighborhoods')->update(['zone' => 'Libreville']);
        DB::table('neighborhoods')->whereIn('name', ['Angondjé', 'Akanda', 'Okala'])->update(['zone' => 'Akanda']);
        DB::table('neighborhoods')->where('name', 'Owendo')->update(['zone' => 'Owendo']);
    }

    public function down(): void
    {
        // Retour à l'ancien découpage approximatif.
        DB::table('neighborhoods')->update(['zone' => 'Centre']);
        DB::table('neighborhoods')->whereIn('name', ['Angondjé', 'Akanda', 'Charbonnages', 'Okala'])->update(['zone' => 'Nord']);
        DB::table('neighborhoods')->whereIn('name', ['Nzeng-Ayong', 'Sibang', 'PK8'])->update(['zone' => 'Est']);
        DB::table('neighborhoods')->whereIn('name', ['Lalala', 'Awendjé', 'Akébé', 'Owendo'])->update(['zone' => 'Sud']);
    }
};
