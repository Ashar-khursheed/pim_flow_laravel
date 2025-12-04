<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class TqlCarrierPrice extends Model
{
    protected $fillable = [
        'quote_id', 'carrier', 'scac', 'customer_rate', 'carrier_quote_id',
        'service_level', 'service_type', 'transit_days', 'max_liability_new',
        'max_liability_used', 'service_level_description', 'price_charges',
        'is_preferred', 'is_economy'
    ];

    
    public function tqlquote()
    {
        return $this->belongsTo(TqlQuote::class);
    }
}
