<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id');
            $table->string('nombre');
            $table->timestamps();

            $table->foreign('comunidad_id')
                ->references('id')
                ->on('comunidades')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provincias');
    }
};