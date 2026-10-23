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
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(["sales_funnel_id"]);
            $table->dropForeign(["sales_stage_id"]);
            $table->foreign("sales_funnel_id")->references("id")->on("sales_funnels")->cascadeOnDelete();
            $table->foreign("sales_stage_id")->references("id")->on("sales_stages")->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {

        });
    }
};
