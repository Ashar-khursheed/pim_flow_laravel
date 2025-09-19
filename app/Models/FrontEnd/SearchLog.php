<?php
// app/Models/SearchLog.php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $fillable = [
        'customer_id',
        'search_term',
        'product_id',
        'ip_address',
        'user_agent',
    ];
}
