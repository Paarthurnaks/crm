<?php

use App\Models\Field;
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
        Schema::table('section_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('section_fields', 'field_id')) {
                $table->foreignIdFor(Field::class)->nullable();
            }
            if (!Schema::hasColumn('section_fields', 'user_field_id')) {
                $table->foreignIdFor(UserField::class)->nullable();
            }

            $table->dropForeign(['field_id']);
            $table->dropForeign(['user_field_id']);

            $table->foreign('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->foreign('user_field_id')->references('id')->on('user_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('section_fields', function (Blueprint $table) {
            $table->dropForeign(['field_id']);
            $table->dropForeign(['user_field_id']);

            $table->foreign('field_id')->references('id')->on('fields')->onDelete('set null');
            $table->foreign('user_field_id')->references('id')->on('user_fields')->onDelete('set null');
        });
    }
};
