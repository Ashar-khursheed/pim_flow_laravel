<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="FaqCategory",
 *     title="FAQ Category",
 *     description="FAQ Category model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="General"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class FaqCategory extends Model
{
	use HasFactory;

	protected $fillable = ['name', 'order', 'status', 'description'];

	public function faqs()
	{
		return $this->morphMany(Faq::class, 'relational');
	}
}
