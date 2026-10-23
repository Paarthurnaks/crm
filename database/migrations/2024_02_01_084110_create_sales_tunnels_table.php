<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_tunnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_stage_id')->constrained('sales_stages');
            $table->foreignId('to_stage_id')->constrained('sales_stages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_tunnels');
    }
};
