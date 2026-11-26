<?php

/**
 * Property inventory vocabulary. A property holds apartments, houses and/or commercial spaces
 * (complexes.type tokens); a house has a house type and is offered for rent, for sale or both.
 */
return [
    // Currency code for amounts that carry none of their own. DEFAULT_CURRENCY is the older name.
    'currency' => env('ERTY_CURRENCY', env('DEFAULT_CURRENCY', 'KES')),

    'house_types' => ['Bungalow', 'Maisonette', 'Townhouse', 'Villa', 'Cottage', 'Duplex', 'Detached house', 'Semi-detached house', 'Mansion', 'Other'],

    'offers' => ['rent' => 'For rent', 'sale' => 'For sale', 'both' => 'For rent or sale'],

    'plot_units' => ['sq_m' => 'sq m', 'sq_ft' => 'sq ft', 'acres' => 'acres', 'hectares' => 'hectares'],

    'house_features' => [
        'servant_quarter' => 'Servant quarter (DSQ)', 'garden' => 'Garden', 'parking' => 'Parking', 'gated' => 'Gated community',
        'borehole' => 'Borehole', 'swimming_pool' => 'Swimming pool', 'solar' => 'Solar water heating', 'security' => '24-hour security',
    ],

    'sale_payment_methods' => ['bank_transfer' => 'Bank transfer', 'mpesa' => 'M-Pesa', 'cheque' => 'Cheque', 'cash' => 'Cash', 'mortgage' => 'Mortgage disbursement', 'other' => 'Other'],
];
