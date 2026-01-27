# Commerce Matrix Plan

**Rebuilding the Lost System - Multi-Channel Commerce with Hierarchical Permissions**

---

## The Origin Story (2008)

Original Organics had:
- 4 websites (different facades)
- Telephone orders
- Mail order (actual mail)
- Garden centre voucher schemes

Problem: M1 (master company) doesn't want aggregated M2 data polluting their product list.

Solution: **The Matrix**

---

## The Three Containers

```
M1 - Master Company (Source of Truth)
│
├── Master Product Catalog
│   └── Products live here, nowhere else
│
├── M2 - Facades/Storefronts (Select from M1)
│   ├── waterbutts.com
│   ├── originalorganics.co.uk
│   ├── telephone-orders (internal)
│   ├── mail-order (internal)
│   └── garden-vouchers (B2B)
│
└── M3 - Dropshippers (Full Inheritance, No Management)
    ├── External company selling our products
    ├── Full visibility, full reporting
    ├── Zero management responsibility
    └── Can have their own M2s!
        ├── dropshipper.com
        └── dropshipper-wholesale.com
```

### SKU Lineage

```
M1-M2-<SKU>

Example:
ORGORG-WBUTS-WB500L    # Original Organics → Waterbutts → 500L Water Butt
ORGORG-PHONE-WB500L    # Same product, telephone channel
DRPSHP-THEIR1-WB500L   # Dropshipper's storefront selling our product
```

This tracks:
- Where the sale originated
- Which facade/channel
- Back to master SKU

---

## The Permission Matrix (Top-Down Immutable)

### The Core Concept

```
If M1 says "NO" → Everything below is "NO"
If M1 says "YES" → M2 can say "NO" for itself
If M2 says "YES" → M3 can say "NO" for itself

Permissions cascade DOWN, restrictions are IMMUTABLE from above.
```

### Visual Model

```
                    M1 (Master)
                    ├── can_sell_alcohol: NO ──────────────┐
                    ├── can_discount: YES                  │
                    └── can_export: YES                    │
                         │                                 │
            ┌────────────┼────────────┐                    │
            ▼            ▼            ▼                    │
         M2-Web      M2-Phone     M2-Voucher              │
         ├── can_sell_alcohol: [LOCKED NO] ◄──────────────┘
         ├── can_discount: NO (restricted self)
         └── can_export: YES (inherited)
              │
              ▼
           M3-Dropshipper
           ├── can_sell_alcohol: [LOCKED NO] (from M1)
           ├── can_discount: [LOCKED NO] (from M2)
           └── can_export: YES (can restrict to NO)
```

### The 3D Matrix

```
Dimension 1: Entity Hierarchy (M1 → M2 → M3)
Dimension 2: Permission Keys (can_sell, can_discount, can_export, can_view_cost...)
Dimension 3: Resource Scope (products, orders, customers, reports...)

Permission = Matrix[Entity][Key][Scope]
```

---

## The Internal WAF (Request-Level Enforcement)

> **EXTRACTED:** This section moved to `CORE_BOUNCER_PLAN.md` as a framework-level concern.
> The training mode / request whitelisting system applies to all modules, not just commerce.

### Every Request is Gated

```php
// Not just "can user do X"
// But "can THIS REQUEST from THIS ENTITY do THIS ACTION on THIS RESOURCE"

POST /orders
├── Entity: M2-Web (waterbutts.com)
├── Action: order.create
├── Resource: order
├── Context: { customer_id: 123, products: [...] }
│
└── Matrix Check:
    ├── Does M1 allow M2-Web to create orders? ✓
    ├── Does M2-Web allow this product combination? ✓
    ├── Does customer region allow these products? ✓
    └── ALLOW
```

### Training Mode (Dev Mode Learning)

```
1. Developer goes to /admin/products
2. Clicks "Create Product"
3. System: "BLOCKED - No permission defined for:"
   - Entity: M1-Admin
   - Action: product.create
   - Route: POST /admin/products

4. Developer clicks [Allow for M1-Admin]
5. Permission recorded in matrix
6. Continue working

Result: Complete map of every action in the system
```

### Production Mode (Strict Enforcement)

```
If permission not in matrix → 403 Forbidden
No exceptions. No fallbacks. No "default allow".

If it wasn't trained, it doesn't exist.
```

---

## Laravel Implementation

### 1. The Entity Hierarchy

```php
// database/migrations/create_commerce_entities_table.php

Schema::create('commerce_entities', function (Blueprint $table) {
    $table->id();
    $table->string('code', 32)->unique();        // ORGORG, WBUTS, DRPSHP
    $table->string('name');
    $table->string('type');                       // m1, m2, m3

    // Hierarchy
    $table->foreignId('parent_id')->nullable()->constrained('commerce_entities');
    $table->string('path')->index();              // ORGORG/WBUTS/DRPSHP (materialized path)
    $table->integer('depth')->default(0);

    // Settings
    $table->json('settings')->nullable();
    $table->boolean('is_active')->default(true);

    $table->timestamps();
});

// Entity types
const TYPE_M1_MASTER = 'm1';        // Master company
const TYPE_M2_FACADE = 'm2';        // Storefront/channel
const TYPE_M3_DROPSHIP = 'm3';      // Dropshipper (inherits, doesn't manage)
```

### 2. The Permission Matrix

```php
// database/migrations/create_permission_matrix_table.php

Schema::create('permission_matrix', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');

    // Permission definition
    $table->string('key');                        // product.create, order.refund
    $table->string('scope')->nullable();          // Resource type or specific ID

    // The value
    $table->boolean('allowed')->default(false);
    $table->boolean('locked')->default(false);    // Set by parent, cannot override

    // Audit
    $table->string('source');                     // 'inherited', 'explicit', 'trained'
    $table->foreignId('set_by_entity_id')->nullable();
    $table->timestamp('trained_at')->nullable();  // When it was learned
    $table->string('trained_route')->nullable();  // Which route triggered training

    $table->timestamps();

    $table->unique(['entity_id', 'key', 'scope']);
    $table->index(['key', 'scope']);
});
```

### 3. The Request Log (Training Data)

```php
// database/migrations/create_permission_requests_table.php

Schema::create('permission_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');

    // Request details
    $table->string('method');                     // GET, POST, PUT, DELETE
    $table->string('route');                      // /admin/products
    $table->string('action');                     // product.create
    $table->string('scope')->nullable();

    // Context
    $table->json('request_data')->nullable();     // Sanitized request params
    $table->string('user_agent')->nullable();
    $table->string('ip_address')->nullable();

    // Result
    $table->string('status');                     // allowed, denied, pending
    $table->boolean('was_trained')->default(false);

    $table->timestamps();

    $table->index(['entity_id', 'action', 'status']);
    $table->index(['status', 'created_at']);
});
```

### 4. The Matrix Service

