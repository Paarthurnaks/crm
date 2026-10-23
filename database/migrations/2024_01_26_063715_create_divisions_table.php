<?php

use App\Models\Division;
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
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('divisions')
                ->cascadeOnDelete();
            $table->timestamps();
        });

        Division::factory()->create([
            'title' => 'common',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignIdFor(Division::class)
                ->default(Division::COMMON)
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Division::class);
        });
        Schema::dropIfExists('divisions');
    }
};
