<?php

namespace App\Models\FrontEnd;
use App\Models\User;
use App\Models\Customer;
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
        return $this->is_active 
            && $this->status === 'approved'
            && $this->start_date <= now()
            && $this->expire_date >= now();
    }

    public function isExpired(): bool
    {
        return $this->expire_date < now();
    }

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
        if (!$this->isValid()) {
            return false;
        }

        if ($this->hasReachedUsageLimit()) {
            return false;
        }

        if ($this->usage_type === 'once') {
            return !$this->usages()->where('customer_id', $customerId)->exists();
        }

        if ($this->usage_limit_per_customer) {
            $customerUsageCount = $this->usages()
                ->where('customer_id', $customerId)
                ->count();
            
            return $customerUsageCount < $this->usage_limit_per_customer;
        }

        return true;
    }

    public function calculateDiscount(float $orderValue): float
    {
        if ($orderValue < $this->min_order_value) {
            return 0;
        }

        if ($this->max_order_value && $orderValue > $this->max_order_value) {
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