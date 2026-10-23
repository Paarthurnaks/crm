<?php

use App\Models\User;
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
        Schema::create('disk_entities', function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->foreignId("who_created_id")->nullable()->constrained("users")->nullOnDelete();
            $table->foreignId("who_changed_id")->nullable()->constrained("users")->nullOnDelete();
            $table->unsignedBigInteger("size")->nullable();
            $table->string("type");
            $table->boolean("deleted")->default(false);
            $table->string("path")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disk_entities');
    }
};