```php
// app/Services/Commerce/PermissionMatrixService.php

namespace App\Services\Commerce;

use App\Models\Commerce\Entity;
use App\Models\Commerce\PermissionMatrix;
use Illuminate\Http\Request;

class PermissionMatrixService
{
    protected bool $trainingMode;

    public function __construct()
    {
        $this->trainingMode = config('commerce.matrix.training_mode', false);
    }

    /**
     * Check if an entity can perform an action
     */
    public function can(Entity $entity, string $key, ?string $scope = null): PermissionResult
    {
        // Build the hierarchy path (M1 → M2 → M3)
        $hierarchy = $this->getHierarchy($entity);

        // Check from top down (M1 first)
        foreach ($hierarchy as $ancestor) {
            $permission = PermissionMatrix::where('entity_id', $ancestor->id)
                ->where('key', $key)
                ->where(function ($q) use ($scope) {
                    $q->whereNull('scope')->orWhere('scope', $scope);
                })
                ->first();

            if ($permission) {
                // If locked and denied at this level, everything below is denied
                if ($permission->locked && !$permission->allowed) {
                    return PermissionResult::denied(
                        reason: "Locked by {$ancestor->name}",
                        locked_by: $ancestor
                    );
                }

                // If explicitly denied (not locked), continue checking
                if (!$permission->allowed && !$permission->locked) {
                    return PermissionResult::denied(
                        reason: "Denied by {$ancestor->name}"
                    );
                }
            }
        }

        // Check the entity itself
        $ownPermission = PermissionMatrix::where('entity_id', $entity->id)
            ->where('key', $key)
            ->where(function ($q) use ($scope) {
                $q->whereNull('scope')->orWhere('scope', $scope);
            })
            ->first();

        if ($ownPermission) {
            return $ownPermission->allowed
                ? PermissionResult::allowed()
                : PermissionResult::denied(reason: "Denied by own policy");
        }

        // No permission found
        return PermissionResult::undefined(key: $key, scope: $scope);
    }

    /**
     * Gate a request through the matrix
     */
    public function gateRequest(Request $request, Entity $entity, string $action): PermissionResult
    {
        $scope = $this->extractScope($request);
        $result = $this->can($entity, $action, $scope);

        // Log the request
        $this->logRequest($request, $entity, $action, $scope, $result);

        // Training mode: undefined permissions become pending for approval
        if ($result->isUndefined() && $this->trainingMode) {
            return PermissionResult::pending(
                key: $action,
                scope: $scope,
                training_url: route('commerce.matrix.train', [
                    'entity' => $entity->id,
                    'key' => $action,
                    'scope' => $scope,
                ])
            );
        }

        // Production mode: undefined = denied
        if ($result->isUndefined()) {
            return PermissionResult::denied(
                reason: "No permission defined for {$action}"
            );
        }

        return $result;
    }

    /**
     * Train a permission (dev mode)
     */
    public function train(Entity $entity, string $key, ?string $scope, bool $allow, ?string $route = null): void
    {
        // Check if parent has locked this
        $hierarchy = $this->getHierarchy($entity);
        foreach ($hierarchy as $ancestor) {
            $parentPerm = PermissionMatrix::where('entity_id', $ancestor->id)
                ->where('key', $key)
                ->where('locked', true)
                ->first();

            if ($parentPerm && !$parentPerm->allowed) {
                throw new PermissionLockedException(
                    "Cannot train permission '{$key}' - locked by {$ancestor->name}"
                );
            }
        }

        PermissionMatrix::updateOrCreate(
            ['entity_id' => $entity->id, 'key' => $key, 'scope' => $scope],
            [
                'allowed' => $allow,
                'source' => 'trained',
                'trained_at' => now(),
                'trained_route' => $route,
            ]
        );
    }

    /**
     * Lock a permission (cascades down)
     */
    public function lock(Entity $entity, string $key, bool $allowed, ?string $scope = null): void
    {
        // Set on this entity
        PermissionMatrix::updateOrCreate(
            ['entity_id' => $entity->id, 'key' => $key, 'scope' => $scope],
            [
                'allowed' => $allowed,
                'locked' => true,
                'source' => 'explicit',
                'set_by_entity_id' => $entity->id,
            ]
        );

        // Cascade to all descendants
        $descendants = Entity::where('path', 'like', $entity->path . '/%')->get();
        foreach ($descendants as $descendant) {
            PermissionMatrix::updateOrCreate(
                ['entity_id' => $descendant->id, 'key' => $key, 'scope' => $scope],
                [
                    'allowed' => $allowed,
                    'locked' => true,
                    'source' => 'inherited',
                    'set_by_entity_id' => $entity->id,
                ]
            );
        }
    }

    protected function getHierarchy(Entity $entity): Collection
    {
        // Return ancestors from root to parent (not including self)
        $path = explode('/', trim($entity->path, '/'));
        array_pop(); // Remove self

        return Entity::whereIn('code', $path)
            ->orderBy('depth')
            ->get();
    }
}
```

### 5. The Middleware (WAF Integration)

```php
// app/Http/Middleware/CommerceMatrixGate.php

namespace App\Http\Middleware;

use App\Services\Commerce\PermissionMatrixService;
use Closure;

class CommerceMatrixGate
{
    public function __construct(
        protected PermissionMatrixService $matrix
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $entity = $this->resolveEntity($request);
        $action = $this->resolveAction($request);

        if (!$entity || !$action) {
            return $next($request); // Not a commerce route
        }

        $result = $this->matrix->gateRequest($request, $entity, $action);

        if ($result->isDenied()) {
            return response()->json([
                'error' => 'permission_denied',
                'message' => $result->reason,
                'key' => $action,
            ], 403);
        }

        if ($result->isPending()) {
            // Training mode - show the training UI
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'permission_undefined',
                    'message' => 'Permission not yet trained',
                    'training_url' => $result->training_url,
                    'key' => $result->key,
                    'scope' => $result->scope,
                ], 428); // Precondition Required
            }

            return response()->view('commerce.matrix.train-prompt', [
                'result' => $result,
                'request' => $request,
                'entity' => $entity,
            ], 428);
        }

        return $next($request);
    }

    protected function resolveAction(Request $request): ?string
    {
        // Option 1: Route-based action mapping
        $route = $request->route();
        if ($route && $action = $route->getAction('matrix_action')) {
            return $action;
        }

        // Option 2: Controller@method convention
        if ($route) {
            $controller = class_basename($route->getControllerClass());
            $method = $route->getActionMethod();
            return Str::snake($controller) . '.' . $method;
            // ProductController@store → product_controller.store
        }

        // Option 3: REST convention
        $method = $request->method();
        $resource = $request->segment(2); // /api/products → products

        return match($method) {
            'GET' => "{$resource}.view",
            'POST' => "{$resource}.create",
            'PUT', 'PATCH' => "{$resource}.update",
            'DELETE' => "{$resource}.delete",
            default => null,
        };
    }
}
```

### 6. Route Definition with Matrix Actions

```php
// routes/commerce.php

Route::middleware(['auth', 'commerce.matrix'])->prefix('commerce')->group(function () {

    // Explicit action mapping
    Route::get('/products', [ProductController::class, 'index'])
        ->matrixAction('product.list');

    Route::post('/products', [ProductController::class, 'store'])
        ->matrixAction('product.create');

    Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])
        ->matrixAction('order.refund');

    // Or use conventions and let middleware figure it out
    Route::apiResource('customers', CustomerController::class);
    // GET /customers → customer.index
    // POST /customers → customer.store
    // etc.
});
```

### 7. The Training UI

```blade
{{-- resources/views/commerce/matrix/train-prompt.blade.php --}}

<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                <flux:icon name="shield-exclamation" class="w-6 h-6 text-amber-600" />
            </div>
            <div>
                <h2 class="text-lg font-semibold">Permission Not Defined</h2>
                <p class="text-sm text-gray-500">Training Mode Active</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-4 font-mono text-sm">
            <div><span class="text-gray-500">Entity:</span> {{ $entity->name }} ({{ $entity->type }})</div>
            <div><span class="text-gray-500">Action:</span> {{ $result->key }}</div>
            <div><span class="text-gray-500">Scope:</span> {{ $result->scope ?? 'global' }}</div>
            <div><span class="text-gray-500">Route:</span> {{ $request->method() }} {{ $request->path() }}</div>
        </div>

        <form action="{{ route('commerce.matrix.train') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="entity_id" value="{{ $entity->id }}">
            <input type="hidden" name="key" value="{{ $result->key }}">
            <input type="hidden" name="scope" value="{{ $result->scope }}">
            <input type="hidden" name="route" value="{{ $request->fullUrl() }}">

            <div class="flex gap-3">
                <button type="submit" name="allow" value="1"
                    class="flex-1 bg-green-600 text-white rounded-lg py-2 hover:bg-green-700">
                    Allow for {{ $entity->name }}
                </button>
                <button type="submit" name="allow" value="0"
                    class="flex-1 bg-red-600 text-white rounded-lg py-2 hover:bg-red-700">
                    Deny for {{ $entity->name }}
                </button>
            </div>

            @if($entity->type !== 'm1')
                <div class="border-t pt-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="lock" value="1" class="rounded">
                        <span class="text-sm">Lock this permission (prevent children from overriding)</span>
                    </label>
                </div>
            @endif
        </form>

        <div class="mt-4 pt-4 border-t">
            <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-700">
                ← Go back without training
            </a>
        </div>
    </div>
</div>
```

---

## The Product Catalog Matrix

### Master Catalog (M1)

