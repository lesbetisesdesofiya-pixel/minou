<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivre ma commande - Opera Resto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .step-active {
            color: #ff6b35;
            border-color: #ff6b35;
        }

        .progress-line {
            height: 2px;
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-gray-50 flex flex-col min-h-screen text-gray-900">

    <!-- Navbar Minimalist -->
    <nav class="bg-white border-b border-gray-100 p-4 sticky top-0 z-50">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="{{ route('menu') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                <span class="font-medium">Retour</span>
            </a>
            <h1 class="text-lg font-bold text-gray-900">Suivi de Commande</h1>
            <div class="w-8"></div>
        </div>
    </nav>

    <main class="flex-grow p-4 max-w-2xl mx-auto w-full">

        @if ($order)
            @php
                $rawStatus = strtolower($order->status);
                if ($rawStatus === 'ready_for_pickup') {
                    $status = 'delivering';
                } elseif ($rawStatus === 'delivered') {
                    $status = 'completed';
                } else {
                    $status = $rawStatus; // pending, preparing
                }

                $steps = ['pending', 'preparing', 'delivering', 'completed'];
                $currentIndex = array_search($status, $steps);
                if ($currentIndex === false) {
                    $currentIndex = 0;
                }

                $statusLabels = [
                    'pending' => 'Commande Reçue',
                    'preparing' => 'En Préparation',
                    'delivering' => 'En Livraison',
                    'completed' => 'Livrée / Terminée'
                ];
            @endphp
            <!-- Status Card -->
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 mb-6 fade-in">
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <p class="text-gray-400 text-xs uppercase font-bold tracking-widest mb-1">Commande #
                            {{ $order->id }}
                        </p>
                        <h2 class="text-2xl font-black text-gray-900">
                            {{ $statusLabels[$status] }}
                        </h2>
                    </div>
                    @if ($status !== 'completed')
                        <div class="flex items-center gap-2 text-primary-500 font-bold text-sm animate-pulse">
                            <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                            Temps réel
                        </div>
                    @endif
                </div>

                <!-- Progress Steps -->
                <div class="relative flex items-center justify-between mb-8">
                    <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-gray-100 -translate-y-1/2 z-0"></div>
                    @foreach ($steps as $idx => $s)
                        <div class="relative z-10 flex flex-col items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full border-2 bg-white flex items-center justify-center transition-all duration-500 {{ $idx <= $currentIndex ? 'border-orange-500 bg-orange-50 text-orange-500' : 'border-gray-200 text-gray-300' }}">
                                @if ($idx < $currentIndex)
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                @else
                                    <span class="text-xs font-bold">
                                        {{ $idx + 1 }}
                                    </span>
                                @endif
                            </div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-center {{ $idx <= $currentIndex ? 'text-gray-900' : 'text-gray-300' }}">
                                {{ last(explode(' ', $statusLabels[$s])) }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-500 mb-1">
                        {{ $order->service_type === 'table' ? 'Service à Table' : 'Détails de livraison' }}
                    </p>
                    @if ($order->service_type === 'table')
                        <p class="font-bold text-gray-800">Table N° {{ $order->table_number }}</p>
                    @else
                        <p class="font-bold text-gray-800">{{ $order->client_name }}</p>
                        <p class="text-gray-600">{{ $order->client_phone }}</p>
                        @if ($order->neighborhood)
                            <p class="text-gray-600 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                {{ $order->neighborhood }}
                            </p>
                        @endif
                    @endif
                </div>

                @if ($status !== 'completed')
                    <button onclick="location.reload()"
                        class="mt-6 w-full py-3 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:bg-gray-50 transition">Actualiser</button>
                @endif
            </div>

            <!-- Items Details Card -->
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 mb-6 fade-in">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-orange-500"></i>
                    Détails de la commande
                </h3>
                <div class="space-y-4">
                    @foreach ($items as $item)
                        <div class="flex justify-between items-start py-3 border-b border-gray-50 last:border-0">
                            <div class="flex-grow">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="bg-orange-100 text-orange-600 text-xs font-black px-2 py-0.5 rounded-lg">{{ $item->quantity }}x</span>
                                    <span
                                        class="font-bold text-gray-800 text-sm">{{ $item->product_name }}</span>
                                </div>
                                <div class="text-[11px] text-gray-400 mt-1 ml-9 italic leading-tight">
                                    {{ $item->options_text }}
                                </div>
                            </div>
                            <div class="font-black text-gray-900 text-sm whitespace-nowrap ml-4">
                                {{ number_format($item->price * $item->quantity, 0, ',', ' ') }} F
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 pt-6 border-t font-black flex justify-between items-center">
                    <span class="text-gray-500 uppercase text-xs tracking-widest">Total Payé</span>
                    <span class="text-2xl text-gray-900">{{ number_format($order->total_amount, 0, ',', ' ') }} F</span>
                </div>
            </div>
        @endif

        <!-- History Card -->
        <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 fade-in">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
                Historique de vos commandes
            </h3>
            <div id="order-history-list" class="space-y-3">
                <!-- Loaded from IndexedDB -->
                <div class="py-10 text-center text-gray-400 italic">Chargement de votre historique...</div>
            </div>
        </div>

    </main>

    <script>
        // IndexedDB Logic
        const DB_NAME = 'OperaRestoDB';
        const DB_VERSION = 1;
        const STORE_NAME = 'orderHistory';

        function openDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(DB_NAME, DB_VERSION);
                request.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains(STORE_NAME)) {
                        db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                    }
                };
                request.onsuccess = (e) => resolve(e.target.result);
                request.onerror = (e) => reject(e.target.error);
            });
        }

        async function saveOrderToHistory(id, status, total) {
            const db = await openDB();
            const tx = db.transaction(STORE_NAME, 'readwrite');
            const store = tx.objectStore(STORE_NAME);
            store.put({ id: String(id), date: new Date().toISOString(), status, total });
        }

        async function getOrderHistory() {
            const db = await openDB();
            return new Promise((resolve) => {
                const tx = db.transaction(STORE_NAME, 'readonly');
                const store = tx.objectStore(STORE_NAME);
                const request = store.getAll();
                request.onsuccess = () => {
                    // Sort by date desc
                    const items = request.result.sort((a, b) => new Date(b.date) - new Date(a.date));
                    resolve(items);
                };
            });
        }

        // Logic to capture order from current URL or last order
        async function initTracking() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentOrderId = urlParams.get('id');

            const history = await getOrderHistory();
            const listContainer = document.getElementById('order-history-list');

            if (history.length === 0) {
                listContainer.innerHTML = '<div class="py-12 text-center text-gray-400 italic flex flex-col items-center gap-3"><i data-lucide="package" class="w-12 h-12 opacity-20"></i> Aucune commande trouvée sur cet appareil.</div>';
                lucide.createIcons();
                return;
            }

            listContainer.innerHTML = history.map(item => `
                <a href="{{ route('track') }}?id=${item.id}" class="block group">
                    <div class="flex items-center justify-between p-4 rounded-2xl bg-gray-50 border border-transparent group-hover:border-orange-200 group-hover:bg-orange-50 transition-all ${currentOrderId == item.id ? 'ring-2 ring-orange-500 bg-orange-50 border-orange-200' : ''}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-gray-400 group-hover:text-orange-500">
                                <i data-lucide="package" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 group-hover:text-orange-400">#${item.id}</p>
                                <p class="text-sm font-bold text-gray-800">${new Date(item.date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-gray-900 mb-1">${parseFloat(item.total).toLocaleString('fr-FR')} F</p>
                            <span class="text-[10px] font-black uppercase text-orange-500">${item.status || 'Voir statut'}</span>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 ml-auto"></i>
                        </div>
                    </div>
                </a>
            `).join('');
            lucide.createIcons();
        }

        document.addEventListener('DOMContentLoaded', initTracking);
    </script>
    <script>lucide.createIcons();</script>
</body>

</html>
