<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingRule extends Model
{
    use HasFactory;

    // Specify the table name if it's not plural of the model name
    protected $table = 'grading_rules';

    // Define the fillable attributes for mass assignment
    protected $fillable = [
        'grade',
        'min_percentage',
        'max_percentage',
        'product_id'
    ];
}