```php
// M1 owns the master product catalog
Schema::create('commerce_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('owner_entity_id')->constrained('commerce_entities'); // M1
    $table->string('master_sku')->unique();

    // Product data
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('cost_price', 10, 2);         // True cost (M1 eyes only)
    $table->decimal('base_price', 10, 2);         // Default selling price

    // Categorization
    $table->json('categories')->nullable();        // Can be selected by M2s
    $table->json('attributes')->nullable();

    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Facade Product Selection (M2)

```php
// M2 selects products from M1's catalog
Schema::create('commerce_facade_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');      // M2
    $table->foreignId('product_id')->constrained('commerce_products');     // From M1

    // Facade-specific SKU
    $table->string('facade_sku');                  // M1-M2-<SKU>

    // Override pricing (if allowed by matrix)
    $table->decimal('price_override', 10, 2)->nullable();
    $table->decimal('sale_price', 10, 2)->nullable();

    // Visibility
    $table->boolean('is_visible')->default(true);
    $table->integer('sort_order')->default(0);

    // Facade-specific content
    $table->string('display_name')->nullable();    // Override product name
    $table->text('custom_description')->nullable();

    $table->timestamps();

    $table->unique(['entity_id', 'product_id']);
    $table->unique(['entity_id', 'facade_sku']);
});
```

### Dropshipper Inheritance (M3)

```php
// M3 inherits from M2 (or M1) with optional restrictions
Schema::create('commerce_dropship_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');           // M3
    $table->foreignId('source_entity_id')->constrained('commerce_entities');    // M2 or M1
    $table->foreignId('facade_product_id')->nullable();  // If inheriting from M2
    $table->foreignId('product_id');                     // Master product ref

    $table->string('dropship_sku');               // Full lineage SKU

    // Margin/pricing (what dropshipper pays vs sells for)
    $table->decimal('wholesale_price', 10, 2);    // What they pay M1
    $table->decimal('suggested_retail', 10, 2)->nullable();

    // Can they see cost?
    // Controlled by permission matrix: product.view_cost

    $table->timestamps();
});
```

---

## The Content Override Matrix (White-Label Engine)

### The Core Insight

**Don't copy data. Create sparse overrides. Resolve at runtime.**

```
M1 (Master) has content
    │
    │ (M2 sees M1's content by default)
    ▼
M2 customizes product name
    │
    │ Override entry: (M2, product, 123, name, "Custom Name")
    │ Everything else still inherits from M1
    ▼
M3 (Dropshipper) inherits M2's view
    │
    │ (Sees M2's custom name, M1's everything else)
    ▼
M3 customizes description
    │
    │ Override entry: (M3, product, 123, description, "Their description")
    │ Still has M2's name, M1's other fields
    ▼
Resolution: M3 sees merged content from all levels
```

### The Override Table

```php
Schema::create('commerce_content_overrides', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');

    // What's being overridden
    $table->string('content_type');               // product, category, page, email_template, setting
    $table->unsignedBigInteger('content_id');     // ID of the source content
    $table->string('field');                      // name, description, image, price, body, etc.

    // The override value
    $table->text('value')->nullable();            // The custom value
    $table->string('value_type')->default('string'); // string, json, html, decimal, boolean

    // Audit
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();

    // Unique: one override per entity+content+field
    $table->unique(['entity_id', 'content_type', 'content_id', 'field'], 'content_override_unique');
    $table->index(['content_type', 'content_id']);
});
```

### Content Types

```php
// Everything that can be white-labeled
const CONTENT_TYPES = [
    'product' => [
        'fields' => ['name', 'description', 'short_description', 'image', 'images', 'price', 'sale_price'],
        'model' => Product::class,
    ],
    'category' => [
        'fields' => ['name', 'description', 'image', 'slug'],
        'model' => Category::class,
    ],
    'page' => [
        'fields' => ['title', 'body', 'meta_title', 'meta_description'],
        'model' => Page::class,
    ],
    'email_template' => [
        'fields' => ['subject', 'body', 'from_name'],
        'model' => EmailTemplate::class,
    ],
    'setting' => [
        'fields' => ['value'],  // site_name, logo, colors, etc.
        'model' => Setting::class,
    ],
    'checkout_field' => [
        'fields' => ['label', 'placeholder', 'help_text', 'required', 'visible'],
        'model' => CheckoutField::class,
    ],
];
```

### The Resolution Service

```php
// app/Services/Commerce/ContentOverrideService.php

namespace App\Services\Commerce;

use App\Models\Commerce\Entity;
use App\Models\Commerce\ContentOverride;
use Illuminate\Database\Eloquent\Model;

class ContentOverrideService
{
    /**
     * Get content with all overrides applied
     */
    public function resolve(Entity $entity, string $contentType, Model $content): array
    {
        // Start with original content
        $resolved = $content->toArray();

        // Get hierarchy from M1 down to this entity
        $hierarchy = $this->getHierarchyTopDown($entity);

        // Apply overrides in order (M1 first, then M2, then M3, etc.)
        foreach ($hierarchy as $ancestor) {
            $overrides = ContentOverride::where('entity_id', $ancestor->id)
                ->where('content_type', $contentType)
                ->where('content_id', $content->id)
                ->get()
                ->keyBy('field');

            foreach ($overrides as $field => $override) {
                $resolved[$field] = $this->castValue($override->value, $override->value_type);
            }
        }

        return $resolved;
    }

    /**
     * Get a single field with override resolution
     */
    public function resolveField(Entity $entity, string $contentType, int $contentId, string $field, $default = null)
    {
        // Check from this entity up to root, return first override found
        $hierarchy = $this->getHierarchyBottomUp($entity);

        foreach ($hierarchy as $ancestor) {
            $override = ContentOverride::where('entity_id', $ancestor->id)
                ->where('content_type', $contentType)
                ->where('content_id', $contentId)
                ->where('field', $field)
                ->first();

            if ($override) {
                return $this->castValue($override->value, $override->value_type);
            }
        }

        return $default;
    }

