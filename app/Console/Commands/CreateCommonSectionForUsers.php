<?php

namespace App\Console\Commands;

use App\Models\Field;
use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateCommonSectionForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-common-section-for-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать общие разделы воронок для пользователей';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fieldIds = Field::query()->whereIn('id', [1, 2, 3, 4, 5, 6, 7, 8])->orderBy('id')->pluck('id');
        if ($fieldIds->count() !== 8) {
            Log::warning('Common section command has missing configured fields', ['available_fields' => $fieldIds->count()]);
        }
        $processed = 0;

        foreach (SalesFunnel::query()->select('id')->lazyById(200) as $salesFunnel) {
            foreach (User::query()->select('id')->lazyById(200) as $user) {
                try {
                    $section = Section::firstOrCreate([
                        'entity' => 'deals',
                        'user_id' => $user->id,
                        'title' => 'Общее',
                        'sales_funnel_id' => $salesFunnel->id,
                    ], ['position' => 1]);

                    foreach ($fieldIds->values() as $position => $fieldId) {
                        SectionField::firstOrCreate([
                            'section_id' => $section->id,
                            'field_id' => $fieldId,
                        ], ['position' => $position + 1]);
                    }
                    $processed++;
                    if ($processed % 200 === 0) {
                        Log::info('Common section command progress', ['processed_pairs' => $processed]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Common section command failed', ['user_id' => $user->id, 'funnel_id' => $salesFunnel->id, 'exception_class' => get_class($e)]);
                    throw $e;
                }
            }
        }

        Log::info('Common section command completed', ['processed_pairs' => $processed]);

        return self::SUCCESS;
    }
}
