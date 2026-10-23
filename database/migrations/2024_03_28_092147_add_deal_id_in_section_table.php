<?php

use App\Models\Deal;
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
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignIdFor(Deal::class)->nullable()->constrained()->cascadeOnDelete();
            $table->boolean("is_template")->default(false);
            $table->boolean("main_section")->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeignIdFor(Deal::class);
            $table->dropColumn("is_template");
            $table->dropColumn("main_section");
        });
    }
};