    /**
     * Set an override for an entity
     */
    public function override(Entity $entity, string $contentType, int $contentId, string $field, $value): ContentOverride
    {
        // Permission check
        $this->checkCanOverride($entity, $contentType, $field);

        return ContentOverride::updateOrCreate(
            [
                'entity_id' => $entity->id,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'field' => $field,
            ],
            [
                'value' => $this->serializeValue($value),
                'value_type' => $this->detectType($value),
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * Remove an override (revert to inherited value)
     */
    public function revert(Entity $entity, string $contentType, int $contentId, string $field): bool
    {
        return ContentOverride::where('entity_id', $entity->id)
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('field', $field)
            ->delete() > 0;
    }

    /**
     * Get all overrides for an entity (for admin UI)
     */
    public function getEntityOverrides(Entity $entity): Collection
    {
        return ContentOverride::where('entity_id', $entity->id)
            ->orderBy('content_type')
            ->orderBy('content_id')
            ->get()
            ->groupBy(['content_type', 'content_id']);
    }

    /**
     * Check what's overridden vs inherited for an entity
     */
    public function getOverrideStatus(Entity $entity, string $contentType, Model $content): array
    {
        $fields = self::CONTENT_TYPES[$contentType]['fields'] ?? [];
        $status = [];

        foreach ($fields as $field) {
            $override = ContentOverride::where('content_type', $contentType)
                ->where('content_id', $content->id)
                ->where('field', $field)
                ->whereIn('entity_id', $this->getHierarchyIds($entity))
                ->orderByRaw("FIELD(entity_id, " . implode(',', $this->getHierarchyIds($entity)) . ") DESC")
                ->first();

            $status[$field] = [
                'value' => $this->resolveField($entity, $contentType, $content->id, $field, $content->$field),
                'source' => $override ? $override->entity->name : 'original',
                'is_overridden' => $override && $override->entity_id === $entity->id,
                'inherited_from' => $override && $override->entity_id !== $entity->id ? $override->entity->name : null,
            ];
        }

        return $status;
    }

    protected function castValue($value, string $type)
    {
        return match($type) {
            'json' => json_decode($value, true),
            'decimal' => (float) $value,
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => $value,
        };
    }
}
```

### Eloquent Integration (Automatic Resolution)

```php
// app/Models/Commerce/Product.php

namespace App\Models\Commerce;

use App\Services\Commerce\ContentOverrideService;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * Get product with overrides for current entity context
     */
    public function forEntity(Entity $entity): array
    {
        return app(ContentOverrideService::class)
            ->resolve($entity, 'product', $this);
    }

    /**
     * Scope for entity-resolved products
     */
    public function scopeWithOverrides($query, Entity $entity)
    {
        // Returns products with override data merged
        return $query->get()->map(fn ($product) => (object) $product->forEntity($entity));
    }
}

// Usage in controller/view
$product = Product::find(123);

// Raw M1 data
$product->name; // "500L Water Butt"

// Resolved for M2
$product->forEntity($m2Entity)['name']; // "Premium 500L Water Butt" (if overridden)

// Resolved for M3 (dropshipper)
$product->forEntity($m3Entity)['name']; // "AquaSave 500L Tank" (white-labeled)
```

### The Override UI Pattern

```blade
{{-- resources/views/commerce/products/edit-override.blade.php --}}
{{-- Shows field with inheritance status and override toggle --}}

@php
    $overrideService = app(ContentOverrideService::class);
    $status = $overrideService->getOverrideStatus($entity, 'product', $product);
@endphp

<form action="{{ route('commerce.products.override', [$entity, $product]) }}" method="POST">
    @csrf

    @foreach($status as $field => $info)
    <div class="field-group mb-4">
        <div class="flex justify-between items-center mb-1">
            <label class="font-medium">{{ Str::title(str_replace('_', ' ', $field)) }}</label>

            @if($info['inherited_from'])
                <span class="text-xs text-blue-600">
                    Inherited from {{ $info['inherited_from'] }}
                </span>
            @elseif($info['is_overridden'])
                <button type="button"
                    wire:click="revert('{{ $field }}')"
                    class="text-xs text-amber-600 hover:underline">
                    Revert to inherited
                </button>
            @else
                <span class="text-xs text-gray-400">Original</span>
            @endif
        </div>

        <div class="relative">
            @if($info['is_overridden'])
                {{-- Editable - this entity has overridden it --}}
                <input type="text"
                    name="overrides[{{ $field }}]"
                    value="{{ $info['value'] }}"
                    class="w-full border-2 border-amber-200 rounded px-3 py-2"
                >
                <span class="absolute right-2 top-2 text-amber-500">
                    <flux:icon name="pencil" class="w-4 h-4" />
                </span>
            @else
                {{-- Read-only with override button --}}
                <input type="text"
                    value="{{ $info['value'] }}"
                    class="w-full border rounded px-3 py-2 bg-gray-50"
                    readonly
                >
                <button type="button"
                    wire:click="startOverride('{{ $field }}')"
                    class="absolute right-2 top-2 text-gray-400 hover:text-blue-600">
                    <flux:icon name="pencil-square" class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>
    @endforeach

    <div class="flex justify-end gap-2">
        <flux:button type="submit">Save Overrides</flux:button>
    </div>
</form>
```

### White-Label Store Generation

When a dropshipper (M3) is created, they get a "premade store":

```php
// app/Services/Commerce/DropshipperOnboardingService.php

class DropshipperOnboardingService
{
    public function provision(Entity $parent, array $data): Entity
    {
        // Create the M3 entity
        $dropshipper = Entity::create([
            'code' => Str::upper(Str::slug($data['company_name'])),
            'name' => $data['company_name'],
            'type' => 'm3',
            'parent_id' => $parent->id,
            'path' => $parent->path . '/' . Str::upper(Str::slug($data['company_name'])),
        ]);

        // Inherit ALL products from parent (creates facade product links)
        $this->inheritCatalog($dropshipper, $parent);

        // Copy default page templates (but as inherited, not copied)
        $this->linkPages($dropshipper, $parent);

        // Set up default branding overrides
        if ($data['brand_name']) {
            app(ContentOverrideService::class)->override(
                $dropshipper,
                'setting',
                Setting::where('key', 'site_name')->first()->id,
                'value',
                $data['brand_name']
            );
        }

        if ($data['logo']) {
            app(ContentOverrideService::class)->override(
                $dropshipper,
                'setting',
                Setting::where('key', 'logo')->first()->id,
                'value',
                $data['logo']
            );
        }

        // They now have a complete store, seeing parent's content
        // Anything they edit creates an override entry
        // White-label ready from day one

        return $dropshipper;
    }

    protected function inheritCatalog(Entity $child, Entity $parent): void
    {
        // Link to all parent's products (no data copied)
        $parentProducts = FacadeProduct::where('entity_id', $parent->id)->get();

        foreach ($parentProducts as $fp) {
            DropshipProduct::create([
                'entity_id' => $child->id,
                'source_entity_id' => $parent->id,
                'facade_product_id' => $fp->id,
                'product_id' => $fp->product_id,
                'dropship_sku' => $child->code . '-' . $fp->facade_sku,
                'wholesale_price' => $this->calculateWholesale($fp),
            ]);
        }
    }
}
```

### The Resolution Chain Visualized

```
Query: "What is product 123's name for M3-ACME?"

                    ┌─────────────────────────────────────────┐
                    │           RESOLUTION CHAIN              │
                    └─────────────────────────────────────────┘

Step 1: Check M3-ACME overrides
        ┌─────────────────────────────────────────────────┐
        │ commerce_content_overrides                       │
        │ WHERE entity_id = M3-ACME                       │
        │   AND content_type = 'product'                  │
        │   AND content_id = 123                          │
        │   AND field = 'name'                            │
        │                                                  │
        │ Result: NULL (no override)                      │
        └─────────────────────────────────────────────────┘
                              │
                              ▼ (not found, check parent)

Step 2: Check M2-WATERBUTTS overrides
        ┌─────────────────────────────────────────────────┐
        │ commerce_content_overrides                       │
        │ WHERE entity_id = M2-WATERBUTTS                 │
        │   AND content_type = 'product'                  │
        │   AND content_id = 123                          │
        │   AND field = 'name'                            │
        │                                                  │
        │ Result: "Premium 500L Water Butt" ✓             │
        └─────────────────────────────────────────────────┘
                              │
                              ▼ (found!)

Step 3: Return "Premium 500L Water Butt"
        (M3-ACME sees M2's override, not M1's original)

─────────────────────────────────────────────────────────────

If M3-ACME later customizes the name:

        ┌─────────────────────────────────────────────────┐
        │ INSERT INTO commerce_content_overrides          │
        │ (entity_id, content_type, content_id, field, value) │
        │ VALUES                                          │
        │ (M3-ACME, 'product', 123, 'name', 'AquaSave Tank') │
        └─────────────────────────────────────────────────┘

Now M3-ACME sees "AquaSave Tank"
M2-WATERBUTTS still sees "Premium 500L Water Butt"
M1-ORGORG still sees "500L Water Butt"
```

---

## Order Flow Through the Matrix

```
Customer places order on waterbutts.com (M2)
    │
    ▼
┌─────────────────────────────────────────┐
│ Order Created                            │
│ - entity_id: M2-WBUTS                   │
│ - sku: ORGORG-WBUTS-WB500L              │
│ - customer sees: M2 branding            │
└────────────────┬────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│ M1 Fulfillment Queue                     │
│ - M1 sees all orders from all M2s       │
│ - Can filter by facade                  │
│ - Ships with M2 branding (or neutral)   │
└────────────────┬────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│ Reporting                                │
│ - M1: Sees all, costs, margins          │
│ - M2: Sees own orders, no cost data     │
│ - M3: Sees own orders, wholesale price  │
└─────────────────────────────────────────┘
```

---

## Permission Keys (Standard Set)

```php
// Product permissions
'product.list'              // View product list
'product.view'              // View product detail
'product.view_cost'         // See cost price (M1 only usually)
'product.create'            // Create new product (M1 only)
'product.update'            // Update product
'product.delete'            // Delete product
'product.price_override'    // Override price on facade

// Order permissions
'order.list'                // View orders
'order.view'                // View order detail
'order.create'              // Create order
'order.update'              // Update order
'order.cancel'              // Cancel order
'order.refund'              // Process refund
'order.export'              // Export order data

// Customer permissions
'customer.list'
'customer.view'
'customer.view_email'       // See customer email
'customer.view_phone'       // See customer phone
'customer.export'           // Export customer data (GDPR!)

// Report permissions
'report.sales'              // Sales reports
'report.revenue'            // Revenue (might hide from M3)
'report.cost'               // Cost reports (M1 only)
'report.margin'             // Margin reports (M1 only)

// System permissions
'settings.view'
'settings.update'
'entity.create'             // Create child entities
'entity.manage'             // Manage entity settings
```

---

## Configuration

```php
// config/commerce.php

return [
    'matrix' => [
        // Training mode - undefined permissions prompt for approval
        'training_mode' => env('COMMERCE_MATRIX_TRAINING', false),

        // Production mode - undefined = denied
        'strict_mode' => env('COMMERCE_MATRIX_STRICT', true),

        // Log all permission checks (for audit)
        'log_all_checks' => env('COMMERCE_MATRIX_LOG_ALL', false),

        // Log denied requests
        'log_denials' => true,

        // Default action when permission undefined (only if strict=false)
        'default_allow' => false,
    ],

    'entities' => [
        'types' => [
            'm1' => [
                'name' => 'Master Company',
                'can_have_children' => true,
                'child_types' => ['m2', 'm3'],
            ],
            'm2' => [
                'name' => 'Facade/Storefront',
                'can_have_children' => true,
                'child_types' => ['m3'],
            ],
            'm3' => [
                'name' => 'Dropshipper',
                'can_have_children' => true,  // Can have own M2s!
                'child_types' => ['m2'],
                'inherits_catalog' => true,
            ],
        ],
    ],

    'sku' => [
        // SKU format: {m1_code}-{m2_code}-{master_sku}
        'separator' => '-',
        'include_m1' => true,
        'include_m2' => true,
    ],
];
```

---

## The Beauty: Everything Connects

```
EntitlementService (what features you have access to)
        │
        ▼
PermissionMatrixService (what actions you can take)
        │
        ▼
CommerceMatrixGate Middleware (enforces on every request)
        │
        ▼
Training Mode (learn permissions by using the app)
        │
        ▼
Production Mode (if not trained, it doesn't work)
```

---

## Warehouse & Fulfillment Layer

### The Physical World Connection

```
Web Server
    │
    ├── Remote Print Queue ──────────────────────┐
    │   (No VPN, just one browser tab open)      │
    │                                             ▼
    │                                    ┌──────────────────┐
    │                                    │    Warehouse     │
    │                                    │                  │
    │   Thermal Printer ◄────────────────┤  Shipping Labels │
    │   (Courier-supplied)               │                  │
    │                                    │  Pick/Pack Lists │
    │   Office Jet ◄─────────────────────┤  (Perforated)    │
    │   (Perforated paper)               │                  │
    │                                    │  BOM Sheets      │
    │                                    └──────────────────┘
    │
    └── Warehouse Knowledge
        ├── Product locations (bin/shelf)
        ├── Pick route optimization
        └── Real-time stock positions
```

### Consignment System (Inbound)

**Consignment** = Notification of incoming supply, a pre-arrival order

```php
Schema::create('commerce_consignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');  // M1 usually
    $table->string('reference');                  // Supplier reference / PO number
    $table->foreignId('supplier_id')->nullable();

    // Status flow
    $table->string('status');                     // expected, in_transit, received, processed
    $table->date('expected_date')->nullable();
    $table->timestamp('received_at')->nullable();
    $table->timestamp('processed_at')->nullable();

    // Who handled it
    $table->foreignId('received_by')->nullable()->constrained('users');
    $table->foreignId('processed_by')->nullable()->constrained('users');

    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('commerce_consignment_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('consignment_id')->constrained('commerce_consignments');
    $table->foreignId('product_id')->constrained('commerce_products');

    $table->integer('quantity_expected');
    $table->integer('quantity_received')->default(0);
    $table->decimal('unit_cost', 10, 2)->nullable();

    // Warehouse placement
    $table->string('bin_location')->nullable();   // Where it went
    $table->timestamps();
});
```

### Consignment Processing Flow

```
Consignment Created (PO sent to supplier)
    │
    ▼
Status: EXPECTED
    │ (supplier ships)
    ▼
Status: IN_TRANSIT
    │ (delivery arrives)
    ▼
Status: RECEIVED
    │
    ├── Stock quantities updated
    ├── Back orders checked & processed
    ├── Notifications triggered
    ├── Warehouse locations updated
    │
    ▼
Status: PROCESSED

// When consignment is processed:
foreach ($consignment->items as $item) {
    // Update stock
    $item->product->increment('stock_quantity', $item->quantity_received);

    // Update warehouse location
    WarehouseLocation::updateOrCreate(
        ['product_id' => $item->product_id],
        ['bin' => $item->bin_location, 'quantity' => $newQuantity]
    );

    // Process back orders
    BackOrderService::processForProduct($item->product);

    // Notify watchers
    event(new StockReplenished($item->product, $item->quantity_received));
}
```

### Warehouse Knowledge (Product Locations)

```php
Schema::create('commerce_warehouse_locations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warehouse_id')->constrained('commerce_warehouses');
    $table->foreignId('product_id')->constrained('commerce_products');

    // Physical location
    $table->string('zone')->nullable();           // A, B, C (areas of warehouse)
    $table->string('aisle')->nullable();          // 1, 2, 3
    $table->string('rack')->nullable();           // R1, R2
    $table->string('shelf')->nullable();          // S1, S2
    $table->string('bin')->nullable();            // B1, B2
    $table->string('full_location');              // A-1-R2-S3-B1 (computed)

    // For pick route optimization
    $table->integer('pick_sequence')->default(0); // Order in optimal pick route

    $table->integer('quantity')->default(0);
    $table->integer('min_quantity')->default(0);  // Reorder trigger
    $table->integer('max_quantity')->nullable();  // Bin capacity

    $table->timestamps();

    $table->unique(['warehouse_id', 'product_id']);
    $table->index(['warehouse_id', 'pick_sequence']);
});
```

### Order Batching System

**The Cart/Checkout Flow for Warehouse Staff**

```
Front Office View:
┌─────────────────────────────────────────────────────────────────┐
│  ORDER BATCHING                                    [Create Batch]│
│                                                                  │
│  Filter Orders:                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [x] Contains: Water Butt 500L    [ ] Contains: Compost     ││
│  │ [x] Ready to ship                [ ] Has back-ordered items ││
│  │ [ ] Priority orders              [x] Standard delivery      ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌──────┬─────────────────────────────────────────────┬────────┐│
│  │  □   │ #10234 - John Smith - 3 items               │ £45.00 ││
│  │  ☑   │ #10235 - Jane Doe - 1 item (WB500L)        │ £29.99 ││
│  │  ☑   │ #10236 - Bob Wilson - 2 items (WB500L, x2) │ £59.98 ││
│  │  □   │ #10237 - Sue Brown - 5 items                │ £89.50 ││
│  │  ☑   │ #10238 - Tom Jones - 1 item (WB500L)       │ £29.99 ││
│  └──────┴─────────────────────────────────────────────┴────────┘│
│                                                                  │
│  Selected: 3 orders, 4x WB500L, 0x other                        │
│                                                                  │
│  [Add to Batch]  [Clear Selection]                              │
└─────────────────────────────────────────────────────────────────┘
```

### Batch / Pick List Generation

```php
Schema::create('commerce_pick_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');
    $table->foreignId('warehouse_id')->constrained('commerce_warehouses');
    $table->foreignId('created_by')->constrained('users');        // Front office

    $table->string('reference');                  // BATCH-20241231-001
    $table->string('status');                     // created, picking, picked, packing, shipped

    // Assignment
    $table->foreignId('assigned_to')->nullable()->constrained('users'); // Warehouse picker

    // Timestamps
    $table->timestamp('picking_started_at')->nullable();
    $table->timestamp('picking_completed_at')->nullable();
    $table->timestamp('packing_started_at')->nullable();
    $table->timestamp('shipped_at')->nullable();

    $table->timestamps();
});

