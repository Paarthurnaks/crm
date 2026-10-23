<?php

namespace Database\Seeders;

use App\Enums\ChatCategoryEnum;
use App\Models\ChatCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ChatCategoryEnum::cases();

        foreach ($categories as $category) {
            ChatCategory::updateOrCreate(
                ['name' => $category->value],
                ['name' => $category->value]
            );
        }
    }
}
