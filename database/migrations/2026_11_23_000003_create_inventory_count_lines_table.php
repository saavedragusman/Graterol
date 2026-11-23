<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-16/DEC-07: una línea por lote dentro de la sesión; pesos con 3 decimales (C9).
        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('inventory_counts');
            $table->foreignId('batch_id')->constrained('batches');
            // Saldo del sistema al momento de pesar.
            $table->decimal('system_quantity_kg', 12, 3)->nullable();
            // Peso contado; puede ser 0.
            $table->decimal('counted_weight_kg', 12, 3)->nullable();
            // counted − system, calculado al pesar.
            $table->decimal('difference_kg', 12, 3)->nullable();
            // Se llena al confirmar si la diferencia ≠ 0.
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('inventory_movements');
            // counted_by es quien teclea el peso.
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->timestampTz('counted_at')->nullable();
            // DEC-05 (1 B): quien pesó; obligatorio al registrar el peso (lo exige la Action).
            $table->foreignId('responsible_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['count_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
    }
};
