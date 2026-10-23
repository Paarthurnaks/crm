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
        Schema::create('creditor_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('creditor_id');
            $table->unsignedBigInteger('initiator_id')->nullable();

            $table->foreign('creditor_id')
                ->references('id')
                ->on('tasks')
                ->onDelete('cascade');

            $table->foreign('initiator_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->string('type');
            $table->string('changed_field')->nullable();
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditor_events');
    }
};
