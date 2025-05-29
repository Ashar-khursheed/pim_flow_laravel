<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::table('attributes', function (Blueprint $table) {
			if (!Schema::hasColumn('attributes', 'attribute_group_id')) {
				$table->integer('attribute_group_id')->nullable()->after('type');
			}

			if (!Schema::hasColumn('attributes', 'created_by')) {
				$table->integer('created_by')->default(1)->after('validations');
			}

			if (!Schema::hasColumn('attributes', 'updated_by')) {
				$table->integer('updated_by')->nullable()->after('created_by');
			}
		});

		Schema::table('attribute_groups', function (Blueprint $table) {
			if (!Schema::hasColumn('attribute_groups', 'created_by')) {
				$table->integer('created_by')->default(1)->after('name');
			}

			if (!Schema::hasColumn('attribute_groups', 'updated_by')) {
				$table->integer('updated_by')->nullable()->after('created_by');
			}
		});

		/* update attributes with the group ID from the pivot table */
		DB::table('attribute_group_attributes')
		->select('attribute_id', 'attribute_group_id')
		->groupBy('attribute_id', 'attribute_group_id')
		->get()
		->each(function ($row) {
			// Update the attribute with the associated group ID
			DB::table('attributes')
			->where('id', $row->attribute_id)
			->update(['attribute_group_id' => $row->attribute_group_id]);
		});

		Schema::create('category_attribute_groups', function (Blueprint $table) {
			$table->id();
			$table->integer('category_id')->index();
			$table->integer('attribute_group_id')->index();
		});

		/* Insert into category_attribute_groups from attribute_group_categories */
		DB::table('attribute_group_categories')
		->where('relational_type', 'App\Models\AttributeGroup')
		->get()
		->each(function ($row) {
			DB::table('category_attribute_groups')->insert([
				'category_id' => $row->category_id,
				'attribute_group_id' => $row->relational_id,
			]);
		});

		Schema::dropIfExists('attribute_group_attributes');
		Schema::dropIfExists('attribute_group_categories');
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('attributes', function (Blueprint $table) {
			if (Schema::hasColumn('attributes', 'attribute_group_id')) {
				$table->dropColumn('attribute_group_id');
			}
			if (Schema::hasColumn('attributes', 'created_by')) {
				$table->dropColumn('created_by');
			}
			if (Schema::hasColumn('attributes', 'updated_by')) {
				$table->dropColumn('updated_by');
			}
		});

		Schema::table('attribute_groups', function (Blueprint $table) {
			if (Schema::hasColumn('attribute_groups', 'created_by')) {
				$table->dropColumn('created_by');
			}
			if (Schema::hasColumn('attribute_groups', 'updated_by')) {
				$table->dropColumn('updated_by');
			}
		});

		Schema::dropIfExists('category_attribute_groups');

		Schema::create('attribute_group_attributes', function (Blueprint $table) {
			$table->id();
			$table->integer('attribute_group_id');
			$table->integer('attribute_id');
			$table->timestamps();
		});

		Schema::create('attribute_group_categories', function (Blueprint $table) {
			$table->id();
			$table->integer('category_id');
			$table->integer('relational_id')->index();
			$table->string('relational_type')->index();
			$table->timestamps();
		});
	}
};
