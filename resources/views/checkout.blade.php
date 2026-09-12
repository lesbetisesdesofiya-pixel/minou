<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande | Opéra Restaurant</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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

        // IndexedDB Helper
        const DB_NAME = 'OperaRestoDB';
        const DB_VERSION = 1;
        const STORE_NAME = 'orderHistory';

        function openHistoryDB() {
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

        async function saveOrderToIndexedDB(id, total) {
            try {
                const db = await openHistoryDB();
                const tx = db.transaction(STORE_NAME, 'readwrite');
                const store = tx.objectStore(STORE_NAME);
                store.put({
                    id: String(id),
                    date: new Date().toISOString(),
                    status: 'Payée',
                    total
                });
            } catch (err) { console.error("IndexedDB Save Error:", err); }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Glassmorphism details */
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: #ff6b35;
        }
    </style>
</head>

<body class="bg-slate-50 antialiased min-h-screen text-slate-800">

    <!-- Premium Top Accent Line -->
    <div class="h-1.5 w-full bg-gradient-to-r from-orange-400 via-primary-500 to-red-500"></div>

    <div class="max-w-3xl mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <header class="mb-8 text-center fade-in">
            <div class="flex justify-between items-center mb-6">
                <a href="{{ route('menu') }}"
                    class="flex items-center gap-2 text-slate-500 hover:text-slate-900 font-medium smooth-transition bg-white shadow-sm border border-slate-200/60 px-4 py-2 rounded-xl text-sm">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    <span>Menu</span>
                </a>
                <span class="text-xs bg-primary-100 text-primary-600 font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    Opéra Resto
                </span>
            </div>
            
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">
                Finalisation de votre commande
            </h1>
            <p class="text-slate-500 text-sm md:text-base mt-2">Plus que quelques secondes avant de vous régaler</p>
            <div class="w-16 h-1 bg-gradient-to-r from-primary-500 to-orange-500 rounded-full mx-auto mt-4"></div>
        </header>

        <!-- Stepper Indicator -->
        <div class="mb-8 px-2 max-w-md mx-auto fade-in">
            <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider relative">
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-0.5 bg-slate-200 -z-10"></div>
                <div id="step-dot-1" class="flex flex-col items-center gap-1.5 bg-slate-50 px-2 text-primary-500">
                    <span class="w-7 h-7 rounded-full bg-primary-500 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-primary-100">1</span>
                    <span>Client</span>
                </div>
                <div id="step-dot-2" class="flex flex-col items-center gap-1.5 bg-slate-50 px-2">
                    <span id="step-badge-2" class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-sm">2</span>
                    <span>Paiement</span>
                </div>
                <div id="step-dot-3" class="flex flex-col items-center gap-1.5 bg-slate-50 px-2">
                    <span id="step-badge-3" class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-sm">3</span>
                    <span>Validation</span>
                </div>
            </div>
        </div>

        <!-- Step 1: Service Type Selection -->
        <div id="step-service-type" class="space-y-6 fade-in">
            <h2 class="text-xl font-bold text-slate-800 text-center mb-2">
                Comment souhaitez-vous être servi ?
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="selectServiceType('livraison')"
                    class="bg-white rounded-2xl p-6 border border-slate-200/80 hover:border-primary-500 hover:shadow-lg hover:shadow-primary-500/5 smooth-transition text-left group">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center group-hover:bg-primary-500 group-hover:text-white smooth-transition text-primary-500 shadow-inner">
                            <i data-lucide="truck" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 group-hover:text-primary-500 smooth-transition">Livraison à domicile</h3>
                            <p class="text-sm text-slate-500">Recevez votre commande bien chaud chez vous</p>
                        </div>
                    </div>
                </button>

                <button onclick="selectServiceType('emporter')"
                    class="bg-white rounded-2xl p-6 border border-slate-200/80 hover:border-primary-500 hover:shadow-lg hover:shadow-primary-500/5 smooth-transition text-left group">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center group-hover:bg-primary-500 group-hover:text-white smooth-transition text-primary-500 shadow-inner">
                            <i data-lucide="shopping-bag" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 group-hover:text-primary-500 smooth-transition">À emporter / Retrait</h3>
                            <p class="text-sm text-slate-500">Venez récupérer votre plat directement</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Step 2: Details Form -->
        <div id="step-details" class="hidden fade-in">
            <form id="checkout-form" onsubmit="handleSubmit(event)" class="space-y-6">

                <!-- Livraison Form -->
                <div id="form-livraison" class="hidden bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-primary-500"></i> Informations de Livraison
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nom & Prénoms *</label>
                            <input type="text" id="livraison-name"
                                name="name"
                                autocomplete="name"
                                minlength="2" maxlength="80"
                                placeholder="Prénom et Nom"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Numéro de Téléphone *</label>
                            <input type="tel" id="livraison-phone"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                minlength="8" maxlength="20"
                                pattern="[\d\s\+\-\(\)]{8,20}"
                                placeholder="+225 07 XX XX XX XX"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Quartier de livraison *</label>
                            @if(!empty($deliveryZones))
                                <select id="livraison-neighborhood"
                                    name="neighborhood"
                                    onchange="onQuartierChange()"
                                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 smooth-transition bg-white">
                                    <option value="">Sélectionnez votre quartier</option>
                                    @foreach($deliveryZones as $quartier => $tarif)
                                        <option value="{{ $quartier }}">{{ $quartier }} — {{ number_format($tarif, 0, ',', ' ') }} F de livraison</option>
                                    @endforeach
                                    <option value="__autre">Autre quartier — {{ number_format($deliveryDefaultFee, 0, ',', ' ') }} F de livraison</option>
                                </select>
                                <input type="text" id="livraison-neighborhood-autre"
                                    maxlength="100"
                                    placeholder="Précisez votre quartier"
                                    oninput="refreshTotals()"
                                    class="hidden mt-3 w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 smooth-transition">
                            @else
                                <input type="text" id="livraison-neighborhood"
                                    name="neighborhood"
                                    autocomplete="address-level2"
                                    minlength="3" maxlength="100"
                                    placeholder="Ex: Cocody, Yopougon..."
                                    oninput="refreshTotals()"
                                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                            @endif
                            <p class="text-xs text-slate-400 mt-1">Livraison dès {{ number_format($deliveryDefaultFee, 0, ',', ' ') }} F selon le quartier (hors frais de service).</p>
                        </div>
                    </div>
                </div>

                <!-- Emporter Form -->
                <div id="form-emporter" class="hidden bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="store" class="w-5 h-5 text-primary-500"></i> Informations de Retrait
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nom & Prénoms *</label>
                            <input type="text" id="emporter-name"
                                name="name"
                                autocomplete="name"
                                minlength="2" maxlength="80"
                                placeholder="Prénom et Nom"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Numéro de Téléphone *</label>
                            <input type="tel" id="emporter-phone"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                minlength="8" maxlength="20"
                                pattern="[\d\s\+\-\(\)]{8,20}"
                                placeholder="+225 07 XX XX XX XX"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                    </div>
                </div>

                <!-- Summary & Notes -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="message-square" class="w-5 h-5 text-primary-500"></i> Notes ou instructions de cuisine (optionnel)
                    </h3>
                    <textarea id="order-notes" rows="3"
                        maxlength="500"
                        placeholder="Allergies, cuisson, heure de livraison..."
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 smooth-transition resize-none"></textarea>
                    <p class="text-right text-xs text-slate-400 mt-1"><span id="notes-counter">0</span> / 500</p>
                </div>

                <!-- Order summary list -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-primary-500"></i> Récapitulatif de votre panier
                    </h3>
                    <div id="order-summary" class="space-y-3 mb-4 max-h-60 overflow-y-auto pr-1"></div>
                    <div class="pt-4 border-t border-slate-100 space-y-1.5">
                        <div class="flex justify-between items-center text-sm text-slate-500">
                            <span>Sous-total</span>
                            <span id="order-subtotal">0 F</span>
                        </div>
                        <div class="flex justify-between items-center text-sm text-slate-500">
                            <span>Frais de service (10%)</span>
                            <span id="order-fee">0 F</span>
                        </div>
                        <div id="row-delivery-fee" class="hidden flex justify-between items-center text-sm text-slate-500">
                            <span>Frais de livraison</span>
                            <span id="order-delivery">0 F</span>
                        </div>
                        <p class="text-[11px] text-slate-400 text-right">Payin + retrait + service inclus</p>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-base font-bold text-slate-900">Total à payer</span>
                            <span id="order-total" class="text-2xl font-black text-primary-500">0 F</span>
                        </div>
                    </div>
                </div>

                <!-- Next Actions -->
                <div class="space-y-3">
                    <button type="submit"
                        class="w-full py-4 bg-primary-500 text-white rounded-2xl font-bold hover:bg-primary-600 smooth-transition shadow-lg shadow-primary-500/10 flex items-center justify-center gap-2">
                        <span>Passer à l'étape suivante (Paiement)</span>
                        <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    </button>
                    <button type="button" onclick="backToServiceType()"
                        class="w-full py-3.5 bg-slate-100 text-slate-700 rounded-2xl font-semibold hover:bg-slate-200 smooth-transition">Retour</button>
                </div>
            </form>
        </div>

        <!-- Step 2.5: Paiement MoneyFusion -->
        <div id="step-payment" class="hidden fade-in space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-5">
                <h2 class="text-xl font-bold text-slate-900 text-center">Paiement sécurisé avec MoneyFusion</h2>
                <p class="text-sm text-slate-500 text-center">Payez par Mobile Money (TMoney, Flooz, Orange, MTN...). Vous serez redirigé vers la page de paiement.</p>

                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Montant à payer</p>
                    <p id="mf-amount" class="text-3xl font-black text-slate-900 mt-1">0 F</p>
                    <p class="text-xs text-slate-400 mt-1"><span id="mf-subtotal"></span> + frais service 10% (<span id="mf-fee"></span>)<span id="mf-delivery-wrap" class="hidden"> + livraison (<span id="mf-delivery"></span>)</span></p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nom du payeur *</label>
                        <input type="text" id="mf-nomclient" maxlength="80" placeholder="Nom complet"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 smooth-transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Numéro Mobile Money *</label>
                        <input type="tel" id="mf-numero" inputmode="tel" maxlength="20" placeholder="Ex: 07 12 34 56"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 smooth-transition">
                    </div>
                </div>

                <div id="mf-error-alert" class="hidden bg-red-50 border border-red-100 rounded-2xl p-4 text-sm text-red-700"></div>

                <button onclick="createOrderThenPay()" id="btn-mf-pay"
                    class="w-full py-4 bg-primary-500 hover:bg-primary-600 text-white font-bold rounded-xl smooth-transition shadow-md flex items-center justify-center gap-2">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                    <span id="btn-mf-pay-label">Payer avec MoneyFusion</span>
                </button>
            </div>

            <button type="button" onclick="backToDetails()"
                class="w-full py-3.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 smooth-transition text-center block">
                Retour aux informations client
            </button>
        </div>

        <!-- Step 2.6: Attente / vérification du paiement -->
        <div id="step-waiting" class="hidden fade-in space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6 text-center">
                <h2 class="text-xl font-bold text-slate-900">Paiement en cours...</h2>

                <div class="flex justify-center">
                    <span class="inline-block w-12 h-12 border-4 border-slate-200 border-t-primary-500 rounded-full animate-spin"></span>
                </div>

                <p class="text-sm text-slate-600 leading-relaxed">
                    Commande <span id="waiting-order-id" class="font-bold text-slate-900"></span> créée pour
                    <span id="waiting-amount" class="font-bold text-slate-900"></span>.<br>
                    Finalisez le paiement sur la page MoneyFusion, puis revenez ici : la validation est automatique.
                </p>

                <div id="waiting-error-alert" class="hidden bg-red-50 border border-red-100 rounded-2xl p-4 text-sm text-red-700 text-left"></div>

                <div class="space-y-3">
                    <a id="btn-open-mf" href="#" target="_blank" rel="noopener"
                        class="w-full py-4 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 smooth-transition flex items-center justify-center gap-2">
                        <i data-lucide="external-link" class="w-5 h-5"></i> Ouvrir la page de paiement
                    </a>
                    <button onclick="checkPaymentNow()" id="btn-check-paid"
                        class="w-full py-4 bg-primary-500 hover:bg-primary-600 text-white font-bold rounded-xl smooth-transition flex items-center justify-center gap-2">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i> J'ai payé, vérifier
                    </button>
                </div>
            </div>

            <!-- Cancel Button -->
            <button type="button" onclick="cancelOrder()" id="btn-cancel-order"
                class="w-full py-3.5 bg-red-50 text-red-700 border border-red-200 rounded-xl font-semibold hover:bg-red-100 smooth-transition text-center block">
                Annuler la commande
            </button>
        </div>

        <!-- Step 3: Success -->
        <div id="step-success" class="hidden fade-in text-center">
            <div class="bg-white rounded-3xl p-8 border border-slate-200/80 shadow-md">
                <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner text-green-500">
                    <i data-lucide="badge-check" class="w-12 h-12"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-2">Commande payée et confirmée !</h2>
                <p class="text-slate-500 mb-8 max-w-sm mx-auto">Votre paiement MoneyFusion a été confirmé. Notre équipe commence la préparation.</p>

                <!-- Order Details Summary -->
                <div id="order-summary-success"
                    class="mb-8 text-left bg-slate-50 rounded-2xl p-6 border border-slate-100 hidden">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Résumé de votre commande</h3>
                    <div id="summary-items-list" class="space-y-3 mb-4"></div>
                    <div class="pt-4 border-t border-slate-200/80 space-y-1.5">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Sous-total</span>
                            <span id="summary-subtotal" class="font-bold text-slate-700"></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Frais de service (10%)</span>
                            <span id="summary-fee" class="font-bold text-slate-700"></span>
                        </div>
                        <div id="row-summary-delivery" class="hidden flex justify-between text-sm">
                            <span class="text-slate-500">Frais de livraison</span>
                            <span id="summary-delivery" class="font-bold text-slate-700"></span>
                        </div>
                        <div class="flex justify-between items-center font-extrabold pt-1">
                            <span class="text-slate-900">Total payé</span>
                            <span id="summary-total-amount" class="text-primary-500 text-lg"></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <a id="btn-track-order" href="#"
                        class="w-full py-4 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 smooth-transition flex items-center justify-center gap-2 shadow-lg shadow-orange-200">
                        <i data-lucide="compass" class="w-5 h-5"></i> Suivre ma commande en direct
                    </a>
                    <a id="btn-whatsapp" href="#" target="_blank" rel="noopener"
                        class="w-full py-4 bg-[#25D366] text-white rounded-xl font-bold hover:brightness-95 smooth-transition flex items-center justify-center gap-2 shadow-lg shadow-green-200">
                        <i data-lucide="message-circle" class="w-5 h-5"></i> Suivre sur WhatsApp
                    </a>
                    <button onclick="downloadReceipt()"
                        class="w-full py-3.5 bg-slate-900 text-white rounded-xl font-medium hover:bg-slate-800 smooth-transition flex items-center justify-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5"></i> Télécharger le reçu
                    </button>
                    <button onclick="localStorage.clear(); window.location.href='{{ route('menu') }}'"
                        class="w-full py-3.5 bg-primary-500 text-white rounded-xl font-medium hover:bg-primary-600 smooth-transition">
                        Nouvelle commande
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notification Container -->
        <div id="toast-container" class="fixed bottom-5 right-5 z-[200] transform translate-y-20 opacity-0 smooth-transition pointer-events-none">
            <div class="bg-slate-950 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-800">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>
                <span id="toast-message" class="text-sm font-semibold">Code copié !</span>
            </div>
        </div>

        <script>
            const ORDERS_BASE_URL = "{{ url('orders') }}";
            // Frais de service : 10% (le serveur recalcule et fait foi)
            const SERVICE_FEE_RATE = {{ (float) config('services.moneyfusion.fee_rate', 0.10) }};
            function calcFee(subtotal) { return Math.round(subtotal * SERVICE_FEE_RATE); }
            // Barème livraison : zones spécifiques + forfait par défaut (0 si à emporter)
            const DELIVERY_ZONES = {!! json_encode($deliveryZones ?? []) !!};
            const DELIVERY_DEFAULT_FEE = {{ (int) ($deliveryDefaultFee ?? 1000) }};
            function calcDeliveryFee() {
                if (currentServiceType !== 'livraison') return 0;
                const q = (getNeighborhoodValue() || '').trim().toLowerCase();
                for (const [quartier, tarif] of Object.entries(DELIVERY_ZONES)) {
                    if (q !== '' && q === String(quartier).trim().toLowerCase()) return parseInt(tarif, 10) || 0;
                }
                return q !== '' ? DELIVERY_DEFAULT_FEE : 0;
            }
            let currentServiceType = '';
            let orderData = {};
            let paymentToken = '';
            let paymentUrl = '';
            let pollTimer = null;

            document.addEventListener('DOMContentLoaded', () => {
                // Lucide icon helper
                lucide.createIcons();

                // 1. Gestion de la persistance (Sécurité Anti-Rechargement)
                const paymentInProgress = localStorage.getItem('paiement_en_cours') === 'true';
                if (paymentInProgress) {
                    const lastOrder = JSON.parse(localStorage.getItem('lastOrder'));
                    if (lastOrder && lastOrder.orderId) {
                        orderData = lastOrder;
                        currentServiceType = lastOrder.serviceType;
                        paymentToken = lastOrder.paymentToken || '';
                        paymentUrl = lastOrder.paymentUrl || '';

                        // Mettre à jour l'indicateur d'étape
                        updateStepper(3);

                        // Reprendre l'attente de paiement + polling
                        showWaitingStep();
                        return;
                    }
                }

                // Chargement normal
                const cart = JSON.parse(localStorage.getItem('restaurantCart'));
                if (!cart || cart.length === 0) {
                    alert('Votre panier est vide !');
                    window.location.href = '{{ route('menu') }}';
                    return;
                }
                displayOrderSummary(cart);
            });

            function updateStepper(step) {
                const badge2 = document.getElementById('step-badge-2');
                const badge3 = document.getElementById('step-badge-3');
                const dot2 = document.getElementById('step-dot-2');
                const dot3 = document.getElementById('step-dot-3');

                if (step >= 2) {
                    badge2.className = "w-7 h-7 rounded-full bg-primary-500 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-primary-100";
                    dot2.classList.add('text-primary-500');
                } else {
                    badge2.className = "w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-sm";
                    dot2.classList.remove('text-primary-500');
                }

                if (step >= 3) {
                    badge3.className = "w-7 h-7 rounded-full bg-primary-500 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-primary-100";
                    dot3.classList.add('text-primary-500');
                } else {
                    badge3.className = "w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-sm";
                    dot3.classList.remove('text-primary-500');
                }
            }

            function selectServiceType(type) {
                currentServiceType = type;
                document.getElementById('step-service-type').classList.add('hidden');
                document.getElementById('step-details').classList.remove('hidden');

                document.getElementById('form-livraison').classList.add('hidden');
                document.getElementById('form-emporter').classList.add('hidden');

                document.getElementById(`form-${type}`).classList.remove('hidden');
                refreshTotals();
            }

            function backToServiceType() {
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-service-type').classList.remove('hidden');
            }

            function displayOrderSummary(cart) {
                const container = document.getElementById('order-summary');
                let subtotal = 0;
                container.innerHTML = cart.map(item => {
                    const itemTotal = item.itemPrice * item.quantity;
                    subtotal += itemTotal;
                    return `
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <div>
                            <span class="font-bold text-slate-900">${item.quantity}x ${item.name}</span>
                            <div class="text-xs text-slate-500 font-medium">
                                ${item.selectedOptions.map(o => o.name).join(', ')}
                            </div>
                        </div>
                        <span class="font-extrabold text-slate-900">${itemTotal.toLocaleString('fr-FR')} F</span>
                    </div>
                `;
                }).join('');
                const serviceFee = calcFee(subtotal);
                const deliveryFee = (typeof calcDeliveryFee === 'function') ? calcDeliveryFee() : 0;
                const total = subtotal + serviceFee + deliveryFee;
                document.getElementById('order-subtotal').textContent = `${subtotal.toLocaleString('fr-FR')} F`;
                document.getElementById('order-fee').textContent = `${serviceFee.toLocaleString('fr-FR')} F`;
                const rowDel = document.getElementById('row-delivery-fee');
                if (rowDel) {
                    rowDel.classList.toggle('hidden', deliveryFee <= 0);
                    document.getElementById('order-delivery').textContent = `${deliveryFee.toLocaleString('fr-FR')} F`;
                }
                document.getElementById('order-total').textContent = `${total.toLocaleString('fr-FR')} F`;
                orderData.subtotal = subtotal;
                orderData.serviceFee = serviceFee;
                orderData.deliveryFee = deliveryFee;
                orderData.total = total;
                orderData.items = cart;
            }

            // Quartier effectif (select + champ "Autre" ou saisie libre)
            function getNeighborhoodValue() {
                const sel = document.getElementById('livraison-neighborhood');
                if (!sel) return '';
                if (sel.tagName === 'SELECT') {
                    if (sel.value === '__autre') {
                        return document.getElementById('livraison-neighborhood-autre')?.value?.trim() ?? '';
                    }
                    return sel.value;
                }
                return sel.value?.trim() ?? '';
            }

            function onQuartierChange() {
                const sel = document.getElementById('livraison-neighborhood');
                const autre = document.getElementById('livraison-neighborhood-autre');
                if (autre) autre.classList.toggle('hidden', sel.value !== '__autre');
                clearFieldError('livraison-neighborhood');
                refreshTotals();
            }

            function refreshTotals() {
                const cart = orderData.items || [];
                if (cart.length) displayOrderSummary(cart);
            }

            // Toast helper
            function showToast(message, isSuccess = true) {
                const toast = document.getElementById('toast-container');
                const msgEl = document.getElementById('toast-message');
                msgEl.textContent = message;
                
                const iconEl = toast.querySelector('i');
                if (isSuccess) {
                    iconEl.innerHTML = '<polyline points="20 6 9 17 4 12"></polyline>';
                    iconEl.className = 'w-5 h-5 text-green-400 shrink-0';
                } else {
                    iconEl.innerHTML = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>';
                    iconEl.className = 'w-5 h-5 text-red-400 shrink-0';
                }
                
                toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
                toast.classList.add('translate-y-0', 'opacity-100');
                
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
                }, 4000);
                lucide.createIcons();
            }

            // ── Validation helpers ──────────────────────────────────────────────
            const PHONE_REGEX = /^[\d\s\+\-\(\)]{8,20}$/;
            const NAME_MIN = 2, NAME_MAX = 80;
            const NEIGHBORHOOD_MIN = 3, NEIGHBORHOOD_MAX = 100;
            const NOTES_MAX = 500;

            function sanitize(str) {
                const d = document.createElement('div');
                d.appendChild(document.createTextNode(str));
                return d.innerHTML;
            }

            function setFieldError(id, msg) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.add('border-red-500', 'ring-2', 'ring-red-100');
                el.classList.remove('border-slate-300');
                let errEl = document.getElementById(id + '-err');
                if (!errEl) {
                    errEl = document.createElement('p');
                    errEl.id = id + '-err';
                    errEl.className = 'text-xs text-red-500 mt-1 font-semibold';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = msg;
            }

            function clearFieldError(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.remove('border-red-500', 'ring-2', 'ring-red-100');
                el.classList.add('border-slate-300');
                const errEl = document.getElementById(id + '-err');
                if (errEl) errEl.textContent = '';
            }

            function validateName(id) {
                const val = document.getElementById(id)?.value?.trim() ?? '';
                if (!val) { setFieldError(id, 'Le nom est obligatoire.'); return false; }
                if (val.length < NAME_MIN) { setFieldError(id, `Minimum ${NAME_MIN} caractères.`); return false; }
                if (val.length > NAME_MAX) { setFieldError(id, `Maximum ${NAME_MAX} caractères.`); return false; }
                if (/[<>\"'%;()&]/.test(val)) { setFieldError(id, 'Caractères invalides.'); return false; }
                clearFieldError(id); return val;
            }

            function validatePhone(id) {
                const val = document.getElementById(id)?.value?.trim() ?? '';
                if (!val) { setFieldError(id, 'Le téléphone est obligatoire.'); return false; }
                if (!PHONE_REGEX.test(val)) { setFieldError(id, 'Format invalide (ex: +225 07 12 34 56).'); return false; }
                clearFieldError(id); return val;
            }

            function validateNeighborhood(id) {
                const sel = document.getElementById(id);
                const isSelect = sel && sel.tagName === 'SELECT';
                const targetId = (isSelect && sel.value === '__autre') ? 'livraison-neighborhood-autre' : id;
                const val = (getNeighborhoodValue() || '').trim();
                if (!val || (isSelect && sel.value === '')) { setFieldError(targetId, 'Veuillez choisir votre quartier.'); return false; }
                if (val.length < NEIGHBORHOOD_MIN) { setFieldError(targetId, `Minimum ${NEIGHBORHOOD_MIN} caractères.`); return false; }
                if (val.length > NEIGHBORHOOD_MAX) { setFieldError(targetId, `Maximum ${NEIGHBORHOOD_MAX} caractères.`); return false; }
                clearFieldError('livraison-neighborhood');
                clearFieldError('livraison-neighborhood-autre');
                return val;
            }

            // Attach validation events
            document.addEventListener('DOMContentLoaded', () => {
                ['livraison-name','emporter-name'].forEach(id => {
                    document.getElementById(id)?.addEventListener('blur', () => validateName(id));
                    document.getElementById(id)?.addEventListener('input', () => {
                        const v = document.getElementById(id)?.value?.trim();
                        if (v?.length >= NAME_MIN) clearFieldError(id);
                    });
                });
                ['livraison-phone','emporter-phone'].forEach(id => {
                    document.getElementById(id)?.addEventListener('blur', () => validatePhone(id));
                });
                document.getElementById('livraison-neighborhood')?.addEventListener('blur', () => validateNeighborhood('livraison-neighborhood'));
                document.getElementById('livraison-neighborhood')?.addEventListener('change', () => validateNeighborhood('livraison-neighborhood'));
                document.getElementById('livraison-neighborhood-autre')?.addEventListener('blur', () => validateNeighborhood('livraison-neighborhood'));

                document.getElementById('order-notes')?.addEventListener('input', function() {
                    if (this.value.length > NOTES_MAX) this.value = this.value.slice(0, NOTES_MAX);
                    const counter = document.getElementById('notes-counter');
                    if (counter) counter.textContent = `${this.value.length} / ${NOTES_MAX}`;
                });
            });
            // ── End validation ──────────────────────────────────────────────────

            async function handleSubmit(e) {
                e.preventDefault();
                let valid = true;

                if (currentServiceType === 'livraison') {
                    const name  = validateName('livraison-name');
                    const phone = validatePhone('livraison-phone');
                    const hood  = validateNeighborhood('livraison-neighborhood');
                    if (!name || !phone || !hood) { valid = false; }
                    else {
                        orderData.name         = sanitize(name);
                        orderData.phone        = sanitize(phone);
                        orderData.neighborhood = sanitize(hood);
                    }
                } else if (currentServiceType === 'emporter') {
                    const name  = validateName('emporter-name');
                    const phone = validatePhone('emporter-phone');
                    if (!name || !phone) { valid = false; }
                    else {
                        orderData.name  = sanitize(name);
                        orderData.phone = sanitize(phone);
                    }
                }

                if (!valid) return;

                // Transition vers le paiement MoneyFusion (pré-remplissage)
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-payment').classList.remove('hidden');
                document.getElementById('mf-amount').textContent = formatAmount(orderData.total);
                document.getElementById('mf-subtotal').textContent = formatAmount(orderData.subtotal || 0);
                document.getElementById('mf-fee').textContent = formatAmount(orderData.serviceFee || 0);
                const mfDelWrap = document.getElementById('mf-delivery-wrap');
                if (mfDelWrap) {
                    mfDelWrap.classList.toggle('hidden', !(orderData.deliveryFee > 0));
                    document.getElementById('mf-delivery').textContent = formatAmount(orderData.deliveryFee || 0);
                }
                document.getElementById('btn-mf-pay-label').textContent = `Payer ${formatAmount(orderData.total)} avec MoneyFusion`;
                if (orderData.name) document.getElementById('mf-nomclient').value = stripTags(orderData.name);
                if (orderData.phone) document.getElementById('mf-numero').value = stripTags(orderData.phone);

                // Mettre à jour l'indicateur d'étape
                updateStepper(2);
            }

            function stripTags(s) {
                const d = document.createElement('div');
                d.innerHTML = s;
                return d.textContent || '';
            }

            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            }

            function mfError(msg) {
                const el = document.getElementById('mf-error-alert');
                el.textContent = msg;
                el.classList.remove('hidden');
            }

            async function createOrderThenPay() {
                const nomclient = (document.getElementById('mf-nomclient').value || '').trim();
                const numeroSend = (document.getElementById('mf-numero').value || '').replace(/\s+/g, '');
                document.getElementById('mf-error-alert').classList.add('hidden');

                if (nomclient.length < 2) { mfError('Veuillez saisir le nom du payeur.'); return; }
                if (!/^[\d+\-()]{8,20}$/.test(numeroSend)) { mfError('Numéro Mobile Money invalide.'); return; }

                const btn = document.getElementById('btn-mf-pay');
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span>Création de la commande...';

                try {
                    // 1. Créer la commande (En attente de paiement)
                    const notesRaw = (document.getElementById('order-notes').value || '').trim().slice(0, NOTES_MAX);
                    orderData.serviceType = currentServiceType;
                    orderData.notes = sanitize(notesRaw);
                    orderData.paymentMethod = 'moneyfusion';

                    let resp = await fetch('{{ route('orders.store') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                        body: JSON.stringify(orderData)
                    });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    let created = await resp.json();
                    if (!created.success) throw new Error(created.message || 'Commande refusée');

                    orderData.orderId = created.order_id;
                    // Totaux serveur (frais recalculés, font foi)
                    if (created.subtotal !== undefined) orderData.subtotal = created.subtotal;
                    if (created.service_fee !== undefined) orderData.serviceFee = created.service_fee;
                    if (created.delivery_fee !== undefined) orderData.deliveryFee = created.delivery_fee;
                    if (created.total !== undefined) orderData.total = created.total;
                    await saveOrderToIndexedDB(created.order_id, orderData.total);

                    // 2. Initier le paiement MoneyFusion
                    btn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span>Connexion à MoneyFusion...';
                    resp = await fetch(`${ORDERS_BASE_URL}/${created.order_id}/moneyfusion/initiate`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                        body: JSON.stringify({ numeroSend, nomclient })
                    });
                    const init = await resp.json();
                    if (!init.success) throw new Error(init.message || 'MoneyFusion indisponible');

                    paymentToken = init.token;
                    paymentUrl = init.payment_url;
                    orderData.paymentToken = paymentToken;
                    orderData.paymentUrl = paymentUrl;

                    localStorage.setItem('paiement_en_cours', 'true');
                    localStorage.setItem('lastOrder', JSON.stringify(orderData));
                    localStorage.removeItem('restaurantCart');

                    // 3. Ouvrir la page de paiement + afficher l'attente
                    window.open(paymentUrl, '_blank');
                    showWaitingStep();
                } catch (err) {
                    console.error(err);
                    mfError(err.message || 'Erreur lors du paiement.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            }

            function showWaitingStep() {
                document.getElementById('step-payment').classList.add('hidden');
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-service-type').classList.add('hidden');
                document.getElementById('step-waiting').classList.remove('hidden');

                document.getElementById('waiting-order-id').textContent = '#' + (orderData.orderId || '');
                document.getElementById('waiting-amount').textContent = formatAmount(orderData.total || 0);
                const openBtn = document.getElementById('btn-open-mf');
                if (openBtn && paymentUrl) openBtn.href = paymentUrl;

                updateStepper(3);
                startPolling();
                lucide.createIcons();
            }

            function startPolling() {
                stopPolling();
                pollTimer = setInterval(checkPaymentNow, 5000);
            }

            function stopPolling() {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            }

            async function checkPaymentNow() {
                if (!orderData.orderId) return;
                const errEl = document.getElementById('waiting-error-alert');
                errEl.classList.add('hidden');

                const btn = document.getElementById('btn-check-paid');
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span>Vérification...';

                try {
                    const resp = await fetch(`${ORDERS_BASE_URL}/${orderData.orderId}/moneyfusion/status`);
                    const result = await resp.json();

                    if (result.paid) {
                        stopPolling();
                        showPaymentSuccess();
                    } else if (result.status && !['pending', ''].includes(String(result.status))) {
                        errEl.textContent = 'Paiement : ' + result.status + '. Réessayez ou annulez la commande.';
                        errEl.classList.remove('hidden');
                    } else {
                        showToast('Paiement toujours en attente...', false);
                    }
                } catch (err) {
                    errEl.textContent = 'Vérification impossible pour le moment. Réessayez.';
                    errEl.classList.remove('hidden');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            }

            function showPaymentSuccess() {
                stopPolling();
                localStorage.removeItem('paiement_en_cours');
                localStorage.removeItem('lastOrder');

                document.getElementById('step-waiting').classList.add('hidden');
                document.getElementById('step-success').classList.remove('hidden');

                const trackBtn = document.getElementById('btn-track-order');
                if (trackBtn) trackBtn.href = `{{ route('track') }}?id=${orderData.orderId}`;

                const waBtn = document.getElementById('btn-whatsapp');
                if (waBtn) {
                    const waText = encodeURIComponent(`Salut, j'aimerais suivre ma commande N°${orderData.orderId} merci`);
                    waBtn.href = `https://wa.me/22899215580?text=${waText}`;
                }

                const summaryDiv  = document.getElementById('order-summary-success');
                const summaryList = document.getElementById('summary-items-list');
                const summaryTot  = document.getElementById('summary-total-amount');
                if (summaryDiv && summaryList && summaryTot) {
                    summaryList.innerHTML = orderData.items.map(item => `
                        <div class="flex justify-between text-sm py-1">
                            <span class="text-slate-600">${item.quantity}x ${item.name}</span>
                            <span class="font-bold text-slate-900">${formatAmount(item.itemPrice * item.quantity)}</span>
                        </div>
                    `).join('');
                    document.getElementById('summary-subtotal').textContent = formatAmount(orderData.subtotal || 0);
                    document.getElementById('summary-fee').textContent = formatAmount(orderData.serviceFee || 0);
                    const rowSumDel = document.getElementById('row-summary-delivery');
                    if (rowSumDel) {
                        rowSumDel.classList.toggle('hidden', !(orderData.deliveryFee > 0));
                        document.getElementById('summary-delivery').textContent = formatAmount(orderData.deliveryFee || 0);
                    }
                    summaryTot.textContent = formatAmount(orderData.total);
                    summaryDiv.classList.remove('hidden');
                }

                showToast('Paiement confirmé ! Merci de votre confiance.');
                lucide.createIcons();
            }

            function backToDetails() {
                document.getElementById('step-payment').classList.add('hidden');
                document.getElementById('step-details').classList.remove('hidden');
                updateStepper(1);
            }

            async function cancelOrder() {
                if (!confirm("Voulez-vous vraiment annuler cette commande ? Vos articles seront replacés dans votre panier.")) {
                    return;
                }

                const btnCancel = document.getElementById('btn-cancel-order');

                const originalText = btnCancel.textContent;
                btnCancel.disabled = true;
                stopPolling();
                btnCancel.textContent = 'Annulation en cours...';

                try {
                    const response = await fetch(`${ORDERS_BASE_URL}/${orderData.orderId}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Restaurer le panier intact dans le LocalStorage
                        localStorage.setItem('restaurantCart', JSON.stringify(orderData.items));

                        // Nettoyer les clés de paiement
                        localStorage.removeItem('paiement_en_cours');
                        localStorage.removeItem('lastOrder');

                        showToast("Commande annulée. Panier restauré.");
                        
                        setTimeout(() => {
                            window.location.href = '{{ route('menu') }}';
                        }, 1200);
                    } else {
                        alert("Impossible d'annuler : " + result.message);
                        btnCancel.disabled = false;
                        btnCancel.textContent = originalText;
                    }
                } catch (err) {
                    console.error(err);
                    alert("Erreur de connexion lors de l'annulation de la commande.");
                    btnCancel.disabled = false;
                    btnCancel.textContent = originalText;
                }
            }

            function formatAmount(amt) {
                return amt.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' F';
            }

            function downloadReceipt() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Header: Dark Bar
                doc.setFillColor(15, 23, 42);
                doc.rect(0, 0, 210, 40, 'F');

                doc.setTextColor(255, 255, 255);
                doc.setFontSize(26);
                doc.setFont('helvetica', 'bold');
                doc.text('OPERA RESTO', 105, 22, { align: 'center' });

                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text('REÇU DE COMMANDE PAYÉ - MERCI DE VOTRE CONFIANCE', 105, 32, { align: 'center' });

                // Content
                doc.setTextColor(40, 40, 40);
                doc.setFontSize(12);
                doc.setFont('helvetica', 'bold');
                doc.text('DÉTAILS DE LA TRANSACTION', 20, 55);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                const date = new Date().toLocaleString('fr-FR');
                doc.text(`Date : ${date}`, 20, 65);
                doc.text(`Mode de retrait : ${currentServiceType.toUpperCase()}`, 20, 71);
                doc.text(`Moyen de paiement : MoneyFusion`, 20, 77);
                if (orderData.name) doc.text(`Client : ${orderData.name}`, 20, 83);
                if (orderData.phone) doc.text(`Téléphone : ${orderData.phone}`, 20, 89);
                if (orderData.neighborhood) doc.text(`Lieu de livraison : ${orderData.neighborhood}`, 20, 95);

                // Table
                let y = 110;
                doc.setFillColor(245, 245, 245);
                doc.rect(20, y, 170, 10, 'F');
                doc.setFont('helvetica', 'bold');
                doc.text('ARTICLE', 25, y + 7);
                doc.text('QTÉ', 140, y + 7);
                doc.text('TOTAL', 170, y + 7);

                y += 20;
                doc.setFont('helvetica', 'normal');
                orderData.items.forEach(item => {
                    const totalItem = formatAmount(item.itemPrice * item.quantity);
                    doc.setFont('helvetica', 'bold');
                    doc.text(`${item.name}`, 25, y);
                    doc.text(`${item.quantity}`, 140, y);
                    doc.text(totalItem, 170, y);

                    y += 5;
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(120, 120, 120);
                    const options = item.selectedOptions.map(o => (typeof o === 'object' ? o.name : o)).join(', ');
                    const splitOptions = doc.splitTextToSize(options, 110);
                    doc.text(splitOptions, 25, y);

                    y += (splitOptions.length * 4) + 6;
                    doc.setFontSize(10);
                    doc.setTextColor(40, 40, 40);

                    if (y > 270) { doc.addPage(); y = 20; }
                });

                // Total
                y += 5;
                doc.setDrawColor(200, 200, 200);
                doc.line(120, y, 190, y);
                y += 8;
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text(`Sous-total : ${formatAmount(orderData.subtotal || 0)}`, 120, y);
                y += 6;
                doc.text(`Frais de service (10%) : ${formatAmount(orderData.serviceFee || 0)}`, 120, y);
                y += 6;
                if (orderData.deliveryFee > 0) {
                    doc.text(`Frais de livraison : ${formatAmount(orderData.deliveryFee)}`, 120, y);
                    y += 6;
                }
                y += 10;
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text('TOTAL GÉNÉRAL (PAYÉ)', 120, y);
                doc.setTextColor(234, 88, 12);
                doc.text(formatAmount(orderData.total), 170, y);

                doc.save(`recu-paye-opera-${Date.now()}.pdf`);
            }
        </script>
</body>

</html>
