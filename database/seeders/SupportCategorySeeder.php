<?php
// database/seeders/SupportCategorySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FrontEnd\SupportCategory;

class SupportCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = ['Technical Support', 'Billing', 'Account Management', 'Product Inquiry', 'Feedback'];

        foreach ($categories as $name) {
            SupportCategory::create(['name' => $name]);
        }
    }
}
