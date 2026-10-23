<?php

use App\Models\SalesFunnel;
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
            $table->dropForeign(["sales_funnel_id"]);
            $table->dropColumn("sales_funnel_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignIdFor(SalesFunnel::class)->nullable()->cascadeOnDelete()->constrained();
        });
    }
};
