<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            500: '#ff6b35',
                            600: '#ea580c',
                        },
                        navy: {
                            800: '#1e3a5f',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

        .card-hover {
            transition: all 0.2s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: #ff6b35;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-white antialiased">

    <div id="menuApp">
        <!-- Page d'accueil -->
        <div id="homePage"
            class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100">
            <div class="max-w-5xl w-full px-6 py-12 fade-in">
                <div class="text-center mb-16">
                    <h1 class="text-5xl md:text-6xl font-light text-gray-900 mb-4 tracking-tight">
                        Bienvenue
                    </h1>
                    <div class="w-20 h-1 bg-primary-500 mx-auto mb-6"></div>
                    <p class="text-lg text-gray-600 font-light">
                        Choisissez votre menu
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-8 max-w-3xl mx-auto">

                    <!-- MENU PLATS -->
                    <button onclick="selectMenu('plats')"
                        class="group bg-white rounded-2xl p-10 text-center card-hover border border-gray-200">

                        <div
                            class="w-20 h-20 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center text-primary-500 group-hover:bg-primary-50 smooth-transition">
                            <i data-lucide="utensils" class="w-10 h-10"></i>
                        </div>

                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">Menu Plats</h2>
                        <p class="text-gray-500">Cuisine, snacks et pizzas</p>
                    </button>


                    <!-- MENU BAR -->
                    <button onclick="selectMenu('bar')"
                        class="group bg-white rounded-2xl p-10 text-center card-hover border border-gray-200">

                        <div
                            class="w-20 h-20 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center text-navy-900 group-hover:bg-navy-900 group-hover:text-white smooth-transition">
                            <i data-lucide="beer" class="w-10 h-10"></i>
                        </div>

                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">Menu Bar</h2>
                        <p class="text-gray-500">Boissons, cocktails et apéritifs</p>
                    </button>

                    <!-- SUIVI COMMANDE -->
                    <button onclick="window.location.href='{{ route('track') }}'"
                        class="group bg-white rounded-2xl p-10 text-center card-hover border border-gray-200 md:col-span-2 max-w-md mx-auto w-full">

                        <div
                            class="w-20 h-20 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white smooth-transition">
                            <i data-lucide="satellite" class="w-10 h-10"></i>
                        </div>

                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">Suivre ma commande</h2>
                        <p class="text-gray-500">Historique et statut en direct</p>
                    </button>

                </div>

            </div>
        </div>

        <!-- Page Menu -->
        <div id="menuPage" class="hidden min-h-screen bg-gray-50">
            <!-- Header -->
            <header class="sticky top-0 z-40 bg-white border-b border-gray-200 backdrop-blur-sm bg-white/95">
                <div class="max-w-7xl mx-auto px-4 sm:px-6">
                    <div class="flex items-center justify-between h-16">
                        <button onclick="showHome()"
                            class="flex items-center gap-2 text-gray-700 hover:text-gray-900 smooth-transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 19l-7-7 7-7"></path>
                            </svg>
                            <span class="text-sm font-medium">Retour</span>
                        </button>

                        <h1 id="menuTitle" class="text-xl font-semibold text-gray-900">Menu Plats</h1>

                        <button onclick="toggleCategoryNav()"
                            class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg smooth-transition">
                            <span class="text-sm font-medium hidden sm:inline">Catégories</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Overlay -->
            <div id="categoryOverlay" class="hidden fixed inset-0 bg-black/20 z-40 backdrop-blur-sm smooth-transition"
                onclick="toggleCategoryNav()"></div>

            <!-- Sidebar catégories (Desktop Only) -->
            <aside id="categoryNav"
                class="hidden md:block fixed top-0 right-0 h-full w-80 bg-white transform translate-x-full smooth-transition z-50 shadow-2xl overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Catégories</h2>
                        <button onclick="toggleCategoryNav()"
                            class="p-2 hover:bg-gray-100 rounded-lg smooth-transition">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <nav id="categoryList" class="space-y-1"></nav>
                </div>
            </aside>

            <!-- Bottom Sticky Category Menu (Mobile Only) -->
            <div id="mobileCategoryNav"
                class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 px-4 py-3 flex gap-3 overflow-x-auto no-scrollbar shadow-[0_-5px_15px_-3px_rgba(0,0,0,0.05)]">
                <!-- JS Populated -->
            </div>

            <!-- Contenu -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 pb-32 md:pb-8">
                <div id="menuGrid" class="space-y-12 md:space-y-16"></div>
            </main>

            <!-- Bouton panier -->
            <button id="floatingCartBtn" onclick="showCart()"
                class="hidden fixed bottom-20 md:bottom-6 right-6 w-14 h-14 bg-primary-500 text-white rounded-full shadow-lg hover:shadow-xl hover:scale-105 smooth-transition z-40 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                    </svg>
                <span id="cartBadge"
                    class="absolute -top-1 -right-1 w-6 h-6 bg-navy-900 text-white text-xs rounded-full flex items-center justify-center font-semibold">0</span>
            </button>
        </div>

        <!-- Modal Produit -->
        <div id="productModal"
            class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 fade-in"
            onclick="closeModal(event)">
            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl"
                onclick="event.stopPropagation()">
                <div class="overflow-y-auto max-h-[90vh]">
                    <div class="sticky top-0 bg-white border-b border-gray-200 p-6 z-10">
                        <button onclick="closeProductModal()"
                            class="float-right p-1 hover:bg-gray-100 rounded-lg smooth-transition">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <h2 id="modalProductName" class="text-2xl font-semibold text-gray-900 pr-8"></h2>
                        <p id="modalProductDescription" class="text-gray-600 mt-2 text-sm"></p>
                    </div>

                    <div class="p-6">
                        <div id="modalAttributes" class="space-y-6"></div>
                        <p id="validationError" class="hidden text-red-500 text-sm mt-4 bg-red-50 p-3 rounded-lg"></p>
                    </div>

                    <div class="sticky bottom-0 bg-white border-t border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-gray-600 font-medium">Total</span>
                            <span id="modalTotalPrice" class="text-3xl font-bold text-gray-900">0 F</span>
                        </div>
                        <button onclick="addToCart()"
                            class="w-full py-3.5 bg-primary-500 text-white rounded-xl font-medium hover:bg-primary-600 smooth-transition shadow-sm">
                            Ajouter au panier
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Panier -->
        <div id="cartModal"
            class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 fade-in"
            onclick="closeModal(event)">
            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl"
                onclick="event.stopPropagation()">
                <div class="overflow-y-auto max-h-[90vh]">
                    <div class="sticky top-0 bg-white border-b border-gray-200 p-6 z-10">
                        <button onclick="closeCartModal()"
                            class="float-right p-1 hover:bg-gray-100 rounded-lg smooth-transition">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <h2 class="text-2xl font-semibold text-gray-900">Votre Panier</h2>
                    </div>

                    <div id="cartItems" class="p-6"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Injection -->
    <script>
        const productsData = {!! json_encode($productsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
        const supplementsData = {
            garnitures: {!! json_encode($garnituresGlobal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        };
        const CHECKOUT_URL = "{{ route('checkout') }}";
    </script>
    <!-- Script Logic -->
    <script src="{{ asset('assets/js/cart.js') }}?v=6"></script>
    <script>lucide.createIcons();</script>

</body>

</html>
