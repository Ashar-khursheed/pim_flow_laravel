<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\User;

class HorecaPage extends Model
{
	protected $table = 'horeca_pages';

	protected $fillable = [
		'name',
		'description',
		'link_name',
		'link_url',
		'banner_url',
		'left_para_description',
		'right_para_description',
		'faqs',
		'is_active',
		'created_by',
		'updated_by',
	];

	public function page_categories()
	{
		return $this->hasMany(HorecaPageProductType::class, 'horeca_page_id')->orderBy('order');
	}

	public function categories()
	{
		return $this->belongsToMany(Category::class, 'horeca_page_categories')
		->using(HorecaPageCategory::class)
		->withPivot('order')
		->where('categories.status', 'published')
		->orderBy('horeca_page_categories.order');
	}

	public function productTypes()
	{
		return $this->hasMany(HorecaPageProductType::class, 'horeca_page_id')->orderBy('order');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updator()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function scopeActive($query)
	{
		return $query->where('is_active', true);
	}
}