Schema::create('commerce_pick_batch_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('batch_id')->constrained('commerce_pick_batches');
    $table->foreignId('order_id')->constrained('commerce_orders');

    $table->integer('sequence');                  // Order within batch
    $table->string('status');                     // pending, picked, packed, shipped

    $table->timestamps();
});
```

### BOM (Bill of Materials) - The Pick List

When batch is finalized, generate the BOM:

```php
// app/Services/Commerce/PickListService.php

class PickListService
{
    public function generateBOM(PickBatch $batch): BillOfMaterials
    {
        $items = collect();

        // Aggregate all products across all orders in batch
        foreach ($batch->orders as $batchOrder) {
            foreach ($batchOrder->order->items as $orderItem) {
                $key = $orderItem->product_id;

                if ($items->has($key)) {
                    $items[$key]['quantity'] += $orderItem->quantity;
                    $items[$key]['orders'][] = $batchOrder->order_id;
                } else {
                    $items[$key] = [
                        'product' => $orderItem->product,
                        'quantity' => $orderItem->quantity,
                        'orders' => [$batchOrder->order_id],
                        'location' => $orderItem->product->warehouseLocation,
                    ];
                }
            }
        }

        // Sort by pick sequence (optimal route through warehouse)
        $items = $items->sortBy(fn ($item) => $item['location']->pick_sequence);

        return new BillOfMaterials(
            batch: $batch,
            items: $items,
            generated_at: now()
        );
    }
}
```

### Print Queue System

**The Magic: Web Server → Physical Printers, No VPN**

```php
Schema::create('commerce_print_queue', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_id')->constrained('commerce_entities');
    $table->foreignId('warehouse_id')->constrained('commerce_warehouses');

    // What to print
    $table->string('document_type');              // shipping_label, pick_list, bom, packing_slip
    $table->string('document_id');                // Reference to source
    $table->foreignId('batch_id')->nullable();
    $table->foreignId('order_id')->nullable();

    // Printer target
    $table->string('printer_id');                 // thermal_1, officejet_1
    $table->string('printer_type');               // thermal, inkjet, laser

    // Content
    $table->text('content')->nullable();          // Raw print data or template ref
    $table->string('template')->nullable();       // blade template name
    $table->json('template_data')->nullable();    // Data for template

    // Status
    $table->string('status')->default('queued');  // queued, printing, printed, failed
    $table->timestamp('printed_at')->nullable();
    $table->text('error')->nullable();

    // Who requested
    $table->foreignId('requested_by')->constrained('users');
    $table->timestamps();

    $table->index(['warehouse_id', 'status']);
    $table->index(['printer_id', 'status']);
});
```

### Print Client (Browser Tab)

```javascript
// resources/js/warehouse-print-client.js
// This runs in ONE browser tab in the warehouse

