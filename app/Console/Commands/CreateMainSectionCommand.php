<?php

namespace App\Console\Commands;

use App\Enums\EntityTypeEnum;
use App\Models\Field;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->createMainSection();
    }

    private function createMainSection()
    {
        $users = User::all();

        foreach ($users as $user) {
            $fields = Field::where('is_required', true)
                ->get();

            $enumsArr = EntityTypeEnum::cases();
            $entities = array_column($enumsArr, 'value');

            foreach ($entities as $entity) {
                $title = 'Общее';
                $section = Section::create([
                    'user_id' => $user->id,
                    'entity' => $entity,
                    'title' => $title,
                    'position' => 1,
                    'is_template' => true,
                    'main_section' => true,
                ]);

                $entityFields = array_values(array_filter($fields->toArray(), function ($field) use ($entity) {
                    return $field['entity'] == $entity;
                }));

                for ($i = 0; $i < count($entityFields); $i++) {
                    SectionField::create([
                        'section_id' => $section->id,
                        'field_id' => $entityFields[$i]['id'],
                        'position' => $i + 1,
                    ]);
                }
            }
        }
    }
}
