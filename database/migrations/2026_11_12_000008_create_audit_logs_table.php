<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C15: auditoría inmutable de anulaciones, devoluciones, ajustes de
        // inventario, cambios de precio o tasa, cierres de caja y permisos.
        // Tabla de solo inserción: nunca se actualiza ni se borra una fila.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
