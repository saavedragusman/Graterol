<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-06: peso facturado, recibido y su diferencia, siempre en kg
        // (DEC-06: un lote por línea, con el número de piezas adentro).
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('invoiced_weight_kg', 12, 3);
            $table->unsignedSmallInteger('invoiced_pieces')->nullable();
            $table->decimal('line_amount', 18, 4);
            $table->decimal('received_weight_kg', 12, 3)->nullable();
            $table->unsignedSmallInteger('received_pieces')->nullable();
            $table->decimal('weight_difference_kg', 12, 3)->nullable();
            $table->decimal('allocated_freight', 18, 4)->nullable();
            $table->decimal('allocated_other_costs', 18, 4)->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};
