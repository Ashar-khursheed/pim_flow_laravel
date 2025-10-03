<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use App\Models\AccessoryItem;

class AccessoryCharge extends Model
{
	public $timestamps = false;
	protected $fillable = ['accessory_item_id', 'amount', 'created_at'];

	public function relation()
	{
		return $this->morphTo();
	}

	public function accessoryItem()
	{
        return $this->belongsTo(AccessoryItem::class, 'accessory_item_id');
	}
}
