<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-09 a BR-12: % esperado congelado al abrir (DEC-03), peso real,
        // precio de referencia y costo asignado al cerrar, y desviación (BR-11).
        Schema::create('butchery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('butchery_orders')->cascadeOnDelete();
            $table->foreignId('cut_product_id')->constrained('products');
            $table->decimal('expected_yield_percent', 5, 2);
            $table->decimal('expected_weight_kg', 12, 3);
            $table->decimal('actual_weight_kg', 12, 3)->nullable();
            $table->decimal('reference_price_per_kg', 18, 4)->nullable();
            $table->decimal('allocated_cost', 18, 4)->nullable();
            $table->decimal('deviation_percent', 6, 2)->nullable();
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'cut_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butchery_order_lines');
    }
};