class WarehousePrintClient {
    constructor(warehouseId) {
        this.warehouseId = warehouseId;
        this.printers = new Map();
        this.polling = false;
    }

    async registerPrinter(printerId, printerType, nativeHandle) {
        // nativeHandle could be:
        // - Web USB API for thermal printers
        // - Window.print() for regular printers
        // - CUPS/IPP endpoint for network printers
        this.printers.set(printerId, { type: printerType, handle: nativeHandle });

        await fetch('/api/warehouse/printers/register', {
            method: 'POST',
            body: JSON.stringify({
                warehouse_id: this.warehouseId,
                printer_id: printerId,
                printer_type: printerType,
                capabilities: this.getCapabilities(nativeHandle)
            })
        });
    }

    startPolling() {
        this.polling = true;
        this.poll();
    }

    async poll() {
        if (!this.polling) return;

        try {
            const response = await fetch(`/api/warehouse/${this.warehouseId}/print-queue`);
            const jobs = await response.json();

            for (const job of jobs) {
                await this.processJob(job);
            }
        } catch (e) {
            console.error('Print poll failed:', e);
        }

        // Poll every 2 seconds
        setTimeout(() => this.poll(), 2000);
    }

    async processJob(job) {
        const printer = this.printers.get(job.printer_id);
        if (!printer) {
            await this.markFailed(job.id, 'Printer not connected');
            return;
        }

        try {
            await this.markPrinting(job.id);

            if (job.document_type === 'shipping_label') {
                await this.printLabel(printer, job);
            } else {
                await this.printDocument(printer, job);
            }

            await this.markPrinted(job.id);
        } catch (e) {
            await this.markFailed(job.id, e.message);
        }
    }

    async printLabel(printer, job) {
        // For thermal printers - ZPL or EPL format
        if (printer.type === 'thermal') {
            const zpl = await this.fetchLabelZPL(job.document_id);
            await printer.handle.write(zpl);
        }
    }

    async printDocument(printer, job) {
        // For regular printers - open in iframe, trigger print
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = `/warehouse/print-preview/${job.document_type}/${job.document_id}`;
        document.body.appendChild(iframe);

        iframe.onload = () => {
            iframe.contentWindow.print();
            setTimeout(() => iframe.remove(), 5000);
        };
    }
}

// Initialize
const client = new WarehousePrintClient(WAREHOUSE_ID);
client.registerPrinter('thermal_1', 'thermal', thermalPrinterUSB);
client.registerPrinter('officejet_1', 'inkjet', null); // Uses window.print()
client.startPolling();
```

### Batch Workflow (Front Office → Warehouse)

```
FRONT OFFICE                          WAREHOUSE
─────────────                         ─────────

1. Filter orders by criteria
   (product X, ready to ship)
        │
        ▼
2. Select orders into batch
   (cart-style UI)
        │
        ▼
3. Create batch
   [Create Batch] ──────────────────► Batch appears in
        │                              warehouse queue
        ▼
4. System generates:                        │
   - BOM (aggregated products)              │
   - Pick list (sorted by location)         │
   - Shipping labels (all at once)          │
        │                                   │
        ▼                                   ▼
5. Print jobs queued ──────────────► 6. Print client receives
        │                               - Pick list (perforated)
        │                               - Shipping labels (thermal)
        │                               - BOM sheet
        │                                   │
        │                                   ▼
        │                            7. Picker follows route
        │                               (optimized by pick_sequence)
        │                                   │
        │                                   ▼
        │                            8. Each order packed
        │                               Label applied
        │                               Marked complete
        │                                   │
        ▼                                   ▼
9. Status updates in real-time       10. Batch complete
   (front office sees progress)          Carrier pickup scheduled
```

### Document Templates

```blade
{{-- resources/views/warehouse/documents/pick-list.blade.php --}}
{{-- Designed for perforated paper - each order on tearable section --}}

@foreach($batch->orders as $batchOrder)
<div class="pick-slip" style="page-break-after: always; border-bottom: 1px dashed #000;">
    <div class="header">
        <strong>Order #{{ $batchOrder->order->number }}</strong>
        <span>{{ $batchOrder->sequence }} of {{ $batch->orders->count() }}</span>
    </div>

    <div class="customer">
        {{ $batchOrder->order->shipping_name }}<br>
        {{ $batchOrder->order->shipping_address }}
    </div>

    <table class="items">
        <tr>
            <th>Location</th>
            <th>SKU</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Picked</th>
        </tr>
        @foreach($batchOrder->order->items->sortBy('product.warehouseLocation.pick_sequence') as $item)
        <tr>
            <td class="location">{{ $item->product->warehouseLocation->full_location }}</td>
            <td>{{ $item->sku }}</td>
            <td>{{ $item->product->name }}</td>
            <td class="qty">{{ $item->quantity }}</td>
            <td class="checkbox">☐</td>
        </tr>
        @endforeach
    </table>

    <div class="notes">
        @if($batchOrder->order->notes)
        <strong>Notes:</strong> {{ $batchOrder->order->notes }}
        @endif
    </div>

    {{-- Tear line --}}
    <div class="tear-line">✂ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─</div>
</div>
@endforeach
```

```blade
{{-- resources/views/warehouse/documents/bom.blade.php --}}
{{-- Aggregated Bill of Materials for entire batch --}}

<div class="bom-sheet">
    <h1>Bill of Materials - Batch {{ $batch->reference }}</h1>
    <p>{{ $batch->orders->count() }} orders | Generated {{ now()->format('Y-m-d H:i') }}</p>

    <table>
        <tr>
            <th>Pick Seq</th>
            <th>Location</th>
            <th>SKU</th>
            <th>Product</th>
            <th>Total Qty</th>
            <th>For Orders</th>
        </tr>
        @foreach($bom->items as $item)
        <tr>
            <td>{{ $item['location']->pick_sequence }}</td>
            <td class="location">{{ $item['location']->full_location }}</td>
            <td>{{ $item['product']->master_sku }}</td>
            <td>{{ $item['product']->name }}</td>
            <td class="qty">{{ $item['quantity'] }}</td>
            <td class="orders">{{ implode(', ', array_map(fn($id) => "#{$id}", $item['orders'])) }}</td>
        </tr>
        @endforeach
    </table>

    <div class="totals">
        <strong>Total Items:</strong> {{ $bom->items->sum('quantity') }}<br>
        <strong>Unique Products:</strong> {{ $bom->items->count() }}
    </div>
</div>
```

### Back Order Processing

```php
// app/Services/Commerce/BackOrderService.php

class BackOrderService
{
    /**
     * When consignment arrives, process back orders
     */
    public static function processForProduct(Product $product): void
    {
        $availableStock = $product->stock_quantity;

        // Get back orders, oldest first
        $backOrders = OrderItem::where('product_id', $product->id)
            ->where('status', 'back_ordered')
            ->orderBy('created_at')
            ->with('order')
            ->get();

        foreach ($backOrders as $item) {
            if ($availableStock >= $item->quantity) {
                // Can fulfill this back order
                $item->update(['status' => 'ready']);
                $availableStock -= $item->quantity;

                // Notify customer
                event(new BackOrderReady($item->order, $item));

                // Check if full order is now ready
                if ($item->order->items->every(fn ($i) => $i->status === 'ready')) {
                    $item->order->update(['status' => 'ready_to_ship']);
                    event(new OrderReadyToShip($item->order));
                }
            } else {
                // Partial or none - stop processing
                break;
            }
        }

        // Update product stock (may have allocated some)
        $product->update(['stock_quantity' => $availableStock]);
    }
}
```

---

## The Complete Flow

```
           ┌─────────────────────────────────────────────────────────────────┐
           │                      INBOUND (Consignment)                       │
           │                                                                  │
           │  PO Created → Expected → In Transit → Received → Processed      │
           │                                            │                     │
           │                                            ▼                     │
           │                                    Stock Updated                 │
           │                                    Back Orders Processed         │
           │                                    Warehouse Locations Set       │
           └──────────────────────────────────────┬──────────────────────────┘
                                                  │
