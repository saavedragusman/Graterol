<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('price_list', 20);
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('VES');
            $table->timestamps();

            $table->unique(['product_id', 'price_list']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
