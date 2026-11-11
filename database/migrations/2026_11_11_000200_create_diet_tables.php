<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('meal_type', 20); // cafe_da_manha | lanche | almoco | jantar
            $table->dateTime('eaten_at');
            $table->string('photo_path')->nullable();
            $table->string('title')->nullable();          // nome do prato identificado pela IA
            $table->json('items')->nullable();            // alimentos identificados com porções
            $table->unsignedInteger('calories')->default(0);
            $table->decimal('protein_g', 7, 1)->default(0);
            $table->decimal('carbs_g', 7, 1)->default(0);
            $table->decimal('sugar_g', 7, 1)->default(0);
            $table->decimal('fat_g', 7, 1)->default(0);
            $table->decimal('fiber_g', 7, 1)->default(0);
            // Distribuição calórica dos macros (% das kcal da refeição)
            $table->decimal('protein_pct', 5, 1)->default(0);
            $table->decimal('carbs_pct', 5, 1)->default(0);
            $table->decimal('fat_pct', 5, 1)->default(0);
            // % da meta calórica diária que esta refeição representa
            $table->decimal('goal_pct', 6, 1)->default(0);
            $table->string('confidence', 10)->nullable(); // baixa | media | alta
            $table->text('notes')->nullable();            // observação do usuário
            $table->text('ai_notes')->nullable();         // observações da IA
            $table->string('ai_model')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'eaten_at']);
        });

        Schema::create('weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'measured_on']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('performed_on');
            $table->string('activity', 60);
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedInteger('calories_burned')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'performed_on']);
        });

        Schema::create('water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('logged_at');
            $table->unsignedSmallInteger('amount_ml');
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });

        // Registro (log) de eventos: cada refeição, pesagem, atividade, água etc.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('water_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('weight_logs');
        Schema::dropIfExists('meals');
    }
};
