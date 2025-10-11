<?php 

return [
    // Decimal precision for balance calculations
    'decimal_places' => 8,
    
    // Default currency for wallets
    'default_currency' => 'USD',
    
    // Default wallet configuration
    'default_wallet' => [
        'slug' => 'main',
        'name' => 'Main Wallet',
        'initial_balance' => 0,
        'auto_create' => true,  // Auto-create on model creation
    ],

    // Reference generation method
    'reference' => [
        'generator' => 'uuid',  // 'uuid' or 'timestamp'
    ],
];