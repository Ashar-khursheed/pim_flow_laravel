<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{

		$permissions = [
			'list attribute',
			'add attribute',
			'update attribute',
			'delete attribute',
			'show attribute',
			'import attribute',
			'export attribute',

			'list attribute group',
			'add attribute group',
			'update attribute group',
			'delete attribute group',
			'show attribute group',

			'list product family attribute group',
			'add product family attribute group',
			'update product family attribute group',
			'delete product family attribute group',
			'show product family attribute group',

			'list product',
			'add product',
			'update product',
			'delete product',
			'show product',

			'list category',
			'add category',
			'update category',
			'delete category',
			'show category',

			'list discount',
			'add discount',
			'update discount',
			'delete discount',
			'show discount',

			'list news letter',
			'add news letter',
			'update news letter',
			'delete news letter',
			'show news letter',

			'list brand',
			'add brand',
			'update brand',
			'delete brand',
			'show brand',

			'list role',
			'add role',
			'update role',
			'delete role',
			'show role',

			'list category page',
			'add category page',
			'update category page',
			'delete category page',
			'show category page',

			'list sub category page',
			'add sub category page',
			'update sub category page',
			'delete sub category page',
			'show sub category page',

			'list review',
			'add review',
			'update review',
			'delete review',
			'show review',

			'list faq',
			'add faq',
			'update faq',
			'delete faq',
			'show faq',

			'list faq category',
			'add faq category',
			'update faq category',
			'delete faq category',
			'show faq category',

			'list seo mgmt',
			'add seo mgmt',
			'update seo mgmt',
			'delete seo mgmt',
			'show seo mgmt',

			'list vendor',
			'add vendor',
			'update vendor',
			'delete vendor',
			'show vendor',

			'list media mgmt',
			'add media mgmt',
			'update media mgmt',
			'delete media mgmt',
			'show media mgmt',

			'list activity log',
			'add activity log',
			'update activity log',
			'delete activity log',
			'show activity log',

			'list user',
			'add user',
			'update user',
			'delete user',
			'show user',

			'list website',
			'add website',
			'update website',
			'delete website',
			'show website',

			'list report',
			'add report',
			'update report',
			'delete report',
			'show report',

			'list payment gateway screen',
			'add payment gateway screen',
			'update payment gateway screen',
			'delete payment gateway screen',
			'show payment gateway screen',

			'list order',
			'add order',
			'update order',
			'delete order',
			'show order',

			'list permission',
			'add permission',
			'update permission',
			'delete permission',
			'show permission',
		];

		$validPermissionIds = [];
		foreach ($permissions as $permission) {
			$record = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
			array_push($validPermissionIds, $record->id);
		}
		Permission::whereNotIn('id', $validPermissionIds)->delete();
	}
}
