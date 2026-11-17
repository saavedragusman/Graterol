<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-09: lote de entrada, kg de entrada y carnicero. Estado
        // open -> closed | voided (C12: cerrada no se edita, se anula).
        Schema::create('butchery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('batch_id')->constrained('batches');
            $table->foreignId('template_id')->constrained('butchery_templates');
            $table->decimal('input_weight_kg', 12, 3);
            $table->decimal('expected_shrinkage_percent', 5, 2);
            $table->foreignId('butcher_id')->constrained('users');
            $table->string('status', 20)->default('open');
            $table->decimal('actual_shrinkage_kg', 12, 3)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butchery_orders');
    }
};
