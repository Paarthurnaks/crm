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
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('service')->nullable();
            $table->string('client_full_name')->nullable();
            $table->unsignedBigInteger('docket_number')->nullable();
            $table->string('email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('service');
            $table->dropColumn('client_full_name');
            $table->dropColumn('docket_number');
            $table->dropColumn('email');
        });
    }
};
