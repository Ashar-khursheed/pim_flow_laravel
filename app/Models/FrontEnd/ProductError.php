<?php

namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductError extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'product_id',
        'problem',
        'assign_to',
        'timestamp',
        'email',
        'created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(Customer::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(Customer::class, 'updated_by');
    }
}