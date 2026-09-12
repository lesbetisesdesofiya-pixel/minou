<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function show(Request $request)
    {
        $orderId = $request->query('id');
        $order   = null;
        $items   = [];

        if ($orderId) {
            $order = Order::with(['items', 'driver'])->find($orderId);
            if ($order) {
                $items = $order->items;
            }
        }

        return view('track', compact('order', 'items'));
    }
}
