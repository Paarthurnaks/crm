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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id')->comment('id привязанной сделки');
            $table->float('sum')->default(0)->comment('сумма');
            $table->date('invoice_date')->comment('дата выставления счета');
            $table->unsignedBigInteger('author_id')->comment('кто выставил счет');
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('deals');
            $table->foreign('author_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
