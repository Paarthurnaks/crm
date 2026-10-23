<?php

use App\Models\SalesFunnel;
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
        Schema::create('sales_funnels', function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->timestamps();
        });

        SalesFunnel::factory()->create([
            "title" => "common"
        ]);


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_funnels');
    }
};
