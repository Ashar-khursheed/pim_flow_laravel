<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryAttributeGroup extends Pivot
{
	protected $fillable = ['category_id', 'attribute_group_id'];

}
