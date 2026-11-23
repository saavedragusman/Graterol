<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un lote es, antes que nada, una cantidad de un producto. M0/M2 no lo
        // necesitaban todavía; M3 (BR-08/BR-09) necesita saber el producto del
        // lote de entrada para resolver su plantilla de despiece.
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('code')->constrained('products');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
