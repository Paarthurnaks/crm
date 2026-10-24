<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Восстановить таблицу, удалённую миграцией 2024-07-09. Старые записи
     * можно вернуть только из резервной копии базы данных.
     */
    public function up(): void
    {
        Log::debug('Начато восстановление таблицы partner_client_deals');

        try {
            if (Schema::hasTable('partner_client_deals')) {
                throw new RuntimeException('Таблица partner_client_deals уже существует; проверьте её схему перед ручной отметкой миграции.');
            }

            Schema::create('partner_client_deals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });

            Log::info('Таблица partner_client_deals восстановлена', ['row_count' => 0]);
        } catch (Throwable $exception) {
            Log::error('Ошибка восстановления таблицы partner_client_deals', [
                'error_type' => get_class($exception),
            ]);

            throw $exception;
        }
    }

    public function down(): void
    {
        Log::debug('Начат откат восстановления таблицы partner_client_deals');

        try {
            if (! Schema::hasTable('partner_client_deals')) {
                Log::info('Таблица partner_client_deals уже отсутствует', ['row_count' => 0]);

                return;
            }

            $rowCount = DB::table('partner_client_deals')->count();

            if ($rowCount > 0) {
                throw new RuntimeException('Нельзя откатить восстановление partner_client_deals, пока в таблице есть записи.');
            }

            Schema::drop('partner_client_deals');

            Log::info('Восстановление таблицы partner_client_deals отменено', ['row_count' => $rowCount]);
        } catch (Throwable $exception) {
            Log::error('Ошибка отката восстановления таблицы partner_client_deals', [
                'error_type' => get_class($exception),
            ]);

            throw $exception;
        }
    }
};
