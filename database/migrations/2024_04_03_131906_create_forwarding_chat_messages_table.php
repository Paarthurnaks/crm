<?php

use App\Models\ChatMessage;
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
        Schema::create('forwarding_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_message_id');
            $table->unsignedBigInteger('forwarded_message_id');
            $table->foreign('original_message_id')->references('id')->on('chat_messages');
            $table->foreign('forwarded_message_id')->references('id')->on('chat_messages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forwarding_chat_messages');
    }
};
