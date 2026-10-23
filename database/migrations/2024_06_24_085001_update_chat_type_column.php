<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->string('type_tmp')->nullable();
        });

        DB::statement('UPDATE chats SET type_tmp = type');

        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('type_tmp', 'type');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->enum('type_tmp', ['single', 'group'])->nullable();
        });

        DB::statement('UPDATE chats SET type_tmp = type');

        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('type_tmp', 'type');
        });
    }
};
