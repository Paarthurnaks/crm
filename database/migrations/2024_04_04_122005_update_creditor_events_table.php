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
        Schema::table('creditor_events', function (Blueprint $table) {
        $table->dropForeign(["creditor_id"]);
        $table->foreign("creditor_id")
                    ->references("id")
                    ->on("creditors")
                    ->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
