<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opéra Resto — Menu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Thème avant peinture : blanc par défaut, 'dark' si choix mémorisé
        (function () {
            try {
                if ((localStorage.getItem('opera-theme') || 'light') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&display=swap');

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .font-serif-d {
            font-family: 'Playfair Display', Georgia, serif;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-hover {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.18);
        }

        .hero-glow {
            background:
                radial-gradient(600px 300px at 15% 10%, rgba(255, 107, 53, 0.10), transparent 60%),
                radial-gradient(500px 260px at 85% 90%, rgba(255, 107, 53, 0.08), transparent 60%);
        }

        .dark .hero-glow {
            background:
                radial-gradient(600px 300px at 15% 10%, rgba(255, 107, 53, 0.18), transparent 60%),
                radial-gradient(500px 260px at 85% 90%, rgba(255, 107, 53, 0.12), transparent 60%);
        }

        .gold-rule {
            background: linear-gradient(90deg, transparent, #ff6b35, transparent);
            height: 1px;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: #ff6b35;
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-stone-100 dark:bg-ink-950 antialiased">

    <div id="menuApp">
        <!-- ══════════ Page d'accueil ══════════ -->
        <div id="homePage" class="min-h-screen bg-stone-50 dark:bg-ink-950 text-stone-900 dark:text-white hero-glow flex flex-col smooth-transition">
            <div class="max-w-5xl w-full mx-auto px-6 pt-10 pb-16 flex-1 flex flex-col justify-center fade-in">
                <div class="text-center mb-12">
                    <p class="text-xs font-bold tracking-[0.35em] uppercase text-primary-600 dark:text-primary-500 mb-4">Opéra Resto · Lomé</p>
                    <h1 class="font-serif-d text-5xl md:text-7xl font-semibold leading-tight mb-4">
                        L'art du goût,<br class="hidden sm:block"> servi chaud
                    </h1>
                    <div class="w-24 gold-rule mx-auto my-6"></div>
                    <p class="text-stone-500 dark:text-stone-400 text-lg font-light max-w-xl mx-auto">
                        Plats signatures, grillades, bar & cocktails — commandez en ligne, payez Mobile Money, suivez en direct.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-5 max-w-3xl mx-auto w-full">
                    <!-- MENU PLATS -->
                    <button onclick="selectMenu('plats')"
                        class="group relative overflow-hidden bg-white dark:bg-white/[0.04] hover:border-primary-500/60 border border-stone-200 dark:border-white/10 rounded-3xl p-8 text-left card-hover shadow-sm dark:shadow-none">
                        <div class="w-16 h-16 mb-5 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/30 group-hover:scale-105 smooth-transition">
                            <i data-lucide="utensils-crossed" class="w-8 h-8 text-white"></i>
                        </div>
                        <h2 class="font-serif-d text-2xl font-semibold mb-1">Menu Plats</h2>
                        <p class="text-stone-500 dark:text-stone-400 text-sm mb-4">Cuisine, grillades, snacks et pizzas</p>
                        <span class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-500 text-sm font-semibold">
                            Découvrir <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 smooth-transition"></i>
                        </span>
                    </button>

                    <!-- MENU BAR -->
                    <button onclick="selectMenu('bar')"
                        class="group relative overflow-hidden bg-white dark:bg-white/[0.04] hover:border-navy-700/60 border border-stone-200 dark:border-white/10 rounded-3xl p-8 text-left card-hover shadow-sm dark:shadow-none">
                        <div class="w-16 h-16 mb-5 rounded-2xl bg-gradient-to-br from-navy-700 to-navy-900 flex items-center justify-center shadow-lg shadow-navy-900/30 group-hover:scale-105 smooth-transition">
                            <i data-lucide="wine" class="w-8 h-8 text-white"></i>
                        </div>
                        <h2 class="font-serif-d text-2xl font-semibold mb-1">Menu Bar</h2>
                        <p class="text-stone-500 dark:text-stone-400 text-sm mb-4">Boissons, cocktails et apéritifs</p>
                        <span class="inline-flex items-center gap-1.5 text-navy-700 dark:text-navy-100 text-sm font-semibold">
                            Découvrir <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 smooth-transition"></i>
                        </span>
                    </button>
                </div>

                <div class="text-center mt-8">
                    <button onclick="window.location.href='{{ route('track') }}'"
                        class="inline-flex items-center gap-2 text-stone-600 dark:text-stone-300 hover:text-stone-900 dark:hover:text-white text-sm font-medium border border-stone-300 dark:border-white/15 hover:border-stone-400 dark:hover:border-white/40 rounded-full px-6 py-3 smooth-transition bg-white/60 dark:bg-transparent">
                        <i data-lucide="satellite-dish" class="w-4 h-4 text-primary-600 dark:text-primary-500"></i>
                        Suivre ma commande
                    </button>
                </div>
            </div>
            <p class="text-center text-xs text-stone-400 dark:text-stone-600 pb-6">Paiement sécurisé MoneyFusion · TMoney · Flooz</p>
        </div>

        <!-- ══════════ Page Menu ══════════ -->
        <div id="menuPage" class="hidden min-h-screen bg-stone-100 dark:bg-ink-950 smooth-transition">
            <!-- Header -->
            <header class="sticky top-0 z-40 bg-ink-950/95 backdrop-blur border-b border-white/10 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6">
                    <div class="flex items-center gap-3 h-16">
                        <button onclick="showHome()"
                            class="flex items-center gap-1.5 text-stone-300 hover:text-white smooth-transition shrink-0">
                            <i data-lucide="chevron-left" class="w-5 h-5"></i>
                            <span class="text-sm font-medium hidden sm:inline">Accueil</span>
                        </button>

                        <div class="flex items-baseline gap-2 min-w-0">
                            <span class="font-serif-d text-xl font-semibold tracking-wide">OPÉRA</span>
                            <span id="menuTitle" class="text-xs font-bold uppercase tracking-[0.2em] text-primary-500 truncate">Menu Plats</span>
                        </div>

                        <div class="flex-1"></div>

                        <div class="relative hidden sm:block w-64">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-500"></i>
                            <input id="searchInput" type="text" placeholder="Rechercher un plat..."
                                oninput="onSearchInput()"
                                class="w-full bg-white/10 border border-white/10 rounded-full pl-9 pr-4 py-2 text-sm placeholder:text-stone-500 focus:outline-none focus:border-primary-500 smooth-transition">
                        </div>

                        <button onclick="toggleTheme()" title="Clair / sombre"
                            class="w-9 h-9 flex items-center justify-center text-stone-200 hover:bg-white/10 rounded-xl smooth-transition shrink-0">
                            <span id="themeIconMoon"><i data-lucide="moon" class="w-5 h-5"></i></span>
                            <span id="themeIconSun" class="hidden"><i data-lucide="sun" class="w-5 h-5 text-amber-400"></i></span>
                        </button>

                        <button onclick="toggleCategoryNav()"
                            class="flex items-center gap-2 px-3 py-2 text-stone-200 hover:bg-white/10 rounded-xl smooth-transition shrink-0">
                            <span class="text-sm font-medium hidden md:inline">Catégories</span>
                            <i data-lucide="layout-grid" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <div class="sm:hidden pb-3">
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-500"></i>
                            <input id="searchInputMobile" type="text" placeholder="Rechercher un plat..."
                                oninput="onSearchInput()"
                                class="w-full bg-white/10 border border-white/10 rounded-full pl-9 pr-4 py-2 text-sm placeholder:text-stone-500 focus:outline-none focus:border-primary-500 smooth-transition">
                        </div>
                    </div>
                </div>
                <!-- Pills catégories -->
                <div class="border-t border-stone-200 dark:border-white/10 bg-white/95 dark:bg-ink-950/95 backdrop-blur">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6">
                        <div id="categoryPills" class="flex gap-2 overflow-x-auto no-scrollbar py-2.5"></div>
                    </div>
                </div>
            </header>

            <!-- Overlay -->
            <div id="categoryOverlay" class="hidden fixed inset-0 bg-black/50 z-40 backdrop-blur-sm smooth-transition"
                onclick="toggleCategoryNav()"></div>

            <!-- Drawer catégories -->
            <aside id="categoryNav"
                class="hidden md:block fixed top-0 right-0 h-full w-80 bg-white dark:bg-ink-900 transform translate-x-full smooth-transition z-50 shadow-2xl overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-stone-200 dark:border-white/10">
                        <h2 class="font-serif-d text-xl font-semibold text-stone-900 dark:text-white">Catégories</h2>
                        <button onclick="toggleCategoryNav()"
                            class="p-2 hover:bg-stone-100 dark:hover:bg-white/10 rounded-xl smooth-transition">
                            <i data-lucide="x" class="w-5 h-5 text-stone-500 dark:text-stone-400"></i>
                        </button>
                    </div>
                    <nav id="categoryList" class="space-y-1"></nav>
                </div>
            </aside>

            <!-- Contenu -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 pb-32 md:pb-12">
                <div id="menuGrid" class="space-y-12 md:space-y-16"></div>
                <div id="emptySearch" class="hidden text-center py-20 text-stone-400 dark:text-stone-500">
                    <i data-lucide="search-x" class="w-12 h-12 mx-auto mb-4 opacity-40"></i>
                    <p class="font-serif-d text-2xl text-stone-500 dark:text-stone-400">Aucun plat trouvé</p>
                    <p class="text-sm mt-1">Essayez un autre mot-clé</p>
                </div>
            </main>

            <!-- Bouton panier flottant -->
            <button id="floatingCartBtn" onclick="showCart()"
                class="hidden fixed bottom-6 right-4 sm:right-6 z-40 flex items-center gap-2.5 pl-4 pr-2 py-2 bg-navy-900 dark:bg-white text-white dark:text-navy-900 rounded-full shadow-2xl shadow-navy-900/30 hover:scale-[1.03] smooth-transition border border-navy-800 dark:border-transparent">
                <span class="relative">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-primary-500"></i>
                    <span id="cartBadge"
                        class="absolute -top-2.5 -right-2.5 min-w-[20px] h-5 px-1 bg-primary-500 text-white text-[11px] rounded-full flex items-center justify-center font-bold">0</span>
                </span>
                <span class="text-sm font-semibold">Panier</span>
                <span id="floatingCartTotal" class="text-sm font-bold bg-primary-500 text-white rounded-full px-3 py-1.5">0 F</span>
            </button>
        </div>

        <!-- ══════════ Modal Produit ══════════ -->
        <div id="productModal"
            class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center sm:p-4 fade-in"
            onclick="closeModal(event)">
            <div class="bg-white dark:bg-ink-900 rounded-t-3xl sm:rounded-3xl max-w-2xl w-full max-h-[92vh] overflow-hidden shadow-2xl"
                onclick="event.stopPropagation()">
                <div class="overflow-y-auto max-h-[92vh]">
                    <div id="modalImageWrap" class="relative hidden h-48 bg-stone-200 dark:bg-white/10">
                        <img id="modalImage" src="" alt="" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <button onclick="closeProductModal()"
                            class="absolute top-4 right-4 w-9 h-9 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center smooth-transition backdrop-blur">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <div class="p-6 sm:p-8">
                        <div class="flex items-start justify-between gap-4 mb-1">
                            <p id="modalProductCategory" class="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-500"></p>
                            <button id="modalCloseText" onclick="closeProductModal()"
                                class="sm:hidden text-xs font-semibold text-stone-400">FERMER</button>
                        </div>
                        <h2 id="modalProductName" class="font-serif-d text-3xl font-semibold text-stone-900 dark:text-white"></h2>
                        <p id="modalProductDescription" class="text-stone-500 dark:text-stone-400 mt-2 text-sm leading-relaxed"></p>
                        <div class="w-12 gold-rule my-5 mx-0!"></div>
                        <div id="modalAttributes" class="space-y-6"></div>
                        <p id="validationError" class="hidden text-red-600 dark:text-red-400 text-sm mt-4 bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 p-3 rounded-xl"></p>
                    </div>

                    <div class="sticky bottom-0 bg-white/95 dark:bg-ink-900/95 backdrop-blur border-t border-stone-200 dark:border-white/10 p-5 sm:px-8">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-stone-500 dark:text-stone-400 text-sm font-medium">Total</span>
                            <span id="modalTotalPrice" class="font-serif-d text-3xl font-bold text-stone-900 dark:text-white">0 F</span>
                        </div>
                        <button onclick="addToCart()"
                            class="w-full py-4 bg-primary-500 text-white rounded-2xl font-bold hover:bg-primary-600 smooth-transition shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
                            <i data-lucide="plus" class="w-5 h-5"></i>
                            Ajouter au panier
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ Modal Panier ══════════ -->
        <div id="cartModal"
            class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center sm:p-4 fade-in"
            onclick="closeModal(event)">
            <div class="bg-stone-50 dark:bg-ink-900 rounded-t-3xl sm:rounded-3xl max-w-2xl w-full max-h-[92vh] overflow-hidden shadow-2xl"
                onclick="event.stopPropagation()">
                <div class="overflow-y-auto max-h-[92vh]">
                    <div class="sticky top-0 bg-navy-900 text-white px-6 sm:px-8 py-5 z-10 flex items-center justify-between">
                        <div>
                            <h2 class="font-serif-d text-2xl font-semibold">Votre Panier</h2>
                            <p class="text-stone-400 text-xs mt-0.5">Vérifiez puis validez votre commande</p>
                        </div>
                        <button onclick="closeCartModal()"
                            class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center smooth-transition">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div id="cartItems" class="p-5 sm:p-8"></div>
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
    <script src="{{ asset('assets/js/cart.js') }}?v=10"></script>
    <script>if (window.lucide) lucide.createIcons();</script>

</body>

</html>
