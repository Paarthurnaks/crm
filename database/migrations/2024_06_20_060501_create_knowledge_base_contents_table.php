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
        Schema::create('knowledge_base_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\KnowledgeBase::class)->constrained()->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->string('type');
            $table->integer('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_contents');
    }
};
