<?php

declare(strict_types=1);

/**
 * Commerce module translations (en_GB).
 *
 * Key structure: section.subsection.key
 */

return [
    // Dashboard
    'dashboard' => [
        'title' => 'Commerce Dashboard',
        'subtitle' => 'Revenue overview and order management',
    ],

    // Common actions
    'actions' => [
        'view_orders' => 'View Orders',
        'add_product' => 'Add Product',
        'new_coupon' => 'New Coupon',
        'new_entity' => 'New M1 Entity',
        'add_permission' => 'Add Permission',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'save' => 'Save',
        'create' => 'Create',
        'update' => 'Update',
        'assign' => 'Assign Product',
        'entity_hierarchy' => 'Entity Hierarchy',
    ],

    // Common sections
    'sections' => [
        'quick_actions' => 'Quick Actions',
        'recent_orders' => 'Recent Orders',
    ],

    // Common table columns
    'table' => [
        'order' => 'Order',
        'workspace' => 'Workspace',
        'status' => 'Status',
        'total' => 'Total',
        'product' => 'Product',
        'sku' => 'SKU',
        'price' => 'Price',
        'stock' => 'Stock',
        'assignments' => 'Assignments',
        'actions' => 'Actions',
    ],

    // Common filters
    'filters' => [
        'entity' => 'Entity',
        'all_entities' => 'All Entities',
        'search' => 'Search',
        'search_placeholder' => 'Search by name or SKU...',
        'category' => 'Category',
        'all_categories' => 'All Categories',
        'stock_status' => 'Stock Status',
        'all' => 'All',
        'in_stock' => 'In Stock',
        'low_stock' => 'Low Stock',
        'out_of_stock' => 'Out of Stock',
        'backorder' => 'Backorder',
        'status' => 'Status',
    ],

    // Common form fields
    'form' => [
        'sku' => 'SKU',
        'type' => 'Type',
        'name' => 'Name',
        'description' => 'Description',
        'category' => 'Category',
        'subcategory' => 'Subcategory',
        'price' => 'Price (pence)',
        'cost_price' => 'Cost Price',
        'rrp' => 'RRP',
        'stock_quantity' => 'Stock Quantity',
        'low_stock_threshold' => 'Low Stock Threshold',
        'tax_class' => 'Tax Class',
        'track_stock' => 'Track stock',
        'allow_backorder' => 'Allow backorder',
        'active' => 'Active',
        'featured' => 'Featured',
        'visible' => 'Visible',
        'code' => 'Code',
        'currency' => 'Currency',
        'timezone' => 'Timezone',
        'domain' => 'Domain (optional)',
        'linked_workspace' => 'Linked Workspace (optional)',
    ],

    // Product types
    'product_types' => [
        'simple' => 'Simple',
        'variable' => 'Variable',
        'bundle' => 'Bundle',
        'virtual' => 'Virtual',
        'subscription' => 'Subscription',
    ],

    // Tax classes
    'tax_classes' => [
        'standard' => 'Standard (20%)',
        'reduced' => 'Reduced (5%)',
        'zero' => 'Zero (0%)',
        'exempt' => 'Exempt',
    ],

    // Products
    'products' => [
        'title' => 'Product Catalog',
        'subtitle' => 'Manage master product catalog and entity assignments',
        'empty' => 'No products found for this entity.',
        'empty_no_entity' => 'Select an entity to view products.',
        'create_first' => 'Create your first product',
        'units' => 'units',
        'not_tracked' => 'Not tracked',
        'uncategorised' => 'Uncategorised',
        'entities' => 'entities',
        'modal' => [
            'create_title' => 'Create Product',
            'edit_title' => 'Edit Product',
        ],
        'actions' => [
            'create' => 'Create Product',
            'update' => 'Update Product',
            'delete_confirm' => 'Delete this product?',
        ],
    ],

    // Product assignments
    'assignments' => [
        'title' => 'Assign Product to Entity',
        'entity' => 'Entity',
        'select_entity' => 'Select entity...',
        'price_override' => 'Price Override (pence)',
        'price_placeholder' => 'Leave blank for default',
        'margin_percent' => 'Margin %',
        'name_override' => 'Name Override',
        'name_placeholder' => 'Leave blank for default',
    ],

    // Orders
    'orders' => [
        'title' => 'Orders',
        'subtitle' => 'Manage customer orders',
        'empty' => 'No orders found.',
        'empty_dashboard' => 'No orders yet',
        'search_placeholder' => 'Search orders...',
        'all_statuses' => 'All statuses',
        'all_types' => 'All types',
        'all_time' => 'All time',
        'date_range' => [
            'today' => 'Today',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
        ],
        'detail' => [
            'summary' => 'Order Summary',
            'totals' => 'Order Totals',
            'status' => 'Status',
            'type' => 'Type',
            'payment_gateway' => 'Payment Gateway',
            'paid_at' => 'Paid At',
            'not_paid' => 'Not paid',
            'customer' => 'Customer Information',
            'name' => 'Name',
            'email' => 'Email',
            'workspace' => 'Workspace',
            'items' => 'Order Items',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'tax' => 'Tax',
            'total' => 'Total',
            'invoice' => 'Invoice',
            'view_invoice' => 'View Invoice',
        ],
        'update_status' => [
            'title' => 'Update Order Status',
            'new_status' => 'New Status',
            'note' => 'Note (optional)',
            'note_placeholder' => 'Reason for status change...',
        ],
    ],

    // Subscriptions
    'subscriptions' => [
        'title' => 'Subscriptions',
        'subtitle' => 'Manage workspace subscriptions',
        'empty' => 'No subscriptions found.',
        'search_placeholder' => 'Search workspaces...',
        'all_statuses' => 'All statuses',
        'all_gateways' => 'All gateways',
        'detail' => [
            'title' => 'Subscription Details',
            'summary' => 'Subscription Summary',
            'status' => 'Status',
            'gateway' => 'Gateway',
            'billing_cycle' => 'Billing Cycle',
            'created' => 'Created',
            'workspace' => 'Workspace',
            'package' => 'Package',
            'current_period' => 'Billing Period',
            'billing_progress' => 'Billing Progress',
            'days_remaining' => 'days remaining',
            'start' => 'Start',
            'end' => 'End',
            'gateway_details' => 'Gateway Details',
            'subscription_id' => 'Subscription ID',
            'customer_id' => 'Customer ID',
            'price_id' => 'Price ID',
            'cancellation' => 'Cancellation',
            'cancelled_at' => 'Cancelled at',
            'reason' => 'Reason',
            'ended_at' => 'Ended at',
            'will_end_at_period_end' => 'Will end at period end',
            'trial' => 'Trial Period',
            'trial_ends' => 'Ends',
        ],
        'update_status' => [
            'title' => 'Update Subscription Status',
            'workspace' => 'Workspace',
            'new_status' => 'New Status',
            'note' => 'Note (optional)',
            'note_placeholder' => 'Reason for status change...',
        ],
        'extend' => [
            'title' => 'Extend Subscription Period',
            'current_period_ends' => 'Current Period Ends',
            'extend_by_days' => 'Extend by (days)',
            'new_end_date' => 'New end date',
            'action' => 'Extend Period',
        ],
    ],

    // Coupons
    'coupons' => [
        'title' => 'Coupons',
        'subtitle' => 'Manage discount codes',
        'empty' => 'No coupons found. Create your first coupon to get started.',
        'search_placeholder' => 'Search codes or names...',
        'all_coupons' => 'All coupons',
        'modal' => [
            'create_title' => 'Create Coupon',
            'edit_title' => 'Edit Coupon',
        ],
        'sections' => [
            'basic_info' => 'Basic Information',
            'discount_settings' => 'Discount Settings',
            'applicability' => 'Applicability',
            'usage_limits' => 'Usage Limits',
            'validity_period' => 'Validity Period',
        ],
        'form' => [
            'code' => 'Code',
            'code_placeholder' => 'SUMMER2025',
            'name' => 'Name',
            'name_placeholder' => 'Summer Sale',
            'description' => 'Description (optional)',
            'discount_type' => 'Discount Type',
            'percentage' => 'Percentage (%)',
            'fixed_amount' => 'Fixed amount (GBP)',
            'discount_percent' => 'Discount %',
            'discount_amount' => 'Discount amount',
            'min_amount' => 'Minimum order amount (optional)',
            'max_discount' => 'Maximum discount (optional)',
            'no_limit' => 'No limit',
            'applies_to' => 'Applies to',
            'all_packages' => 'All packages',
            'specific_packages' => 'Specific packages',
            'packages' => 'Packages',
            'max_uses' => 'Max total uses (optional)',
            'unlimited' => 'Unlimited',
            'max_uses_per_workspace' => 'Max per workspace',
            'duration' => 'Duration',
            'apply_once' => 'Apply once',
            'apply_repeating' => 'Apply for X months',
            'apply_forever' => 'Apply forever',
            'duration_months' => 'Number of months',
            'valid_from' => 'Valid from (optional)',
            'valid_until' => 'Valid until (optional)',
        ],
        'actions' => [
            'create' => 'Create Coupon',
            'update' => 'Update Coupon',
        ],
        'bulk' => [
            'generate_button' => 'Bulk Generate',
            'modal_title' => 'Bulk Generate Coupons',
            'generation_settings' => 'Generation Settings',
            'count' => 'Number of coupons',
            'code_prefix' => 'Code prefix (optional)',
            'code_prefix_placeholder' => 'PROMO',
            'generate_action' => 'Generate Coupons',
            'generated' => ':count coupon(s) generated successfully.',
        ],
    ],

    // Entities
    'entities' => [
        'title' => 'Commerce Entities',
        'subtitle' => 'Manage M1/M2/M3 entity hierarchy',
        'empty' => 'No entities yet',
        'create_first' => 'Create your first M1 entity',
        'hierarchy' => 'Entity Hierarchy',
        'stats' => [
            'total' => 'Total Entities',
            'm1_masters' => 'M1 Masters',
            'm2_facades' => 'M2 Facades',
            'm3_dropshippers' => 'M3 Dropshippers',
            'active' => 'Active',
        ],
        'types' => [
            'm1' => 'M1 (Master)',
            'm2' => 'M2 (Facade)',
            'm3' => 'M3 (Dropshipper)',
        ],
        'modal' => [
            'create_title' => 'Create Entity',
            'edit_title' => 'Edit Entity',
        ],
        'form' => [
            'code' => 'Code',
            'code_placeholder' => 'ORGORG',
            'name' => 'Name',
            'name_placeholder' => 'Original Organics Ltd',
            'type' => 'Type',
            'parent' => 'Parent',
        ],
        'delete' => [
            'title' => 'Delete Entity',
            'confirm' => 'Are you sure you want to delete this entity? This action cannot be undone. All associated permissions will also be deleted.',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'actions' => [
            'add_child' => 'Add child entity',
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'edit' => 'Edit entity',
            'delete' => 'Delete entity',
        ],
    ],

    // Permission Matrix
    'permissions' => [
        'title' => 'Permission Matrix',
        'subtitle' => 'Train and manage entity permissions',
        'empty' => 'No permissions trained yet',
        'empty_help' => 'Permissions will appear here as you train them through the matrix.',
        'search_placeholder' => 'Search permissions...',
        'stats' => [
            'total' => 'Total Permissions',
            'allowed' => 'Allowed',
            'denied' => 'Denied',
            'locked' => 'Locked',
            'pending' => 'Pending',
        ],
        'pending_requests' => 'Pending Requests',
        'trained_permissions' => 'Trained Permissions',
        'table' => [
            'entity' => 'Entity',
            'action' => 'Action',
            'route' => 'Route',
            'time' => 'Time',
            'permission_key' => 'Permission Key',
            'scope' => 'Scope',
            'status' => 'Status',
            'source' => 'Source',
        ],
        'status' => [
            'allowed' => 'Allowed',
            'denied' => 'Denied',
            'locked' => 'Locked',
        ],
        'actions' => [
            'train' => 'Train',
            'allow_selected' => 'Allow Selected',
            'deny_selected' => 'Deny Selected',
            'unlock' => 'Unlock',
            'delete' => 'Delete',
        ],
        'train_modal' => [
            'title' => 'Train Permission',
            'entity' => 'Entity',
            'select_entity' => 'Select entity...',
            'permission_key' => 'Permission Key',
            'key_placeholder' => 'product.create',
            'key_help' => 'e.g., product.create, order.view, refund.process',
            'scope' => 'Scope (optional)',
            'scope_placeholder' => 'Leave empty for global',
            'decision' => [
                'allow' => 'Allow',
                'deny' => 'Deny',
            ],
            'lock' => [
                'label' => 'Lock this permission',
                'help' => 'Child entities cannot override this decision. Use for critical restrictions.',
            ],
            'action' => 'Train Permission',
        ],
    ],

    // Common status labels
    'status' => [
        'none' => 'None',
        'unknown' => 'unknown',
        'global' => 'global',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'featured' => 'Featured',
    ],

    // Referrals
    'referrals' => [
        'title' => 'Referrals',
        'subtitle' => 'Manage affiliate referrals, commissions, and payouts',
    ],

    // Bulk actions
    'bulk' => [
        'export' => 'Export',
        'delete' => 'Delete',
        'change_status' => 'Change Status',
        'extend_period' => 'Extend 30 days',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'no_selection' => 'Please select at least one item.',
        'export_success' => ':count item(s) exported successfully.',
        'status_updated' => ':count item(s) updated to :status.',
        'period_extended' => ':count subscription(s) extended by :days days.',
        'activated' => ':count coupon(s) activated.',
        'deactivated' => ':count coupon(s) deactivated.',
        'deleted' => ':count coupon(s) deleted.',
        'skipped_used' => ':count coupon(s) skipped (already used).',
        'confirm_delete_title' => 'Confirm Bulk Delete',
        'confirm_delete_message' => 'Are you sure you want to delete :count coupon(s)?',
        'delete_warning' => 'Coupons that have been used cannot be deleted and will be skipped.',
    ],
];
