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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('creator_id')->comment('Постановщик задачи');
            $table->unsignedBigInteger('deal_id')
                ->nullable()
                ->comment('Идентификатор привязанной сделки');
            $table->text('description')->nullable();
            $table->dateTime('deadline')
                ->nullable()
                ->comment('Крайний срок');
            $table->string('status')
                ->default('new')
                ->comment('стадия выполнения (new|pending|paused|closed)');
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('deals');
            $table->foreign('creator_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
