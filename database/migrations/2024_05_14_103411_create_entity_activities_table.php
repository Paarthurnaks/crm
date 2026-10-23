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
        Schema::create('entity_activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('activityable');
            $table->morphs('entity');
            $table->timestamps();

            $table->index(['activityable_type', 'activityable_id'], 'activityable_index');
            $table->index(['entity_type', 'entity_id'], 'entity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_activities');
    }
};
