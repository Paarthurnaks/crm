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
        Schema::table('role_fields', function (Blueprint $table) {
            $table->string("type")->default("view");
        });

        Schema::table('role_user_fields', function (Blueprint $table) {
            $table->string("type")->default("view");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {   
        Schema::table('role_fields', function (Blueprint $table) {
            $table->dropColumn("type");
        });

        Schema::table('role_user_fields', function (Blueprint $table) {
            $table->dropColumn("type");
        });
    }
};