┌─────────────────────────────────────────────────┼──────────────────────────────────────────────────┐
│                                                 │           COMMERCE                               │
│                                                 ▼                                                  │
│  Customer Order ──► Permission Matrix Check ──► Order Created ──► Awaiting Fulfillment           │
│       │                                              │                                             │
│       │ (M1-M2-SKU tracking)                        │                                             │
│       ▼                                              ▼                                             │
│  Multi-Entity                                   Front Office                                      │
│  Visibility                                     Batching UI                                       │
└──────────────────────────────────────────────────┬─────────────────────────────────────────────────┘
                                                   │
           ┌───────────────────────────────────────┼────────────────────────────┐
           │                                       │        FULFILLMENT         │
           │                                       ▼                            │
           │  Batch Created ──► BOM Generated ──► Print Queue ──► Warehouse    │
           │       │                                    │                       │
           │       │                                    ▼                       │
           │       │                           ┌─────────────────┐              │
           │       │                           │ Print Client    │              │
           │       │                           │ (1 browser tab) │              │
           │       │                           └────────┬────────┘              │
           │       │                                    │                       │
           │       │                    ┌───────────────┼───────────────┐       │
           │       │                    ▼               ▼               ▼       │
           │       │              Pick List        BOM Sheet      Labels        │
           │       │             (perforated)                   (thermal)       │
           │       │                    │               │               │       │
           │       │                    └───────────────┼───────────────┘       │
           │       │                                    ▼                       │
           │       │                              PICK → PACK → SHIP            │
           │       │                                    │                       │
           │       ▼                                    ▼                       │
           │  Status Updates ◄──────────────── Batch Complete                  │
           │  (real-time to front office)                                      │
           └────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: Core Matrix
- [ ] Entity hierarchy (M1 → M2 → M3)
- [ ] Permission matrix table
- [ ] PermissionMatrixService
- [ ] Basic can() checks

### Phase 2: WAF Integration
- [ ] CommerceMatrixGate middleware
- [ ] Request logging
- [ ] Action resolution (route → permission key)

### Phase 3: Training Mode
- [ ] Training UI (the click-to-allow modal)
- [ ] Permission discovery
- [ ] Bulk training tools

### Phase 4: Product Catalog
- [ ] Master catalog (M1)
- [ ] Facade selection (M2)
- [ ] Dropship inheritance (M3)
- [ ] SKU lineage

### Phase 5: Order Flow
- [ ] Multi-entity order creation
- [ ] Fulfillment routing
- [ ] Entity-scoped reporting

### Phase 6: Production Hardening
- [ ] Strict mode enforcement
- [ ] Audit logging
- [ ] Permission export/import (for deployment)

### Phase 7: Entitlement Integration

Commerce-Entitlement lifecycle synchronisation. Connects payment events to workspace feature access.

**Current State (Implemented):**
- [x] `CommerceService->fulfillOrder()` provisions packages via `EntitlementService->provisionPackage()`
- [x] Stripe/BTCPay webhooks trigger entitlement provisioning on checkout complete
- [x] Subscription cancellation revokes entitlements via `ProvisionSocialHostSubscription` listener

**Gaps to Address:**

#### 7.1 Payment Failure Handling
- [ ] `StripeWebhookController->handleInvoicePaymentFailed()` → suspend entitlements
- [ ] `BTCPayWebhookController` payment expiry → suspend entitlements
- [ ] Grace period configuration (default: 3 days)
- [ ] `EntitlementService->suspendWorkspace()` integration

#### 7.2 Dunning Workflow
- [ ] Failed payment notification sequence (Day 0, 3, 7, 14)
- [ ] Automatic retry scheduling
- [ ] `WorkspaceSuspended` event dispatch
- [ ] Admin visibility into suspended workspaces

#### 7.3 Event-Driven Architecture
- [ ] Webhooks dispatch domain events (not inline processing)
- [ ] Listeners handle business logic independently
- [ ] `PaymentCompleted`, `PaymentFailed`, `SubscriptionRenewed` events
- [ ] Decoupled from specific payment provider

#### 7.4 Legacy Cleanup
- [ ] Remove `BlestaWebhookController` (unused)
- [ ] Remove `BlestaApiAuth` middleware
- [ ] Remove Blesta routes from `routes/api.php`
- [ ] Remove `config/blesta.php`
- [ ] Clean up any Blesta references in codebase

#### 7.5 Testing
- [ ] Payment failure → suspension flow tests
- [ ] Dunning sequence tests
- [ ] Grace period expiry tests
- [ ] Multi-provider webhook tests (Stripe, BTCPay)

---

---

## SKU System: One Trip or Go Home

### Philosophy

Every scan tells you everything. No lookups. No mistakes. One barcode = complete fulfillment knowledge.

### Compound SKU Format

```
SKU-<opt>~<val>*<qty>[-<opt>~<val>*<qty>]...

Where:
  SKU       = Base product identifier
  -         = Option separator
  <opt>     = Option code (color, size, ram, cover, etc.)
  ~         = Value indicator
  <val>     = Option value (black, XL, 16gb, etc.)
  *         = Quantity indicator (optional, default 1)
  <qty>     = Count of this option
```

### Examples

```
# Simple product with options
LAPTOP-ram~16gb-ssd~512gb

# Product with multiple of an accessory option
LAPTOP-ram~16gb-ssd~512gb-cover~black*2

# Multiple separate items (comma-separated)
LAPTOP-ram~16gb,HDMI-length~2m,MOUSE-color~black

# Bundle (pipe-separated = discounted group)
LAPTOP-ram~16gb|MOUSE-color~black|PAD-size~xl
```

### Bundle Detection & Pricing

The `|` character binds products into a bundle with potential price override.

```
┌──────────────────────────────────────────────────────────────┐
│  Input: LAPTOP-ram~16gb|MOUSE-color~black|PAD-size~xl        │
│                                                              │
│  Step 1: Detect Bundle (found |)                             │
│                                                              │
│  Step 2: Strip Human Choices                                 │
│          → LAPTOP|MOUSE|PAD                                  │
│                                                              │
│  Step 3: Hash the Raw Combination                            │
│          → hash("LAPTOP|MOUSE|PAD") = "abc123"               │
│                                                              │
│  Step 4: Lookup Bundle Discount                              │
│          → bundle_hashes["abc123"] = "CYBERMON20" coupon     │
│          → Apply 20% bundle discount                         │
│                                                              │
│  Step 5: Process Remainders                                  │
│          → ram~16gb, color~black, size~xl                    │
│          → Feed into additional pricing rules                │
│          → (BOGO, volume discounts, upsell triggers)         │
└──────────────────────────────────────────────────────────────┘
```

### Bundle Hash Table

```php
// commerce_bundle_hashes
Schema::create('commerce_bundle_hashes', function (Blueprint $table) {
    $table->id();
    $table->string('hash', 64)->unique();       // SHA256 of sorted base SKUs
    $table->string('base_skus');                // "LAPTOP|MOUSE|PAD" (for debugging)
    $table->string('coupon_code')->nullable();  // CYBERMON20
    $table->decimal('fixed_price')->nullable(); // Or fixed bundle price
    $table->decimal('discount_percent')->nullable();
    $table->decimal('discount_amount')->nullable();
    $table->unsignedBigInteger('entity_id');    // M1/M2/M3 scope
    $table->boolean('active')->default(true);
    $table->timestamps();

    $table->index(['entity_id', 'active']);
});
```

### SKU Parser Service

```php
class SkuParserService
{
    /**
     * Parse a compound SKU string into structured data
     */
    public function parse(string $compoundSku): SkuParseResult
    {
        // Split by comma for multiple items
        $items = explode(',', $compoundSku);

        $parsedItems = [];
        $currentBundle = [];

        foreach ($items as $item) {
            // Check for bundle separator
            if (str_contains($item, '|')) {
                $bundleParts = explode('|', $item);
                foreach ($bundleParts as $part) {
                    $currentBundle[] = $this->parseItem($part);
                }
                $parsedItems[] = new BundleItem(
                    items: $currentBundle,
                    hash: $this->hashBundle($currentBundle)
                );
                $currentBundle = [];
            } else {
                $parsedItems[] = $this->parseItem($item);
            }
        }

        return new SkuParseResult($parsedItems);
    }

    /**
     * Parse single item: SKU-opt~val*qty-opt~val*qty
     */
    protected function parseItem(string $item): ParsedItem
    {
        $parts = explode('-', $item, 2);
        $baseSku = $parts[0];
        $options = [];

        if (isset($parts[1])) {
            // Split remaining by - for each option
            preg_match_all('/([a-z_]+)~([^-*]+)(?:\*(\d+))?/i', $parts[1], $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $options[] = new SkuOption(
                    code: $match[1],
                    value: $match[2],
                    quantity: isset($match[3]) ? (int)$match[3] : 1
                );
            }
        }

        return new ParsedItem(
            baseSku: $baseSku,
            options: $options
        );
    }

    /**
     * Hash bundle for discount lookup (strips human choices)
     */
    protected function hashBundle(array $items): string
    {
        $baseSkus = collect($items)
            ->map(fn($item) => $item->baseSku)
            ->sort()
            ->implode('|');

        return hash('sha256', $baseSkus);
    }
}
```

