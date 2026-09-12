<?php

use App\Models\Order;
use App\Models\DishVariation;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Laravel Bootstrapped!\n";

// 1. Check database connection and categories
$catsCount = \App\Models\Category::count();
echo "Categories in DB: {$catsCount}\n";

$dishesCount = \App\Models\Dish::count();
$varsCount = \App\Models\DishVariation::count();
echo "Dishes in DB: {$dishesCount}\n";
echo "Variations in DB: {$varsCount}\n";

// List some variations
$fishDishes = \App\Models\Dish::where('nom', 'LIKE', '%Dorade%')
    ->orWhere('nom', 'LIKE', '%Tilapia%')
    ->orWhere('nom', 'LIKE', '%Poisson%')
    ->get();
foreach ($fishDishes as $d) {
    echo "Dish found - ID: {$d->id}, Nom: {$d->nom}, Cat ID: {$d->category_id}\n";
}

// 2. Select a fish variation
$fishVariation = DishVariation::first();



if (!$fishVariation) {
    echo "❌ No fish variation found! Make sure you imported the menu.\n";
    exit(1);
}

$initialStock = (float)$fishVariation->stock_kg;
echo "Fish variation selected: {$fishVariation->name} of dish #{$fishVariation->dish_id} (Initial stock: {$initialStock} kg)\n";

// 3. Create a mock order checkout payload
$checkoutPayload = [
    'serviceType' => 'livraison',
    'name' => 'John Doe Test',
    'phone' => '+225 07 12 34 56',
    'neighborhood' => 'Cocody-Plateau',
    'notes' => 'Test notes',
    'total' => 5500,
    'items' => [
        [
            'id' => $fishVariation->dish_id,
            'name' => $fishVariation->dish->nom,
            'quantity' => 2,
            'itemPrice' => 5500,
            'selectedOptions' => [
                ['name' => $fishVariation->name]
            ]
        ]
    ]
];

echo "Simulating Checkout POST /orders...\n";

// Resolve CheckoutController
$controller = app(\App\Http\Controllers\CheckoutController::class);
$request = Request::create('/orders', 'POST', [], [], [], [], json_encode($checkoutPayload));
$request->headers->set('Content-Type', 'application/json');

$response = $controller->store($request);
$resData = json_decode($response->getContent(), true);

if (isset($resData['success']) && $resData['success']) {
    echo "✅ Order placed successfully! Order ID: {$resData['order_id']}\n";
    
    // Check if order details are correctly saved
    $order = Order::with('items')->find($resData['order_id']);
    echo "Order in DB service type: {$order->service_type}, Client name: {$order->client_name}\n";
    echo "Order items count: " . $order->items->count() . "\n";
    foreach ($order->items as $item) {
        echo " - Item: {$item->product_name}, Qty: {$item->quantity}, Price: {$item->price}, Options: {$item->options_text}\n";
    }

    // Check if stock decremented
    $fishVariation->refresh();
    $newStock = (float)$fishVariation->stock_kg;
    echo "New stock: {$newStock} kg\n";
    if ($newStock === ($initialStock - 2)) {
        echo "✅ Stock decremented correctly by 2!\n";
    } else {
        echo "❌ Stock mismatch! Expected: " . ($initialStock - 2) . " but got: {$newStock}\n";
    }
} else {
    echo "❌ Order placement failed: " . ($resData['message'] ?? 'Unknown error') . "\n";
    print_r($resData);
}
