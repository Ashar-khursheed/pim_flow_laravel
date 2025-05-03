<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$admin = User::where('username', 'admin')->first();
		$admin->syncRoles(['Super Admin']);
	}
}
