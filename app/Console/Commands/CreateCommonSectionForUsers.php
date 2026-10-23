<?php

namespace App\Console\Commands;

use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        $sales_funnels = SalesFunnel::all();

        foreach($users as $user)
        {
            foreach ($sales_funnels as $sales_funnel) 
            {
                $section = Section::create([
                    "entity" => "deals",
                    "user_id" => $user->id,
                    "title" => "Общее",
                    "sales_funnel_id" => $sales_funnel->id,
                    "position" => 1
                ]);

                $fieldIds = [1, 2, 3, 4, 5, 6, 7, 8];

                for($i = 0; $i < count($fieldIds); $i++)
                {
                    SectionField::create([
                        "section_id" => $section->id,
                        "field_id" => $fieldIds[$i],
                        "position" => $i+1
                    ]);
                }
            }
        }
    }
}
