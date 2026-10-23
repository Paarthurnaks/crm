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
        Schema::table('sales_tunnels', function (Blueprint $table) {
            $table->dropForeign(["from_stage_id"]);
            $table->foreign('from_stage_id')->references("id")->on('sales_stages')->onDelete("cascade");
            $table->dropForeign(["to_stage_id"]);
            $table->foreign('to_stage_id')->references("id")->on('sales_stages')->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_tunnels', function (Blueprint $table) {
            $table->dropForeign(["from_stage_id"]);
            $table->foreign('from_stage_id')->references("id")->on('sales_stages');
            $table->dropForeign(["to_stage_id"]);
            $table->foreign('to_stage_id')->references("id")->on('sales_stages');
        });
    }
};
