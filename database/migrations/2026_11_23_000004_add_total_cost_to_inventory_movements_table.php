<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C9/C10: monto exacto del movimiento, con el mismo signo que `quantity` (entrada +, salida −).
        // Solo se agrega la columna nullable: el kardex es de solo inserción y el trigger rechaza
        // cualquier UPDATE, así que los movimientos existentes quedan en NULL (se valoran con
        // round(quantity × unit_cost) al centavo como respaldo).
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('total_cost', 18, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
