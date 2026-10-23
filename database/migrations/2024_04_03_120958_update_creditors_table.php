<?php

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
        Schema::table('creditors', function (Blueprint $table) {
            $table->string("surname")->nullable();
            $table->string("pathronymic")->nullable();
            $table->date("birthdate")->nullable();
            $table->string("birthplace")->nullable();
            $table->string("registration")->nullable();
            $table->string("registration_index")->nullable();
            $table->string("snils")->nullable();
            $table->string("inn")->nullable();
            $table->string("passport_issued_by")->nullable();
            $table->string("passport_series")->nullable();
            $table->string("passport_number")->nullable();
            $table->string("passport_issued_date")->nullable();
            $table->string("departament_code")->nullable();
            $table->string("account_number")->nullable();
            $table->string("case_number")->nullable();
            $table->datetime("instance_start_date")->nullable();
            $table->datetime("publication_at")->nullable();

            $table->foreignId("who_changed_id")->nullable()->constrained("users")->nullOnDelete();
            $table->string("props")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditors', function (Blueprint $table) {
            $table->dropColumn("surname")->nullable();
            $table->dropColumn("pathronymic")->nullable();
            $table->dropColumn("birthdate");
            $table->dropColumn("birthplace");
            $table->dropColumn("registration");
            $table->dropColumn("registration_index");
            $table->dropColumn("snils");
            $table->dropColumn("inn");
            $table->dropColumn("passport_issued_by");
            $table->dropColumn("passport_series");
            $table->dropColumn("passport_number");
            $table->dropColumn("passport_issued_date");
            $table->dropColumn("departament_code");
            $table->dropColumn("account_number");
            $table->dropColumn("case_number");
            $table->dropColumn("instance_start_date");
            $table->dropColumn("publication_at");

            $table->dropForeign(["who_changed_id"]);
            $table->dropColumn("who_changed_id");
            $table->dropColumn("props");
        });
    }
};
