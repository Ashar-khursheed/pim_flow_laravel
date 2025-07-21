<?php
// database/seeders/SupportPrioritySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FrontEnd\SupportPriority;

class SupportPrioritySeeder extends Seeder
{
    public function run()
    {
        $priorities = [
            ['name' => 'Low', 'level' => 1],
            ['name' => 'Medium', 'level' => 2],
            ['name' => 'High', 'level' => 3],
        ];

        foreach ($priorities as $data) {
            SupportPriority::create($data);
        }
    }
}
