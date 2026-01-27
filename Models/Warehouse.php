<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commerce Warehouse - Fulfillment location.
 *
 * @property int $id
 * @property string $code
 * @property int $entity_id
 * @property string $name
 * @property string|null $description
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $county
 * @property string|null $postcode
 * @property string $country
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string $type
 * @property bool $can_ship
 * @property bool $can_pickup
 * @property bool $is_primary
 * @property array|null $operating_hours
 * @property array|null $settings
 * @property bool $is_active
 */
class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;

    // Warehouse types
    public const TYPE_OWNED = 'owned';

    public const TYPE_THIRD_PARTY = 'third_party';

    public const TYPE_DROPSHIP = 'dropship';

    public const TYPE_VIRTUAL = 'virtual';

    protected $table = 'commerce_warehouses';

    protected $fillable = [
        'code',
        'entity_id',
        'name',
        'description',
        'address_line1',
        'address_line2',
        'city',
        'county',
        'postcode',
        'country',
        'contact_name',
        'contact_email',
        'contact_phone',
        'type',
        'can_ship',
        'can_pickup',
        'is_primary',
        'operating_hours',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'settings' => 'array',
        'can_ship' => 'boolean',
        'can_pickup' => 'boolean',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id');
    }

    // Address helpers

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->county,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    // Stock helpers

    /**
     * Get stock for a specific product.
     */
    public function getStock(Product $product): ?Inventory
    {
        return $this->inventory()
            ->where('product_id', $product->id)
            ->first();
    }

    /**
     * Get available stock (quantity - reserved).
     */
    public function getAvailableStock(Product $product): int
    {
        $inventory = $this->getStock($product);

        if (! $inventory) {
            return 0;
        }

        return $inventory->getAvailableQuantity();
    }

    /**
     * Check if product is in stock at this warehouse.
     */
    public function hasStock(Product $product, int $quantity = 1): bool
    {
        return $this->getAvailableStock($product) >= $quantity;
    }

    // Operating hours

    /**
     * Check if warehouse is open at a given time.
     */
    public function isOpenAt(\DateTimeInterface $dateTime): bool
    {
        if (! $this->operating_hours) {
            return true; // No hours defined = always open
        }

        $dayOfWeek = strtolower($dateTime->format('D'));
        $time = $dateTime->format('H:i');

        $hours = $this->operating_hours[$dayOfWeek] ?? null;

        if (! $hours || ! isset($hours['open'], $hours['close'])) {
            return false;
        }

        return $time >= $hours['open'] && $time <= $hours['close'];
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeCanShip($query)
    {
        return $query->where('can_ship', true);
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
