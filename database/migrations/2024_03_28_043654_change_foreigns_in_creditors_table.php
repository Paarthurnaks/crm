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
        Schema::table('creditors', function (Blueprint $table) {
            $table->dropForeign(["responsible_id"]);
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
            $table->dropForeign(["creator_id"]);
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditors', function (Blueprint $table) {
            $table->dropForeign(["responsible_id"]);
            $table->foreign('responsible_id')->references('id')->on('user_fields')->onDelete('cascade');
            $table->dropForeign(["creator_id"]);
            $table->foreign('creator_id')->references('id')->on('user_fields')->onDelete('cascade');
        });
    }
};
