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
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['responsible_id']);

            $table->foreign('creator_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('responsible_id')->nullable()->change();

            // Добавляем новый внешний ключ с onDelete('set null')
            $table->foreign('responsible_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['responsible_id']);

            $table->foreign('creator_id')
                ->references('id')
                ->on('users');

            $table->foreign('responsible_id')
                ->references('id')
                ->on('users');
        });
    }
};
