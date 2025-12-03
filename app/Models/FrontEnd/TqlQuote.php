<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class TqlQuote extends Model
{
    
    protected $fillable = [
        'user_id', 'pick_location_type', 'drop_location_type', 'shipment_date',
        'origin', 'destination', 'pickup_details', 'delivery_details', 'accessorials',
        'created_date', 'tendered_date', 'po_number', 'expiration_date'
    ];

    public function tqlcommodities()
    {
        return $this->hasMany(TqlCommodity::class);
    }

    public function tqlcarrierPrices()
    {
        return $this->hasMany(TqlCarrierPrice::class);
    }
}
