<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAccessory extends Model
{
	use HasFactory;

	protected $table = 'product_accessories';

	protected $fillable = [
		'product_id',
		'name',
		'isapproved',
		'isRequired',
		'approved_by',
		'created_by',
		'updated_by'
	];


	protected $hidden = [];

	// Relationships
	public function product()
	{
		return $this->belongsTo(Product::class, 'product_id');
	}

	public function approvedBy()
	{
		return $this->belongsTo(User::class, 'approved_by');
	}

	public function createdBy()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updatedBy()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
	// Scopes
	public function scopeApproved($query)
	{
		return $query->where('isapproved', 1);
	}

	public function scopePending($query)
	{
		return $query->where('isapproved', 0);
	}

	public function scopeByProduct($query, $productId)
	{
		return $query->where('product_id', $productId);
	}

	// Mutators
	public function setNameAttribute($value)
	{
		$this->attributes['name'] = ucfirst(trim($value));
	}

	// Accessors
	public function getApprovalStatusAttribute()
	{
		return $this->isapproved ? 'Approved' : 'Pending';
	}

	public function items() {
		return $this->hasMany(AccessoryItem::class);
	}

	public function accessoryTypes()
	{
		return $this->hasMany(AccessoryItem::class, 'product_accessory_id');
	}
}
