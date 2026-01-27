<?php

declare(strict_types=1);

namespace Core\Commerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Content Override - Sparse override entry for white-label commerce.
 *
 * Stores a single field override for a specific entity + model combination.
 * Only stores what's different from the parent/original.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $overrideable_type
 * @property int $overrideable_id
 * @property string $field
 * @property string|null $value
 * @property string $value_type
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ContentOverride extends Model
{
    // Value types
    public const TYPE_STRING = 'string';

    public const TYPE_JSON = 'json';

    public const TYPE_HTML = 'html';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_BOOLEAN = 'boolean';

    protected $table = 'commerce_content_overrides';

    protected $fillable = [
        'entity_id',
        'overrideable_type',
        'overrideable_id',
        'field',
        'value',
        'value_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'overrideable_id' => 'integer',
    ];

    // Relationships

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function overrideable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Value casting

    /**
     * Get the value cast to its appropriate type.
     */
    public function getCastedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->value_type) {
            self::TYPE_JSON => json_decode($this->value, true),
            self::TYPE_INTEGER => (int) $this->value,
            self::TYPE_DECIMAL => (float) $this->value,
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value, // string, html
        };
    }

    /**
     * Set the value with automatic type detection.
     */
    public function setValueWithType(mixed $value): self
    {
        if ($value === null) {
            $this->value = null;
            $this->value_type = self::TYPE_STRING;

            return $this;
        }

        if (is_bool($value)) {
            $this->value = $value ? '1' : '0';
            $this->value_type = self::TYPE_BOOLEAN;
        } elseif (is_int($value)) {
            $this->value = (string) $value;
            $this->value_type = self::TYPE_INTEGER;
        } elseif (is_float($value)) {
            $this->value = (string) $value;
            $this->value_type = self::TYPE_DECIMAL;
        } elseif (is_array($value)) {
            $this->value = json_encode($value);
            $this->value_type = self::TYPE_JSON;
        } elseif (is_string($value) && $this->looksLikeHtml($value)) {
            $this->value = $value;
            $this->value_type = self::TYPE_HTML;
        } else {
            $this->value = (string) $value;
            $this->value_type = self::TYPE_STRING;
        }

        return $this;
    }

    /**
     * Check if a string looks like HTML content.
     */
    protected function looksLikeHtml(string $value): bool
    {
        return preg_match('/<[a-z][\s\S]*>/i', $value) === 1;
    }

    // Scopes

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('overrideable_type', $type)
            ->where('overrideable_id', $id);
    }

    public function scopeForField($query, string $field)
    {
        return $query->where('field', $field);
    }

    public function scopeForEntities($query, array $entityIds)
    {
        return $query->whereIn('entity_id', $entityIds);
    }

    // Factory helpers

    /**
     * Create or update an override.
     */
    public static function setOverride(
        Entity $entity,
        Model $model,
        string $field,
        mixed $value,
        ?int $userId = null
    ): self {
        $override = static::firstOrNew([
            'entity_id' => $entity->id,
            'overrideable_type' => $model->getMorphClass(),
            'overrideable_id' => $model->getKey(),
            'field' => $field,
        ]);

        $override->setValueWithType($value);

        if ($override->exists) {
            $override->updated_by = $userId ?? auth()->id();
        } else {
            $override->created_by = $userId ?? auth()->id();
        }

        $override->save();

        return $override;
    }

    /**
     * Remove an override.
     */
    public static function clearOverride(
        Entity $entity,
        Model $model,
        string $field
    ): bool {
        return static::where('entity_id', $entity->id)
            ->where('overrideable_type', $model->getMorphClass())
            ->where('overrideable_id', $model->getKey())
            ->where('field', $field)
            ->delete() > 0;
    }
}
