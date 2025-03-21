<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeGroupAttribute extends Model
{
	protected $fillable = ['attribute_group_id', 'attribute_id'];
}
