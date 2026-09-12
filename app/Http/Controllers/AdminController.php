<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('admin_login');
    }

    public function login(Request $request)
    {
        $email    = $request->input('email', '');
        $password = $request->input('password', '');

        $admin = Admin::where('email', $email)->first();

        if ($admin && Hash::check($password, $admin->password)) {
            session(['admin_id' => $admin->id, 'admin_email' => $admin->email, 'admin_role' => $admin->role]);
            return redirect()->route('admin.dashboard');
        }

        return back()->with('error', 'Identifiants invalides.');
    }

    public function logout()
    {
        session()->flush();
        return redirect()->route('admin.login');
    }

    public function dashboard()
    {
        if (!session('admin_id')) return redirect()->route('admin.login');

        $stats = [
            'total_orders'    => Order::count(),
            'total_revenue'   => Order::where('status', '!=', 'cancelled')->sum('total_amount'),
            'pending_orders'  => Order::where('status', 'pending')->count(),
            'delivered_orders'=> Order::where('status', 'delivered')->count(),
        ];

        $recentOrders = Order::orderByDesc('created_at')->limit(10)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders'));
    }
}

