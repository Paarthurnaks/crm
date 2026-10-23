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
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_fields', function (Blueprint $table) {
            $table->dropForeign(['field_id']);
            $table->dropForeign(['user_field_id']);

            $table->dropColumn(['field_id', 'user_field_id']);
        });

        Schema::table('report_fields', function (Blueprint $table) {
            $table->foreignIdFor(Field::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(UserField::class)->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_fields', function (Blueprint $table) {
            $table->dropForeign(['field_id']);
            $table->dropForeign(['user_field_id']);

            $table->dropColumn(['field_id', 'user_field_id']);
        });

        Schema::table('report_fields', function (Blueprint $table) {
            $table->foreignIdFor(Field::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(UserField::class)->nullable()->constrained()->nullOnDelete();
        });
    }
};
