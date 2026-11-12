<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-02/BR-06/BR-07: la compra nace en borrador (Filament) y se recibe
        // pesando en la PWA (DEC-01). Guarda la tasa BCV del día (C13).
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_invoice_number', 60);
            $table->date('supplier_invoice_date');
            $table->char('currency', 3)->default('VES');
            $table->decimal('exchange_rate', 18, 4);
            $table->decimal('freight_amount', 18, 4)->default(0);
            $table->decimal('other_costs_amount', 18, 4)->default(0);
            $table->string('other_costs_note')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
