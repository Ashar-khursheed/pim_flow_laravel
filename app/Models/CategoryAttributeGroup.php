<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAttributeGroup extends Model
{
	protected $fillable = ['category_id', 'attribute_group_id', 'created_by'];
}
