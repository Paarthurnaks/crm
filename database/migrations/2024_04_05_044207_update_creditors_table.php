<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Ramsey\Uuid\v1;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('creditors', function (Blueprint $table) {
            $table->string("organization_inn")->nullable();
            $table->string("organization_short_name")->nullable();
            $table->string("organization_full_name")->nullable();
            $table->string("organization_ogrn")->nullable();

            $table->string("bank_title")->nullable();
            $table->string("bank_name")->nullable();
            $table->string("bank_bik")->nullable();
            $table->string("bank_account")->nullable();
            $table->string("bank_correspondent_account")->nullable();
            $table->string("bank_valute")->nullable();
            $table->string("bank_address")->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditors', function (Blueprint $table) {
            $table->dropColumn("organization_inn");
            $table->dropColumn("organization_short_name");
            $table->dropColumn("organization_full_name");
            $table->dropColumn("organization_ogrn");
            $table->dropColumn("bank_title");
            $table->dropColumn("bank_name");
            $table->dropColumn("bank_bik");
            $table->dropColumn("bank_account");
            $table->dropColumn("bank_correspondent_account");
            $table->dropColumn("bank_valute");
            $table->dropColumn("bank_address");
        });
    }
};
