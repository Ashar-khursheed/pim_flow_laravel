<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as Permissions;

class Permission extends Permissions
{
	public function user()
	{
		return $this->hasMany('App\Models\Permission');
	}
}
