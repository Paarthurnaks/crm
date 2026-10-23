<?php

use App\Models\SalesFunnel;
use App\Models\SalesStage;
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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignIdFor(SalesStage::class)->constrained();
            $table->foreignIdFor(SalesFunnel::class)->constrained();
            $table->foreignId('creator_id')->constrained('users');
            $table->foreignId('responsible_id')->constrained('users');
            $table->text('note');
            $table->float('amount')->default(0);
            $table->string('client')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
