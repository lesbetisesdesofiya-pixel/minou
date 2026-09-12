<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\DeliveryZone;
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

        $deliveryZones      = DeliveryZone::orderBy('quartier')->get();
        $deliveryDefaultFee = config('delivery.default', 1000);

        return view('admin.dashboard', compact('stats', 'recentOrders', 'deliveryZones', 'deliveryDefaultFee'));
    }

    private function requireAdmin()
    {
        if (!session('admin_id')) return redirect()->route('admin.login');
        return null;
    }

    /**
     * Citer un quartier à tarif de livraison spécial.
     */
    public function storeZone(Request $request)
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        $validated = $request->validate([
            'quartier' => 'required|string|max:100|unique:delivery_zones,quartier',
            'fee'      => 'required|integer|min:0|max:100000',
        ], [
            'quartier.unique' => 'Ce quartier est déjà cité.',
        ]);

        DeliveryZone::create([
            'quartier' => trim($validated['quartier']),
            'fee'      => (int) $validated['fee'],
            'active'   => true,
        ]);

        return back()->with('zone_success', 'Quartier ajouté : ' . $validated['quartier'] . ' (' . number_format($validated['fee'], 0, ',', ' ') . ' F).');
    }

    /**
     * Retirer un quartier à tarif spécial (retour au forfait par défaut).
     */
    public function destroyZone(Request $request, $id)
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        DeliveryZone::where('id', $id)->delete();

        return back()->with('zone_success', 'Quartier retiré, forfait par défaut appliqué.');
    }
}

