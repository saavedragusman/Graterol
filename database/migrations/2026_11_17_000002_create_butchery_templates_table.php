<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-08: cada materia prima tiene una plantilla (DEC-02); el % de
        // los cortes se guarda en butchery_template_lines y debe sumar,
        // junto con expected_shrinkage_percent, exactamente 100 (validado
        // en la Action, C3, no en la columna).
        Schema::create('butchery_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_product_id')->constrained('products');
            $table->string('name', 150);
            $table->decimal('expected_shrinkage_percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // DEC-02: solo una plantilla activa por materia prima a la vez.
        DB::statement(
            'CREATE UNIQUE INDEX butchery_templates_one_active_per_raw_material '.
            'ON butchery_templates (raw_material_product_id) WHERE is_active = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('butchery_templates');
    }
};
