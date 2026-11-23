<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-08: un corte (producto tipo derived) con su % estándar, por plantilla.
        Schema::create('butchery_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('butchery_templates')->cascadeOnDelete();
            $table->foreignId('cut_product_id')->constrained('products');
            $table->decimal('expected_yield_percent', 5, 2);
            $table->timestamps();

            $table->unique(['template_id', 'cut_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butchery_template_lines');
    }
};
