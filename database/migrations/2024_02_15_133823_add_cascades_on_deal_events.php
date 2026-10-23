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
        Schema::table('deal_events', function (Blueprint $table) {
            $table->dropForeign(["deal_id"]);
            $table->foreign('deal_id')->references("id")->on('deals')->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deal_events', function (Blueprint $table) {
            $table->dropForeign(["deal_id"]);
            $table->foreign('deal_id')->references("id")->on('deals');
        });
    }
};
