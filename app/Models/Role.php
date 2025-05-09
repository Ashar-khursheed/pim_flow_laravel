<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $guard_name = 'api';
	/**
	 * Override the users() method to match the signature in Spatie's Role model.
	 */
	// public function users(): BelongsToMany
	// {
	// 	return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
	// }
}
