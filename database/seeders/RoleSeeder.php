<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$roles = [
			'Super Admin',
			'Admin',
			'Ecommerce Manager',
			'Ecommerce Specialist',
			'Content Writing Manager',
			'Content Writer',
			'SEO Manager',
			'SEO Specialist',
			'Marketing Manager',
			'Graphic Designer Manager',
			'Graphic Designer',
			'Finance Department',
		];

		$validRoleIds = [];
		foreach ($roles as $role) {
			$record = Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
			array_push($validRoleIds, $record->id);
		}
		Role::whereNotIn('id', $validRoleIds)->delete();

		/**
		 * Give the permissions to roles
		 */
		Role::where('name', 'Super Admin')->first()?->syncPermissions(Permission::all());

		Role::where('name', 'Admin')->first()?->syncPermissions([
			'list attribute',
			'add attribute',
			'update attribute',
			'delete attribute',
			'show attribute',
			'import attribute',
			'export attribute',
		]);

		Role::where('name', 'Ecommerce Manager')->first()?->syncPermissions([

		]);
		Role::where('name', 'Ecommerce Specialist')->first()?->syncPermissions([

		]);
		Role::where('name', 'Content Writing Manager')->first()?->syncPermissions([

		]);
		Role::where('name', 'Content Writer')->first()?->syncPermissions([

		]);
		Role::where('name', 'SEO Manager')->first()?->syncPermissions([

		]);
		Role::where('name', 'SEO Specialist')->first()?->syncPermissions([

		]);
		Role::where('name', 'Marketing Manager')->first()?->syncPermissions([

		]);
		Role::where('name', 'Graphic Designer Manager')->first()?->syncPermissions([

		]);
		Role::where('name', 'Graphic Designer')->first()?->syncPermissions([

		]);
		Role::where('name', 'Finance Department')->first()?->syncPermissions([

		]);
	}
}
