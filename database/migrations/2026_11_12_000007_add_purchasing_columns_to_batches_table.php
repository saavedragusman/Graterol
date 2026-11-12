<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-07: el lote nace en la recepción con su costo total y, cuando la
        // compra fue por pieza (DEC-06), el número de piezas recibidas.
        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedSmallInteger('pieces')->nullable()->after('unit');
            $table->decimal('total_cost', 18, 4)->nullable()->after('unit_cost');
            $table->char('currency', 3)->default('VES')->after('total_cost');
            $table->decimal('exchange_rate', 18, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['pieces', 'total_cost', 'currency', 'exchange_rate']);
        });
    }
};
