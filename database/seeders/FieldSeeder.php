<?php

namespace Database\Seeders;

use App\Enums\DefaultRolesEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Models\Field;
use App\Models\Role;
use App\Models\RoleField;
use Database\Factories\RoleFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->dealFieldsSeed();
        $this->taskFieldSeed();
        $this->userFieldSeed();
        $this->documentTemplateFieldSeed();
    }

    private function dealFieldsSeed(): void
    {
        $codes = ["title", "sales_stage", "sales_funnel", "creator", "responsible", "note", "amount", "client", "created_at", "updated_at"];
        $names = ["Заголовок", "Стадия", "Воронка", "Создатель", "Ответственный", "Комментарий", "Сумма", "Клиент", "Дата создания", "Дата обновления"];
        $types = ["string", "stage", "funnel", "user", "user", "string", "float", "user", "datetime", "datetime"];
        $is_required = [true, false, true, false, true, true, true, true, false, false];

        for ($i = 0; $i < count($codes); $i++)
        {
            Field::create([
                "entity" => SectionFieldEntityEnum::DEALS->value,
                "code" => $codes[$i],
                "name" => $names[$i],
                "type" => $types[$i],
                "is_required" => $is_required[$i]
            ]);
        }
    }

    private function taskFieldSeed(): void
    {
        $codes = ["title", "description", "status", "deadline", "type", "deal", "creator", "created_at", "updated_at"];
        $names = ["Заголовок", "Описание", "Статус", "Крайний срок", "Тип", "Сделка", "Постановщик", "Дата создания", "Дата обновления"];
        $types = ["string", "string", "enum", "datetime", "enum", "deal", "user", "datetime", "datetime"];
        $is_required = [true, true, true, true, true, true, false, false, false];

        for ($i = 0; $i < count($codes); $i++)
        {
            $field = Field::create([
                "entity" => SectionFieldEntityEnum::TASKS->value,
                "code" => $codes[$i],
                "name" => $names[$i],
                "type" => $types[$i],
                "is_required" => $is_required[$i]
            ]);

            $roleFieldTypes = ["view", "create", "update"];
            $partnerRoleId = Role::where('original_title', DefaultRolesEnum::PARTNER->value)->value('id');

            $prohibited = ["creator", "created_at", "updated_at"];

            foreach ($roleFieldTypes as $roleFieldType) {
                if(in_array($field->code, $prohibited) && $roleFieldType !== "view") continue;

                RoleField::create([
                    'role_id' => $partnerRoleId,
                    'field_id' => $field->id,
                    'type' => $roleFieldType,
                ]);
            }
        }
    }

    private function userFieldSeed(): void
    {
        $codes = ["name", "surname", "email", "role", "active", "last_login", "created_at", "updated_at"];
        $names = ["Имя", "Фамилия", "Почта", "Роль", "Активный", "Последний вход", "Дата создания", "Дата обновления"];
        $types = ["string", "string", "string", "role", "boolean", "datetime", "datetime", "datetime"];
        $is_required = [true, true, true, true, false, false, false, false];

        for ($i = 0; $i < count($codes); $i++)
        {
            Field::create([
                "entity" => SectionFieldEntityEnum::USERS->value,
                "code" => $codes[$i],
                "name" => $names[$i],
                "type" => $types[$i],
                "is_required" => $is_required[$i]
            ]);
        }
    }

    private function documentTemplateFieldSeed(): void
    {
        $codes = ["entity", "title", "creator", "created_at", "updated_at"];
        $names = ["Сущность", "Название", "Создатель", "Дата создания", "Дата обновления"];
        $types = ["string", "string", "user", "datetime", "datetime"];
        $is_required = [true, true, false, false, false, false];

        for ($i = 0; $i < count($codes); $i++)
        {
            Field::create([
                "entity" => SectionFieldEntityEnum::TEMPLATES->value,
                "code" => $codes[$i],
                "name" => $names[$i],
                "type" => $types[$i],
                "is_required" => $is_required[$i]
            ]);
        }
    }
}
