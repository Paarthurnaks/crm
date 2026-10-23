<?php

use App\Models\SalesFunnel;
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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string("entity");
            $table->string("title");
            $table->foreignIdFor(User::class)->cascadeOnDelete()->constrained();
            $table->foreignIdFor(SalesFunnel::class)->nullable()->cascadeOnDelete()->constrained();
            $table->integer("position");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
