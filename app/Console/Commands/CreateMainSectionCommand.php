<?php

namespace App\Console\Commands;

use App\Enums\EntityTypeEnum;
use App\Models\Field;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateMainSectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-main-sections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать основные разделы для пользователей';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->createMainSection();

        return self::SUCCESS;
    }

    private function createMainSection(): void
    {
        $fieldsByEntity = Field::query()->where('is_required', true)->get()->groupBy('entity');
        $entities = array_column(EntityTypeEnum::cases(), 'value');
        $processed = 0;

        foreach (User::query()->select('id')->lazyById(200) as $user) {
            try {
                foreach ($entities as $entity) {
                    $section = Section::firstOrCreate([
                        'user_id' => $user->id,
                        'entity' => $entity,
                        'is_template' => true,
                        'main_section' => true,
                    ], [
                        'title' => 'Общее',
                        'position' => 1,
                    ]);

                    foreach ($fieldsByEntity->get($entity, collect())->values() as $position => $field) {
                        SectionField::firstOrCreate([
                            'section_id' => $section->id,
                            'field_id' => $field->id,
                        ], ['position' => $position + 1]);
                    }
                }
                $processed++;
                if ($processed % 200 === 0) {
                    Log::info('Main section command progress', ['processed_users' => $processed]);
                }
            } catch (\Throwable $e) {
                Log::error('Main section command failed', ['user_id' => $user->id, 'exception_class' => get_class($e)]);
                throw $e;
            }
        }

        Log::info('Main section command completed', ['processed_users' => $processed]);
    }
}
