<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;



/**
 * @OA\Schema(
 *     schema="Faq",
 *     title="FAQ",
 *     description="FAQ model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="question", type="string", example="What is Laravel?"),
 *     @OA\Property(property="answer", type="string", example="Laravel is a PHP framework."),
 *     @OA\Property(property="faq_category_id", type="integer", example=2),
 *     @OA\Property(property="faq_product_id", type="integer", example=2),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Faq extends Model implements TranslatableContract
{
	use Translatable;

	// public $translatedAttributes = [
	// 	'question_tr',
	// 	'answer_tr',
	// ];

	public $translatedAttributes = [];
	protected $fillable = ['relational_id', 'relational_type', 'question', 'answer', 'status'];

	protected static function booted()
	{
		static::deleting(function ($faq) {
			$faq->translations()->delete();
		});
	}
}

// 	public function category()
// 	{
// 		return $this->belongsTo(FaqCategory::class, 'category_id');
// 	}

// 	public function product()
// 	{
// 		return $this->belongsTo(Product::class, 'product_id');
// 	}
// }