### SKU Builder Service

```php
class SkuBuilderService
{
    /**
     * Build compound SKU from cart/order data
     */
    public function build(array $lineItems): string
    {
        $skuParts = [];

        foreach ($lineItems as $item) {
            $sku = $item['base_sku'];

            // Add options
            foreach ($item['options'] ?? [] as $option) {
                $sku .= "-{$option['code']}~{$option['value']}";
                if (($option['quantity'] ?? 1) > 1) {
                    $sku .= "*{$option['quantity']}";
                }
            }

            $skuParts[] = $sku;
        }

        // If bundle, join with |
        if ($this->isBundle($lineItems)) {
            return implode('|', $skuParts);
        }

        // Otherwise comma-separate
        return implode(',', $skuParts);
    }

    /**
     * Generate bundle hash for coupon creation
     */
    public function generateBundleHash(array $baseSkus): string
    {
        sort($baseSkus);
        return hash('sha256', implode('|', $baseSkus));
    }
}
```

### Pricing Pipeline

```php
class SkuPricingService
{
    public function calculatePrice(SkuParseResult $parsed, Entity $entity): PricingResult
    {
        $lines = [];
        $bundleDiscounts = [];

        foreach ($parsed->items as $item) {
            if ($item instanceof BundleItem) {
                // Look up bundle discount
                $bundle = CommerceBundleHash::where('hash', $item->hash)
                    ->where('entity_id', $entity->id)
                    ->where('active', true)
                    ->first();

                if ($bundle) {
                    $bundleDiscounts[] = new BundleDiscount(
                        items: $item->items,
                        couponCode: $bundle->coupon_code,
                        discountPercent: $bundle->discount_percent,
                        discountAmount: $bundle->discount_amount,
                        fixedPrice: $bundle->fixed_price
                    );
                }

                // Price individual items within bundle
                foreach ($item->items as $bundledItem) {
                    $lines[] = $this->priceItem($bundledItem, $entity);
                }
            } else {
                $lines[] = $this->priceItem($item, $entity);
            }
        }

        // Apply bundle discounts
        foreach ($bundleDiscounts as $discount) {
            $lines = $this->applyBundleDiscount($lines, $discount);
        }

        // Apply remainder-based rules (BOGO, volume, etc.)
        $lines = $this->applyQuantityRules($lines, $entity);

        return new PricingResult($lines, $bundleDiscounts);
    }

    protected function priceItem(ParsedItem $item, Entity $entity): PricedLine
    {
        $product = $this->findProduct($item->baseSku, $entity);
        $basePrice = $product->getPrice($entity);

        // Add option modifiers
        foreach ($item->options as $option) {
            $modifier = $product->getOptionModifier($option->code, $option->value);
            $basePrice += ($modifier * $option->quantity);
        }

        return new PricedLine($item, $basePrice);
    }
}
```

### Warehouse Integration

The compound SKU becomes the pick instruction:

```
┌─────────────────────────────────────────────────────────────────────┐
│ PICK LIST                                                           │
│ Order: #12345                                                       │
│─────────────────────────────────────────────────────────────────────│
│                                                                     │
│ [■■■■■■■■■■■■] LAPTOP-ram~16gb-ssd~512gb-cover~black*2              │
│     │                                                               │
│     ├─ LAPTOP (A-12-3-1)        ← bin location                     │
│     │   └─ Option: ram~16gb      ← pre-configured variant          │
│     │   └─ Option: ssd~512gb                                       │
│     │                                                               │
│     └─ COVER-BLACK (B-02-1-5) × 2  ← separate pick, quantity 2     │
│                                                                     │
│─────────────────────────────────────────────────────────────────────│
│ Bundle: LAPTOP|MOUSE|PAD  →  CYBERMON20 applied                    │
└─────────────────────────────────────────────────────────────────────┘
```

### Option Types

Options can represent different things:

```php
// Option type definitions
enum OptionType: string
{
    case VARIANT = 'variant';      // ram~16gb (pre-built into product)
    case ACCESSORY = 'accessory';  // cover~black (separate SKU to pick)
    case SERVICE = 'service';      // warranty~3yr (no physical pick)
    case CUSTOMIZATION = 'custom'; // engrave~"John" (requires action)
}

// Option registry
class SkuOptionRegistry
{
    protected array $options = [
        'ram' => ['type' => 'variant', 'affects_price' => true],
        'ssd' => ['type' => 'variant', 'affects_price' => true],
        'color' => ['type' => 'variant', 'affects_price' => false],
        'size' => ['type' => 'variant', 'affects_price' => true],
        'cover' => ['type' => 'accessory', 'sku_prefix' => 'COVER-'],
        'case' => ['type' => 'accessory', 'sku_prefix' => 'CASE-'],
        'warranty' => ['type' => 'service', 'affects_price' => true],
        'engrave' => ['type' => 'custom', 'requires_input' => true],
    ];

    public function resolveOption(string $code, string $value): ResolvedOption
    {
        $config = $this->options[$code] ?? throw new UnknownOptionException($code);

        return match($config['type']) {
            'variant' => new VariantOption($code, $value),
            'accessory' => new AccessoryOption(
                $code,
                $value,
                sku: $config['sku_prefix'] . strtoupper($value)
            ),
            'service' => new ServiceOption($code, $value),
            'custom' => new CustomOption($code, $value),
        };
    }
}
```

### M1-M2-M3 Integration

The compound SKU carries entity lineage:

```
Full SKU with Lineage:
ORGORG-WBUTS-WB500L-color~green-stand~oak*2

Where:
  ORGORG  = M1 (Original Organics - master)
  WBUTS   = M2 (Waterbutts.com - storefront)
  WB500L  = Base product
  -color~green-stand~oak*2 = Options
```

The entity prefix enables:
- **Routing**: Order goes to correct fulfillment center
- **Reporting**: Sales attributed to correct facade
- **Pricing**: Entity-specific pricing rules applied
- **Permissions**: Entity-specific product availability checked

---

## Session State Summary

**What's Captured:**
1. ✅ M1 → M2 → M3 Entity Hierarchy (master, facades, dropshippers)
2. ✅ Permission Matrix (top-down immutable, cascading locks)
3. ✅ Integrated WAF with Training Mode (click-to-allow, production strict)
4. ✅ Product Catalog (master + facade selection + dropship inheritance)
5. ✅ Content Override Matrix (sparse overrides, runtime resolution, white-label engine)
6. ✅ Consignment System (inbound supply, auto stock updates, back order processing)
7. ✅ Warehouse Knowledge (locations, pick sequences, bin tracking)
8. ✅ Order Batching (front office cart-style selection)
9. ✅ BOM/Pick List Generation (aggregated, route-optimized)
10. ✅ Remote Print Queue (thermal labels, perforated pick lists, no VPN)
11. ✅ Back Order Auto-Processing (FIFO fulfillment on stock arrival)
12. ✅ SKU Encoding System ("one trip or go home")
    - Compound SKU format: `SKU-opt~val*qty`
    - Bundle detection via `|` separator
    - Bundle hash → coupon lookup (strip human choices, hash base SKUs)
    - Option types: variant, accessory, service, customization
    - Entity lineage prefix: M1-M2-SKU-options

**Pricing Note:**
Pricing is NOT a separate system. It's the intersection of:
- Permission Matrix (can_discount, max_discount_percent, can_sell_below_wholesale)
- Content Overrides (sparse price overrides per entity)
- SKU System (bundle hashes, option modifiers, volume rules)

No separate pricing engine needed. Primitives compose.

**Parked for Future:**
- Carrier integrations ("Night Fright" and friends)
- Returns flow
- Financial reconciliation per entity

---

*Created: 2024-12-31*
*Updated: 2024-12-31*
*Status: Core Vision Captured - Ready for Implementation Planning*
*Origin: The 2008 System That Was Ahead of Its Time*
