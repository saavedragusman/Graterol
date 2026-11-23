<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-16/DEC-07: sesión de conteo físico; draft → confirmed | cancelled.
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('status', 20);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            // UTC (C13).
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
