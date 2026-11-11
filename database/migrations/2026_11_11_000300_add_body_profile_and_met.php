<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex', 1)->nullable();      // M | F — usado no cálculo do metabolismo basal
            $table->date('birth_date')->nullable();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->decimal('met', 4, 1)->nullable();  // intensidade usada no cálculo das calorias
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['sex', 'birth_date']));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropColumn('met'));
    }
};
