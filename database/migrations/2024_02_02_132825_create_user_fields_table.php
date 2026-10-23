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
        Schema::create('user_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->comment('сущность');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')
                ->comment('возможные типы: integer, float, boolean, datetime,date,enum,file,user,deal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_fields');
    }
};
