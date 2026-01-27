<?php

declare(strict_types=1);

namespace Core\Commerce\Models;

use Core\Mod\Tenant\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Commerce Entity - Multi-entity hierarchical commerce.
 *
 * Entity types:
 * - M1: Master Company (source of truth, owns product catalog)
 * - M2: Facades/Storefronts (select from M1, can override content)
 * - M3: Dropshippers (full inheritance, no management responsibility)
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 * @property string $path
 * @property int $depth
 * @property int|null $workspace_id
 * @property array|null $settings
 * @property string|null $domain
 * @property string $currency
 * @property string $timezone
 * @property bool $is_active
 */
class Entity extends Model
{
    use HasFactory;
    use SoftDeletes;

    // Entity types
    public const TYPE_M1_MASTER = 'm1';

    public const TYPE_M2_FACADE = 'm2';

    public const TYPE_M3_DROPSHIP = 'm3';

    protected $table = 'commerce_entities';

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'path',
        'depth',
        'workspace_id',
        'settings',
        'domain',
        'currency',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'depth' => 'integer',
    ];

    // Relationships

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(PermissionMatrix::class, 'entity_id');
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'entity_id');
    }

    // Type helpers

    public function isMaster(): bool
    {
        return $this->type === self::TYPE_M1_MASTER;
    }

    public function isFacade(): bool
    {
        return $this->type === self::TYPE_M2_FACADE;
    }

    public function isDropshipper(): bool
    {
        return $this->type === self::TYPE_M3_DROPSHIP;
    }

    // Hierarchy methods

    /**
     * Get ancestors from root to parent (not including self).
     */
    public function getAncestors(): Collection
    {
        if (! $this->path || $this->depth === 0) {
            return collect();
        }

        $pathCodes = explode('/', trim($this->path, '/'));
        array_pop($pathCodes); // Remove self

        if (empty($pathCodes)) {
            return collect();
        }

        return static::whereIn('code', $pathCodes)
            ->orderBy('depth')
            ->get();
    }

    /**
     * Get hierarchy from root to this entity (including self).
     */
    public function getHierarchy(): Collection
    {
        $ancestors = $this->getAncestors();
        $ancestors->push($this);

        return $ancestors;
    }

    /**
     * Get all descendants of this entity.
     */
    public function getDescendants(): Collection
    {
        return static::where('path', 'like', $this->path.'/%')->get();
    }

    /**
     * Get the root M1 entity for this hierarchy.
     */
    public function getRoot(): self
    {
        if ($this->depth === 0) {
            return $this;
        }

        $rootCode = explode('/', trim($this->path, '/'))[0];

        return static::where('code', $rootCode)->firstOrFail();
    }

    // SKU methods

    /**
     * Generate SKU prefix for this entity.
     * Format: M1-M2-SKU or M1-M2-M3-SKU
     */
    public function getSkuPrefix(): string
    {
        $pathCodes = explode('/', trim($this->path, '/'));

        return implode('-', $pathCodes);
    }

    /**
     * Build a full SKU with entity lineage.
     */
    public function buildSku(string $baseSku): string
    {
        return $this->getSkuPrefix().'-'.$baseSku;
    }

    // Factory methods

    /**
     * Create a new M1 master entity.
     */
    public static function createMaster(string $code, string $name, array $attributes = []): self
    {
        $code = Str::upper($code);

        return static::create(array_merge([
            'code' => $code,
            'name' => $name,
            'type' => self::TYPE_M1_MASTER,
            'path' => $code,
            'depth' => 0,
        ], $attributes));
    }

    /**
     * Create a child entity under this one.
     */
    public function createChild(string $code, string $name, string $type, array $attributes = []): self
    {
        $code = Str::upper($code);

        return static::create(array_merge([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'parent_id' => $this->id,
            'path' => $this->path.'/'.$code,
            'depth' => $this->depth + 1,
        ], $attributes));
    }

    /**
     * Create an M2 facade under this entity.
     */
    public function createFacade(string $code, string $name, array $attributes = []): self
    {
        return $this->createChild($code, $name, self::TYPE_M2_FACADE, $attributes);
    }

    /**
     * Create an M3 dropshipper under this entity.
     */
    public function createDropshipper(string $code, string $name, array $attributes = []): self
    {
        return $this->createChild($code, $name, self::TYPE_M3_DROPSHIP, $attributes);
    }

    // Type alias helpers

    public function isM1(): bool
    {
        return $this->isMaster();
    }

    public function isM2(): bool
    {
        return $this->isFacade();
    }

    public function isM3(): bool
    {
        return $this->isDropshipper();
    }

    // Boot

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $entity) {
            // Uppercase the code
            $entity->code = Str::upper($entity->code);

            // Compute path and depth if not set
            if (! $entity->path) {
                if ($entity->parent_id) {
                    $parent = static::find($entity->parent_id);
                    if ($parent) {
                        $entity->path = $parent->path.'/'.$entity->code;
                        $entity->depth = $parent->depth + 1;
                    }
                } else {
                    $entity->path = $entity->code;
                    $entity->depth = 0;
                }
            }

            // Auto-determine type based on parent if not set
            if (! $entity->type) {
                if (! $entity->parent_id) {
                    $entity->type = self::TYPE_M1_MASTER;
                } else {
                    $parent = static::find($entity->parent_id);
                    $entity->type = match ($parent?->type) {
                        self::TYPE_M1_MASTER => self::TYPE_M2_FACADE,
                        self::TYPE_M2_FACADE => self::TYPE_M3_DROPSHIP,
                        default => self::TYPE_M3_DROPSHIP,
                    };
                }
            }
        });
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMasters($query)
    {
        return $query->where('type', self::TYPE_M1_MASTER);
    }

    public function scopeFacades($query)
    {
        return $query->where('type', self::TYPE_M2_FACADE);
    }

    public function scopeDropshippers($query)
    {
        return $query->where('type', self::TYPE_M3_DROPSHIP);
    }

    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    // Settings helpers

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }
}
