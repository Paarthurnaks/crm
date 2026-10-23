<?php

use App\Models\Field;
use App\Models\Report;
use App\Models\UserField;
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
        Schema::create('report_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Report::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Field::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(UserField::class)->nullable()->constrained()->nullOnDelete();
            $table->integer("position")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_fields');
    }
};
