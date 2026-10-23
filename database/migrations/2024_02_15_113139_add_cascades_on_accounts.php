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
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['author_id']);
            $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['author_id']);
            $table->foreign('deal_id')->references('id')->on('deals');
            $table->foreign('author_id')->references('id')->on('users');
        });
    }
};
