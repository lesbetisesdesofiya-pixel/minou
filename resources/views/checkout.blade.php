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
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Quartier & Précisions *</label>
                            <input type="text" id="livraison-neighborhood"
                                name="neighborhood"
                                autocomplete="address-level2"
                                minlength="3" maxlength="100"
                                placeholder="Ex: Cocody, Yopougon..."
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
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
                    <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                        <span class="text-base font-bold text-slate-900">Total à payer</span>
                        <span id="order-total" class="text-2xl font-black text-primary-500">0 F</span>
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

        <!-- Step 2.5: Payment Method selection & USSD Copy (Étape 1 de paiement) -->
        <div id="step-payment" class="hidden fade-in space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h2 class="text-xl font-bold text-slate-900 text-center mb-6">Sélectionnez votre moyen de paiement</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <!-- Tmoney Card -->
                    <button onclick="selectPaymentMethod('tmoney')" id="btn-pay-tmoney"
                        class="p-5 border-2 border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-3 hover:border-orange-500 hover:bg-orange-50/20 smooth-transition group">
                        <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center font-black text-orange-600 text-2xl group-hover:scale-110 smooth-transition">T</div>
                        <div class="text-center">
                            <span class="font-bold text-slate-800 block">TMoney</span>
                            <span class="text-xs text-slate-400">Togo Cellulaire</span>
                        </div>
                    </button>
                    <!-- Flooz Card -->
                    <button onclick="selectPaymentMethod('flooz')" id="btn-pay-flooz"
                        class="p-5 border-2 border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-3 hover:border-blue-500 hover:bg-blue-50/20 smooth-transition group">
                        <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center font-black text-blue-600 text-2xl group-hover:scale-110 smooth-transition">F</div>
                        <div class="text-center">
                            <span class="font-bold text-slate-800 block">Flooz</span>
                            <span class="text-xs text-slate-400">Moov Africa</span>
                        </div>
                    </button>
                </div>

                <!-- USSD Code Details (Dynamic) -->
                <div id="ussd-container" class="hidden bg-slate-50 rounded-2xl p-5 border border-slate-100/80 space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Instructions de transfert</h3>
                    
                    <p class="text-sm text-slate-600 text-center">
                        Voici le code USSD généré automatiquement pour payer votre commande de <span id="ussd-amount" class="font-bold text-slate-900"></span> :
                    </p>
                    
                    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                        <code id="ussd-code-text" class="text-sm md:text-base font-mono font-bold text-primary-500 select-all overflow-x-auto whitespace-nowrap mr-2"></code>
                        <button onclick="copyUssdCode()" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg smooth-transition shrink-0">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copier
                        </button>
                    </div>

                    <p class="text-xs text-slate-400 text-center leading-relaxed">
                        Pour Flooz Moov, une référence de transaction unique a été insérée de manière sécurisée pour votre suivi.
                    </p>

                    <button onclick="confirmCopyAndPay()" id="btn-confirm-pay"
                        class="w-full py-4 bg-primary-500 hover:bg-primary-600 text-white font-bold rounded-xl smooth-transition shadow-md shadow-primary-500/10 flex items-center justify-center gap-2">
                        <i data-lucide="clipboard-copy" class="w-5 h-5"></i>
                        <span>Copier le code et payer</span>
                    </button>
                </div>
            </div>
            
            <button type="button" onclick="backToDetails()"
                class="w-full py-3.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 smooth-transition text-center block">
                Retour aux informations client
            </button>
        </div>

        <!-- Step 2.6: Screenshot upload & cancellation (Étape 2 de paiement) -->
        <div id="step-screenshot" class="hidden fade-in space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
                <h2 class="text-xl font-bold text-slate-900 text-center">Étape 2 : Confirmez votre paiement</h2>
                
                <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5 text-sm text-amber-800 flex gap-3 shadow-inner">
                    <i data-lucide="info" class="w-5 h-5 shrink-0 mt-0.5 text-amber-600"></i>
                    <div>
                        <p class="font-bold text-amber-950">Commande en attente de paiement !</p>
                        <p class="mt-0.5 leading-relaxed">Le code USSD a été copié. Veuillez composer le code sur votre téléphone pour effectuer le paiement de <span id="screenshot-order-amount" class="font-bold"></span>.</p>
                        <p class="mt-2 font-bold text-amber-950">Une fois le SMS de reçu reçu, faites une capture d'écran et déposez-la ci-dessous.</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="block text-sm font-semibold text-slate-700">Capture d'écran du reçu SMS de paiement *</span>
                    
                    <label class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed border-slate-300 rounded-2xl cursor-pointer hover:bg-slate-50 hover:border-primary-500 smooth-transition" id="screenshot-dropzone">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6 space-y-2 text-center px-4">
                            <i data-lucide="image" class="w-12 h-12 text-slate-400" id="upload-icon"></i>
                            <p class="text-sm text-slate-600 font-bold" id="upload-text">Cliquez ici pour sélectionner l'image du reçu</p>
                            <p class="text-xs text-slate-400">Formats supportés : JPG, JPEG, PNG</p>
                        </div>
                        <input type="file" id="screenshot-input" accept="image/*" class="hidden" onchange="handleFileSelected(event)" />
                    </label>

                    <div id="screenshot-preview-container" class="hidden relative rounded-2xl overflow-hidden border border-slate-200 bg-slate-50 max-h-64 flex items-center justify-center p-3">
                        <img id="screenshot-preview" class="max-h-56 rounded-xl object-contain shadow-sm">
                        <button onclick="removeSelectedFile()" class="absolute top-4 right-4 p-2 bg-red-600 hover:bg-red-700 text-white rounded-full smooth-transition shadow-md">
                            <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                        </button>
                    </div>
                </div>

                <!-- Payment validation alert error -->
                <div id="payment-error-alert" class="hidden bg-red-50 border border-red-100 rounded-2xl p-4 text-sm text-red-700 flex gap-2.5">
                    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5 text-red-600"></i>
                    <div>
                        <p class="font-bold text-red-950">Erreur de validation</p>
                        <p id="payment-error-message" class="mt-0.5 leading-relaxed"></p>
                    </div>
                </div>

                <!-- Validation Action Buttons -->
                <button onclick="submitScreenshot()" id="btn-validate-payment" disabled
                    class="w-full py-4 bg-primary-500 hover:bg-primary-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-2xl smooth-transition shadow-lg flex items-center justify-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    <span>Valider la commande</span>
                </button>
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
                <p class="text-slate-500 mb-8 max-w-sm mx-auto">Votre reçu a été validé avec succès par notre IA. Notre équipe commence la préparation.</p>

                <!-- Order Details Summary -->
                <div id="order-summary-success"
                    class="mb-8 text-left bg-slate-50 rounded-2xl p-6 border border-slate-100 hidden">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Résumé de votre commande</h3>
                    <div id="summary-items-list" class="space-y-3 mb-4"></div>
                    <div class="pt-4 border-t border-slate-200/80 flex justify-between items-center font-extrabold">
                        <span class="text-slate-900">Total payé</span>
                        <span id="summary-total-amount" class="text-primary-500 text-lg"></span>
                    </div>
                </div>

                <div class="space-y-3">
                    <a id="btn-track-order" href="#"
                        class="w-full py-4 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 smooth-transition flex items-center justify-center gap-2 shadow-lg shadow-orange-200">
                        <i data-lucide="compass" class="w-5 h-5"></i> Suivre ma commande en direct
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
            let currentServiceType = '';
            let orderData = {};
            let selectedPaymentMethod = '';
            let ussdCode = '';
            let generatedReference = '';
            let selectedFile = null;

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
                        selectedPaymentMethod = lastOrder.paymentMethod;
                        generatedReference = lastOrder.transactionReference;

                        // Mettre à jour l'indicateur d'étape
                        updateStepper(3);

                        // Afficher le bon montant
                        document.getElementById('screenshot-order-amount').textContent = formatAmount(orderData.total);

                        // Afficher directement l'étape 2 (Dépôt du reçu)
                        showPaymentStep2(lastOrder);
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
            }

            function backToServiceType() {
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-service-type').classList.remove('hidden');
            }

            function displayOrderSummary(cart) {
                const container = document.getElementById('order-summary');
                let total = 0;
                container.innerHTML = cart.map(item => {
                    const itemTotal = item.itemPrice * item.quantity;
                    total += itemTotal;
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
                document.getElementById('order-total').textContent = `${total.toLocaleString('fr-FR')} F`;
                orderData.total = total;
                orderData.items = cart;
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
                const val = document.getElementById(id)?.value?.trim() ?? '';
                if (!val) { setFieldError(id, 'Le quartier est obligatoire.'); return false; }
                if (val.length < NEIGHBORHOOD_MIN) { setFieldError(id, `Minimum ${NEIGHBORHOOD_MIN} caractères.`); return false; }
                if (val.length > NEIGHBORHOOD_MAX) { setFieldError(id, `Maximum ${NEIGHBORHOOD_MAX} caractères.`); return false; }
                clearFieldError(id); return val;
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

                // Transition vers l'Étape 1 du Paiement
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-payment').classList.remove('hidden');

                // Mettre à jour l'indicateur d'étape
                updateStepper(2);
            }

            function selectPaymentMethod(method) {
                selectedPaymentMethod = method;
                
                // Mettre à jour les boutons visuellement
                const btnTmoney = document.getElementById('btn-pay-tmoney');
                const btnFlooz = document.getElementById('btn-pay-flooz');
                
                if (method === 'tmoney') {
                    btnTmoney.className = "p-5 border-2 border-orange-500 bg-orange-50/20 rounded-2xl flex flex-col items-center justify-center gap-3 smooth-transition group shadow-sm";
                    btnFlooz.className = "p-5 border border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-3 hover:border-blue-500 hover:bg-blue-50/20 smooth-transition group";
                } else {
                    btnFlooz.className = "p-5 border-2 border-blue-500 bg-blue-50/20 rounded-2xl flex flex-col items-center justify-center gap-3 smooth-transition group shadow-sm";
                    btnTmoney.className = "p-5 border border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-3 hover:border-orange-500 hover:bg-orange-50/20 smooth-transition group";
                }

                const amount = orderData.total;
                document.getElementById('ussd-amount').textContent = formatAmount(amount);

                // Récupération des codes marchands depuis le backend inséré via Blade
                if (method === 'tmoney') {
                    const merchantTmoney = "{{ env('CODE_MARCHAND_TMONEY', '123456') }}";
                    ussdCode = `*145*5*${amount}*${merchantTmoney}#`;
                    generatedReference = '';
                } else {
                    const merchantMoov = "{{ env('CODE_MARCHAND_MOOV', '654321') }}";
                    // Génération d'une référence Flooz unique
                    generatedReference = 'FLZ' + Date.now().toString().slice(-6) + Math.floor(Math.random() * 1000);
                    ussdCode = `*155*2*1*${generatedReference}*${merchantMoov}*${merchantMoov}*${amount}#`;
                }

                document.getElementById('ussd-code-text').textContent = ussdCode;
                document.getElementById('ussd-container').classList.remove('hidden');
                lucide.createIcons();
            }

            function copyUssdCode() {
                if (!ussdCode) return;
                navigator.clipboard.writeText(ussdCode).then(() => {
                    showToast("Code USSD copié avec succès !");
                }).catch(err => {
                    console.error("Erreur de copie : ", err);
                    showToast("Erreur de copie, sélectionnez le code à la main.", false);
                });
            }

            async function confirmCopyAndPay() {
                // 1. Copier le code
                copyUssdCode();

                // Préparer les données de commande
                const notesRaw = (document.getElementById('order-notes').value || '').trim().slice(0, NOTES_MAX);
                orderData.serviceType = currentServiceType;
                orderData.notes       = sanitize(notesRaw);
                orderData.paymentMethod = selectedPaymentMethod;
                orderData.transactionReference = generatedReference || null;

                const btnConfirm = document.getElementById('btn-confirm-pay');
                const originalText = btnConfirm.innerHTML;
                btnConfirm.disabled = true;
                btnConfirm.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span>Création de la commande...';

                // 2. Envoi au serveur pour créer la commande en statut "En attente de paiement"
                try {
                    const response = await fetch('{{ route('orders.store') }}', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(orderData)
                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const result = await response.json();

                    if (result.success) {
                        orderData.orderId = result.order_id;
                        
                        // 3. Sauvegarde dans le LocalStorage
                        localStorage.setItem('paiement_en_cours', 'true');
                        localStorage.setItem('lastOrder', JSON.stringify(orderData));
                        localStorage.removeItem('restaurantCart'); // Panier vidé de la boutique principale

                        await saveOrderToIndexedDB(result.order_id, orderData.total);

                        // Afficher le montant sur l'étape suivante
                        document.getElementById('screenshot-order-amount').textContent = formatAmount(orderData.total);

                        // 4. Basculer instantanément sur l'Étape 2 (Dépôt du reçu)
                        showPaymentStep2(orderData);
                    } else {
                        alert('Erreur: ' + (result.message || 'Impossible d\'enregistrer la commande.'));
                        btnConfirm.disabled = false;
                        btnConfirm.innerHTML = originalText;
                    }
                } catch (err) {
                    console.error(err);
                    alert('Une erreur serveur est survenue. Veuillez réessayer.');
                    btnConfirm.disabled = false;
                    btnConfirm.innerHTML = originalText;
                }
            }

            function showPaymentStep2(lastOrder) {
                document.getElementById('step-payment').classList.add('hidden');
                document.getElementById('step-details').classList.add('hidden');
                document.getElementById('step-service-type').classList.add('hidden');
                
                document.getElementById('step-screenshot').classList.remove('hidden');
                
                updateStepper(3);
                lucide.createIcons();
            }

            function backToDetails() {
                document.getElementById('step-payment').classList.add('hidden');
                document.getElementById('step-details').classList.remove('hidden');
                updateStepper(1);
            }

            // Gestion de l'upload et de la preview
            function handleFileSelected(e) {
                const file = e.target.files[0];
                if (!file) return;

                selectedFile = file;

                // Activer le bouton de validation
                document.getElementById('btn-validate-payment').disabled = false;

                // Afficher l'aperçu de l'image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('screenshot-preview').src = e.target.result;
                    document.getElementById('screenshot-dropzone').classList.add('hidden');
                    document.getElementById('screenshot-preview-container').classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }

            function removeSelectedFile() {
                selectedFile = null;
                document.getElementById('screenshot-input').value = '';
                document.getElementById('btn-validate-payment').disabled = true;
                
                document.getElementById('screenshot-preview-container').classList.add('hidden');
                document.getElementById('screenshot-dropzone').classList.remove('hidden');
            }

            async function submitScreenshot() {
                if (!selectedFile) return;

                const btn = document.getElementById('btn-validate-payment');
                const btnCancel = document.getElementById('btn-cancel-order');
                const errorAlert = document.getElementById('payment-error-alert');
                
                errorAlert.classList.add('hidden');

                const originalText = btn.innerHTML;
                btn.disabled = true;
                btnCancel.disabled = true;
                btn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span>Vérification de votre reçu en cours avec l\'IA Gemini...';

                const formData = new FormData();
                formData.append('screenshot', selectedFile);

                try {
                    const response = await fetch(`/orders/${orderData.orderId}/validate-payment`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Succès complet ! Nettoyer le LocalStorage
                        localStorage.removeItem('paiement_en_cours');
                        localStorage.removeItem('lastOrder');

                        // Transitionner vers l'écran final de succès
                        document.getElementById('step-screenshot').classList.add('hidden');
                        document.getElementById('step-success').classList.remove('hidden');

                        // Mettre à jour les liens de tracking
                        const trackBtn = document.getElementById('btn-track-order');
                        if (trackBtn) trackBtn.href = `{{ route('track') }}?id=${orderData.orderId}`;

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
                            summaryTot.textContent = formatAmount(orderData.total);
                            summaryDiv.classList.remove('hidden');
                        }

                        showToast("Paiement validé avec succès ! Merci de votre confiance.");
                    } else {
                        // Échec de validation automatique
                        document.getElementById('payment-error-message').textContent = result.message || "La capture d'écran n'est pas valide. Veuillez vérifier votre reçu et réessayer.";
                        errorAlert.classList.remove('hidden');

                        btn.disabled = false;
                        btnCancel.disabled = false;
                        btn.innerHTML = originalText;
                    }
                } catch (err) {
                    console.error(err);
                    document.getElementById('payment-error-message').textContent = "Erreur serveur ou de connexion avec l'IA. Veuillez vérifier votre réseau et réessayer.";
                    errorAlert.classList.remove('hidden');

                    btn.disabled = false;
                    btnCancel.disabled = false;
                    btn.innerHTML = originalText;
                }
            }

            async function cancelOrder() {
                if (!confirm("Voulez-vous vraiment annuler cette commande ? Vos articles seront replacés dans votre panier.")) {
                    return;
                }

                const btnCancel = document.getElementById('btn-cancel-order');
                const btnValidate = document.getElementById('btn-validate-payment');
                
                const originalText = btnCancel.textContent;
                btnCancel.disabled = true;
                btnValidate.disabled = true;
                btnCancel.textContent = 'Annulation en cours...';

                try {
                    const response = await fetch(`/orders/${orderData.orderId}/cancel`, {
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
                        btnValidate.disabled = false;
                        btnCancel.textContent = originalText;
                    }
                } catch (err) {
                    console.error(err);
                    alert("Erreur de connexion lors de l'annulation de la commande.");
                    btnCancel.disabled = false;
                    btnValidate.disabled = false;
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
                doc.text(`Moyen de paiement : ${selectedPaymentMethod.toUpperCase()}`, 20, 77);
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
