<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $guard_name = 'api';
	// public function user()
	// {
	// 	return $this->hasMany('App\Models\Permission');
	// }
}
