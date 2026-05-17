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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Cambiamos los enums por strings cortos
            $table->string('type', 20); // pc, laptop, mobile, tablet, other

            $table->string('serial_number')->unique();

            // Cambiamos el enum por un string corto con valor por defecto
            $table->string('status', 20)->default('available'); // available, assigned, maintenance

            $table->timestamps();

            // Opcional, pero muy recomendado como Senior:
            // Agregamos Soft Deletes. Si un dispositivo se daña o se pierde, no borramos su historial.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
