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
        Schema::create('cursos', function (Blueprint $table) {
        $table->id();
        $table->string('titulo');
        $table->text('descripcion')->nullable();
        $table->string('area');
        $table->enum('modalidad', ['online', 'presencial', 'mixta']);
        $table->string('provincia')->nullable();
        $table->string('ciudad')->nullable();
        $table->enum('nivel', ['basico', 'medio', 'avanzado']);
        $table->string('situacion_permitida');
        $table->text('requisitos')->nullable();
        $table->string('enlace')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
