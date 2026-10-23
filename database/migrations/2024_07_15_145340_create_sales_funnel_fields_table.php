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
        Schema::create('sales_funnel_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_funnel_id')->constrained('sales_funnels')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('fields')->cascadeOnDelete();
            $table->foreignId('user_field_id')->nullable()->constrained('user_fields')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_funnel_fields');
    }
};
