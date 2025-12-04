<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class TqlCommodity extends Model
{
    protected $fillable = [
        'quote_id', 'description', 'quantity', 'weight', 'dimension_length',
        'dimension_width', 'dimension_height', 'is_hazmat', 'freight_class_code',
        'unit_type_code', 'nmfc', 'piece_case_count', 'is_stackable', 'hazmat_details'
    ];

    public function tqlQuote()
    {
        return $this->belongsTo(TqlQuote::class);
    }
}
