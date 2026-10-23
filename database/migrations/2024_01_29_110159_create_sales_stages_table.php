<?php

use App\Models\SalesFunnel;
use App\Models\SalesStage;
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
        Schema::create('sales_stages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color');
            $table->integer('position');
            $table->string('type');
            $table->foreignIdFor(SalesFunnel::class)->constrained();
            $table->timestamps();
        });

        SalesStage::factory()->create([
            'title' => 'common',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_stages');
    }
};
