<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Category;
/**
 * @OA\Schema(
 *     schema="Coupon",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="WELCOME10"),
 *     @OA\Property(property="value", type="number", example=50),
 *     @OA\Property(property="type", type="string", example="fixed"),
 *     @OA\Property(property="min_order_price", type="number", example=100),
 *     @OA\Property(property="quantity", type="integer", example=100),
 *     @OA\Property(property="total_used", type="integer", example=10),
 *     @OA\Property(property="expires_at", type="string", format="date", example="2025-12-31"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Discount extends Model
{
    protected $table = 'ec_discounts';

    protected $fillable = [
        'title',
        'code',
        'start_date',
        'end_date',
        'quantity',
        'total_used',
        'value',
        'type',
        'can_use_with_promotion',
        'type_option',
        'target',
        'min_order_price',
        'discount_on',
        'product_quantity',
        'apply_via_url',
        'display_at_checkout',
        'description',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'can_use_with_promotion' => 'bool',
        'apply_via_url' => 'bool',
        'display_at_checkout' => 'bool',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Discount $discount) {
            // $discount->productCollections()->detach();
            $discount->productCategories()->detach();
            $discount->customers()->detach();
            $discount->products()->detach();
            $discount->usedByCustomers()->detach();
        });
    }

    public function isExpired(): bool
    {
        return $this->end_date && strtotime($this->end_date) < strtotime(Carbon::now()->toDateTimeString());
    }

    // public function productCollections(): BelongsToMany
    // {
    //     return $this->belongsToMany(
    //         ProductCollection::class,
    //         'ec_discount_product_collections',
    //         'discount_id',
    //         'product_collection_id'
    //     );
    // }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'ec_discount_product_categories',
            'discount_id',
            'product_category_id'
        );
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'ec_discount_customers', 'discount_id', 'customer_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'ec_discount_products', 'discount_id', 'product_id');
    }

    public function usedByCustomers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'ec_customer_used_coupons');
    }

    protected function leftQuantity(): Attribute
    {
        return Attribute::get(fn () => $this->quantity - $this->total_used);
    }

    public function scopeActive(Builder $query): void
    {
        $query
            ->where('start_date', '<=', Carbon::now())
            ->where(
                fn (Builder $query) => $query
                    ->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now()->toDateTimeString())
            );
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where(
            fn (Builder $query) => $query
                ->whereNull('quantity')
                ->orWhereColumn('quantity', '>', 'total_used')
        );
    }
}
