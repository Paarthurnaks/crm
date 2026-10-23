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
        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('responsible_id');
            $table->unsignedBigInteger('creator_id');
            $table->timestamps();

            $table->foreign('responsible_id')->references('id')->on('user_fields')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('user_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditors');
    }
};
