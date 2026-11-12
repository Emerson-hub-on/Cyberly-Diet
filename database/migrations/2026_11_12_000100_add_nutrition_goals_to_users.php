<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metas nutricionais personalizáveis. Valor NULL = automático (o sistema calcula a partir de
 * sexo, idade, altura e peso); preenchido = definido pelo usuário (ex.: orientação do nutricionista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('bmr_kcal')->nullable();        // taxa metabólica basal
            $table->unsignedSmallInteger('protein_goal_g')->nullable();
            $table->unsignedSmallInteger('carbs_goal_g')->nullable();
            $table->unsignedSmallInteger('fat_goal_g')->nullable();
            $table->unsignedSmallInteger('fiber_goal_g')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('calorie_goal')->nullable()->default(null)->change();
            $table->unsignedInteger('water_goal_ml')->nullable()->default(null)->change();
        });

        // Quem ainda estava com o padrão antigo (2000) passa a usar o cálculo automático
        DB::table('users')->where('calorie_goal', 2000)->update(['calorie_goal' => null]);
        DB::table('users')->where('water_goal_ml', 2000)->update(['water_goal_ml' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('calorie_goal')->update(['calorie_goal' => 2000]);
        DB::table('users')->whereNull('water_goal_ml')->update(['water_goal_ml' => 2000]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('calorie_goal')->nullable(false)->default(2000)->change();
            $table->unsignedInteger('water_goal_ml')->nullable(false)->default(2000)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bmr_kcal', 'protein_goal_g', 'carbs_goal_g', 'fat_goal_g', 'fiber_goal_g']);
        });
    }
};
