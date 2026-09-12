<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administration - Opera Resto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-white flex flex-col justify-between hidden md:flex">
            <div class="p-6">
                <h1 class="text-2xl font-black tracking-wider text-orange-500 mb-8">OPERA RESTO</h1>
                <nav class="space-y-2">
                    <a href="#" class="flex items-center gap-3 px-4 py-3 bg-orange-500 text-white rounded-xl font-medium transition">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a>
                </nav>
            </div>
            <div class="p-6 border-t border-slate-800">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center font-bold text-orange-500">
                        A
                    </div>
                    <div>
                        <p class="text-sm font-bold truncate max-w-[150px]">{{ session('admin_email') }}</p>
                        <p class="text-xs text-gray-400 capitalize">{{ session('admin_role') }}</p>
                    </div>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-2 bg-red-600/20 text-red-400 rounded-lg hover:bg-red-600 hover:text-white transition flex items-center justify-center gap-2 font-medium">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Déconnexion
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-grow flex flex-col overflow-y-auto">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Vue d'ensemble</h2>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 hidden sm:inline">{{ date('d F Y') }}</span>
                    <!-- Mobile Logout Form -->
                    <form action="{{ route('admin.logout') }}" method="POST" class="md:hidden">
                        @csrf
                        <button type="submit" class="p-2 bg-red-50 text-red-500 rounded-lg">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </button>
                    </form>
                </div>
            </header>

            <!-- Dashboard Body -->
            <main class="p-6 space-y-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Orders -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-sm text-gray-400 font-medium">Commandes Totales</p>
                            <h3 class="text-3xl font-black mt-1">{{ number_format($stats['total_orders']) }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center">
                            <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-sm text-gray-400 font-medium">Chiffre d'Affaires</p>
                            <h3 class="text-3xl font-black mt-1 text-orange-600">{{ number_format($stats['total_revenue'], 0, ',', ' ') }} F</h3>
                        </div>
                        <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                        </div>
                    </div>

                    <!-- Pending Orders -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-sm text-gray-400 font-medium">En attente</p>
                            <h3 class="text-3xl font-black mt-1 text-yellow-600">{{ number_format($stats['pending_orders']) }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-yellow-50 text-yellow-500 rounded-xl flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                    </div>

                    <!-- Delivered Orders -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-sm text-gray-400 font-medium">Livrées</p>
                            <h3 class="text-3xl font-black mt-1 text-green-600">{{ number_format($stats['delivered_orders']) }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-green-50 text-green-500 rounded-xl flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- Delivery Zones -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="font-bold text-gray-800 text-lg">Livraison — quartiers à tarif spécial</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Forfait par défaut : <span class="font-bold text-gray-800">{{ number_format($deliveryDefaultFee, 0, ',', ' ') }} F</span>
                            partout ailleurs. Les quartiers cités ici apparaissent dans la liste déroulante du client avec leur tarif.
                        </p>
                    </div>
                    <div class="p-6 grid md:grid-cols-2 gap-6">
                        <div>
                            @if (session('zone_success'))
                                <p class="bg-green-50 text-green-700 border border-green-200 p-3 rounded-xl mb-4 text-sm font-medium">{{ session('zone_success') }}</p>
                            @endif
                            @if ($errors->any())
                                <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-xl mb-4 text-sm">
                                    @foreach ($errors->all() as $error)
                                        <p>{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif
                            <form action="{{ route('admin.zones.store') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                                @csrf
                                <input type="text" name="quartier" required maxlength="100" placeholder="Nom du quartier"
                                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none text-sm">
                                <input type="number" name="fee" required min="0" max="100000" step="50" value="1500" title="Tarif en F"
                                    class="w-full sm:w-32 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none text-sm">
                                <button type="submit"
                                    class="px-5 py-2.5 bg-slate-900 text-white rounded-xl font-semibold hover:bg-slate-800 transition text-sm whitespace-nowrap">
                                    Ajouter
                                </button>
                            </form>
                        </div>
                        <div>
                            @forelse ($deliveryZones as $zone)
                                <div class="flex items-center justify-between py-2.5 border-b border-gray-100 last:border-0">
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">{{ $zone->quartier }}</p>
                                        <p class="text-xs text-gray-500">{{ number_format($zone->fee, 0, ',', ' ') }} F de livraison</p>
                                    </div>
                                    <form action="{{ route('admin.zones.destroy', $zone->id) }}" method="POST"
                                        onsubmit="return confirm('Retirer ce quartier ? Le forfait par défaut s\'appliquera.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-gray-400 italic text-sm py-4">Aucun quartier cité : forfait {{ number_format($deliveryDefaultFee, 0, ',', ' ') }} F partout.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Recent Orders Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg">Dernières Commandes</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-400 uppercase">
                                    <th class="px-6 py-4">Commande</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4">Type</th>
                                    <th class="px-6 py-4">Montant</th>
                                    <th class="px-6 py-4">Statut</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Détails</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @forelse ($recentOrders as $order)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 font-bold text-gray-900">#{{ $order->id }}</td>
                                        <td class="px-6 py-4">
                                            @if ($order->service_type === 'table')
                                                <span class="text-gray-500 italic">Table N° {{ $order->table_number }}</span>
                                            @else
                                                <p class="font-semibold text-gray-800">{{ $order->client_name }}</p>
                                                <p class="text-xs text-gray-500">{{ $order->client_phone }}</p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 capitalize font-medium text-gray-600">
                                            {{ $order->service_type }}
                                        </td>
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            {{ number_format($order->total_amount, 0, ',', ' ') }} F
                                        </td>
                                        <td class="px-6 py-4">
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                                    'preparing' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'PREPARING' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'ready_for_pickup' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                    'READY_FOR_PICKUP' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                    'delivered' => 'bg-green-50 text-green-700 border-green-200',
                                                    'completed' => 'bg-green-50 text-green-700 border-green-200',
                                                    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
                                                ];
                                                $color = $statusColors[strtolower($order->status)] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                            @endphp
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold border {{ $color }}">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            {{ $order->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('track') }}?id={{ $order->id }}" class="text-orange-500 hover:text-orange-600 font-semibold flex items-center gap-1">
                                                Suivre <i data-lucide="external-link" class="w-4 h-4"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">
                                            Aucune commande enregistrée pour le moment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>
