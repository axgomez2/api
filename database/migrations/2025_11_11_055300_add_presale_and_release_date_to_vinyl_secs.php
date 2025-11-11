<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vinyl_secs', function (Blueprint $table) {
            // Adicionar is_presale se não existir
            if (!Schema::hasColumn('vinyl_secs', 'is_presale')) {
                $table->boolean('is_presale')->default(false)->after('is_new');
            }
            
            // Adicionar presale_arrival_date se não existir
            if (!Schema::hasColumn('vinyl_secs', 'presale_arrival_date')) {
                $table->date('presale_arrival_date')->nullable()->after('is_presale');
            }
            
            // Adicionar release_date se não existir
            if (!Schema::hasColumn('vinyl_secs', 'release_date')) {
                $table->date('release_date')->nullable()->after('presale_arrival_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vinyl_secs', function (Blueprint $table) {
            if (Schema::hasColumn('vinyl_secs', 'is_presale')) {
                $table->dropColumn('is_presale');
            }
            
            if (Schema::hasColumn('vinyl_secs', 'presale_arrival_date')) {
                $table->dropColumn('presale_arrival_date');
            }
            
            if (Schema::hasColumn('vinyl_secs', 'release_date')) {
                $table->dropColumn('release_date');
            }
        });
    }
};
