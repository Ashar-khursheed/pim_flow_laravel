<?php

namespace App\Models\FrontEnd;
use App\Models\User;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\CouponCustomer;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'basis',
        'min_order_value',
        'max_order_value',
        'usage_type',
        'usage_limit',
        'usage_count',
        'usage_limit_per_customer',
        'start_date',
        'expire_date',
        'is_active',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'min_order_value' => 'decimal:2',
        'max_order_value' => 'decimal:2',
        'value' => 'decimal:2',
        'start_date' => 'datetime',
        'expire_date' => 'datetime',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'coupon_customers', 'coupon_id', 'customer_id')
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories')->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products')->withTimestamps();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->approved()
            ->where('start_date', '<=', now())
            ->where('expire_date', '>=', now());
    }

    public function scopeByBasis($query, string $basis)
    {
        return $query->where('basis', $basis);
    }

    // Helper methods
    public function isValid(): bool
    { 
        $today = now()->toDateString();         
        return
            $this->is_active == 1 &&
            strtolower($this->status) === 'approved' &&
            !empty($this->start_date) &&
            !empty($this->expire_date) &&
            strtotime($this->start_date->toDateString()) <= strtotime($today) &&
            strtotime($this->expire_date->toDateString()) >= strtotime($today);
    }

    public function isExpired(): bool
    {
        $today = now()->toDateString();
        return $this->expire_date->toDateString() < $today;
        
    }

    // public function isExpired(): bool
    // {
    //     return $this->expire_date < now();        
    // }
    public function hasUsageLimit(): bool
    {
        return !is_null($this->usage_limit);
    }

    public function hasReachedUsageLimit(): bool
    {
        return $this->hasUsageLimit() && $this->usage_count >= $this->usage_limit;
    }

    public function canBeUsedByCustomer(int $customerId): bool
    {
        $usage_type = $this->usage_type;
        $usage_limit = $this->usage_limit;
        $usage_limit_per_customer = $this->usage_limit_per_customer;
        $basis = $this->basis;

        // Get counts - FIXED: Separate total vs customer-specific
        $current_total_usage = $this->usage_count; // Total uses by ALL customers
        $current_customer_usage = $this->usages()->where('customer_id', $customerId)->count(); // This customer's uses

        // Basis-specific counts
        $current_customer_count = CouponCustomer::where('coupon_id', $this->id)
            ->where('customer_id', $customerId)
            ->count();

        $category_ids = CouponCategory::where('coupon_id', $this->id)
            ->pluck('category_id')
            ->toArray();
        $validCategories = $this->categories()->pluck('categories.id')->toArray();

        $productIds = CouponProduct::where('coupon_id', $this->id)
            ->pluck('product_id')
            ->toArray();
        // Validation Logic
        $is_valid = true;
        $error_message = '';

        // STEP 1: Check overall usage type limits (for ALL customers combined)
        if ($usage_type == 'once') {
             
            if ($current_total_usage >= 1) {                
                $is_valid = false;
                $error_message = 'This coupon can only be used once and has already been used.';
            }
        } elseif ($usage_type == 'multiple') {
            // Check if TOTAL usage across all customers has reached limit             
            if ($usage_limit > 0 && $current_total_usage >= $usage_limit) {
                $is_valid = false;
                $error_message = "This coupon has reached its usage limit of {$usage_limit}.";
            }
        } elseif ($usage_type == 'unlimited') {
            if ($is_valid && $usage_limit_per_customer > 0) {
                if ($current_customer_usage >= $usage_limit_per_customer) {
                    $is_valid = false;
                    $error_message = "You have reached the usage limit of {$usage_limit_per_customer} for this coupon.";
                }
            }
        }
         
        // STEP 3: Check basis-specific limits        
        if ($is_valid) {
            switch ($basis) {
                case 'customer':
                    // Check if this specific customer has reached their limit                   
                    $isAssigned = $this->customers()
                    ->where('customer_id', $customerId)
                    ->exists();    
                    if (!$isAssigned) {
                        $is_valid = false;
                        $error_message = "This coupon is not valid for your account.";
                    } 
                    break;

                case 'category':
                    // Check if category-specific limit is reached
                    $validCategories = $this->categories()->pluck('categories.id')->toArray();
                    if (empty(array_intersect($category_ids, $validCategories))) {

                        $is_valid = false;
                        $error_message = "This category has reached its usage limit.";
                    }
                    break;

                case 'product':
                    // Check if product-specific limit is reached

                    $validProducts = $this->products()->pluck('ec_products.id')->toArray();
                    if (empty(array_intersect($productIds, $validProducts))) {

                        $is_valid = false;
                        $error_message = "This product has reached its usage limit.";
                    }
                    break;
            }
        }

        // STEP 4: Other validations
        if (!$this->isValid()) {
            $is_valid = false;
            $error_message = 'This coupon is not valid.';
        }

        if (!$this->isExpired() && $this->expire_date < now()) {
            $is_valid = false;
            $error_message = 'This coupon has expired.';
        }

        return $is_valid;
    }
    // public function canBeUsedByCustomer_old(int $customerId): bool
    // {
    //     if (!$this->isValid()) {
    //         return false;
    //     }

    //     if ($this->hasReachedUsageLimit()) {
    //         return false;
    //     }

    //     if ($this->usage_type === 'once') {
    //         return !$this->usages()->where('customer_id', $customerId)->exists();
    //     }

    //     if ($this->usage_limit_per_customer) {
    //         $customerUsageCount = $this->usages()
    //             ->where('customer_id', $customerId)
    //             ->count();

    //         return $customerUsageCount < $this->usage_limit_per_customer;
    //     }

    //     return true;
    // }

    public function calculateDiscount(float $orderValue): float
    {
        if ($orderValue < $this->min_order_value) {
            return 0;
        }

        if ($this->max_order_value && $orderValue > $this->max_order_value) {
            return 0;
        }

        if ($orderValue < $this->value ) {     
            return 0;                            
        }
        if ($this->type === 'percentage') {
            return ($orderValue * $this->value) / 100;
        }

        return $this->value;
    }

    public function markAsUsed(int $customerId, float $orderValue, float $discountAmount, ?int $orderId = null): void
    {
        // Create usage record
        $this->usages()->create([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'order_value' => $orderValue,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);

        // Increment usage count
        $this->increment('usage_count');

        // Update customer pivot table
        $customer = $this->customers()->where('customer_id', $customerId)->first();
        if ($customer) {
            $customer->pivot->increment('usage_count');
        } else {
            $this->customers()->attach($customerId, ['usage_count' => 1]);
        }
    }

    public function approve(int $approverId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }
}