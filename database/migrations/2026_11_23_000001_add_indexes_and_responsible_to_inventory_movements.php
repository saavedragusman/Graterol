<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El kardex es de solo inserción (C10): solo se agregan índices y una columna nullable;
        // nunca se hace UPDATE sobre filas existentes (el trigger lo rechaza), así que quedan en NULL.
        Schema::table('inventory_movements', function (Blueprint $table) {
            // PostgreSQL no crea índice para la FK; el saldo es Σ por lote (BR-14).
            $table->index('batch_id');
            $table->index('type');

            // DEC-05 (1 B): quien pesó o responde por la merma; user_id sigue siendo quien registra.
            $table->foreignId('responsible_user_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropIndex(['type']);
            $table->dropIndex(['batch_id']);
        });
    }
};
