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
        Schema::table('forwarding_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['original_message_id']);
            $table->dropForeign(['forwarded_message_id']);

            $table->unsignedBigInteger('original_message_id')->nullable()->change();
            $table->unsignedBigInteger('forwarded_message_id')->nullable()->change();

            $table->foreign('original_message_id')
                ->references('id')->on('chat_messages')
                ->nullOnDelete();

            $table->foreign('forwarded_message_id')
                ->references('id')->on('chat_messages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forwarding_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['original_message_id']);
            $table->dropForeign(['forwarded_message_id']);

            $table->unsignedBigInteger('original_message_id')->nullable(false)->change();
            $table->unsignedBigInteger('forwarded_message_id')->nullable(false)->change();

            $table->foreign('original_message_id')
                ->references('id')->on('chat_messages');

            $table->foreign('forwarded_message_id')
                ->references('id')->on('chat_messages');
        });
    }
};
