<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->string('type', 30);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->foreignId('user_id')->constrained('users');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION inventory_movements_block_update_delete()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'inventory_movements es de solo inserción: use un movimiento inverso';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_inventory_movements_immutable ON inventory_movements');

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_inventory_movements_immutable
            BEFORE UPDATE OR DELETE ON inventory_movements
            FOR EACH ROW EXECUTE FUNCTION inventory_movements_block_update_delete();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_inventory_movements_immutable ON inventory_movements');
        DB::statement('DROP FUNCTION IF EXISTS inventory_movements_block_update_delete');
        Schema::dropIfExists('inventory_movements');
    }
};
