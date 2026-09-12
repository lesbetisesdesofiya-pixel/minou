<?php
// No backend require needed for view, JS handles data
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande</title>
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
                    status: 'En attente',
                    total
                });
            } catch (err) { console.error("IndexedDB Save Error:", err); }
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

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: #ff6b35;
        }
    </style>
</head>

<body class="bg-gray-50 antialiased min-h-screen">

    <div class="max-w-3xl mx-auto px-4 py-6 md:py-8">
        <!-- Header -->
        <header class="mb-8 fade-in">
            <a href="index.php"
                class="flex items-center gap-2 text-gray-700 hover:text-gray-900 smooth-transition mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                <span class="text-sm font-medium">Retour</span>
            </a>
            <h1 class="text-3xl font-semibold text-gray-900 text-center">
                Finaliser la commande
            </h1>
            <div class="w-20 h-1 bg-primary-500 rounded-full mx-auto mt-3"></div>
        </header>

        <!-- Step 1: Service Type Selection -->
        <div id="step-service-type" class="space-y-4 fade-in">
            <h2 class="text-xl font-medium text-gray-700 text-center mb-6">
                Comment souhaitez-vous être servi ?
            </h2>

            <button onclick="selectServiceType('livraison')"
                class="w-full bg-white rounded-2xl p-6 border-2 border-gray-200 hover:border-primary-500 hover:shadow-md smooth-transition text-left group">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 md:w-14 md:h-14 bg-gray-100 rounded-full flex items-center justify-center group-hover:bg-primary-50 smooth-transition">
                        <!-- Icon Delivery -->
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-gray-700 group-hover:text-primary-500 smooth-transition"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Livraison</h3>
                        <p class="text-sm text-gray-600">Recevez votre commande chez vous</p>
                    </div>
                </div>
            </button>

            <button onclick="selectServiceType('emporter')"
                class="w-full bg-white rounded-2xl p-6 border-2 border-gray-200 hover:border-primary-500 hover:shadow-md smooth-transition text-left group">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 md:w-14 md:h-14 bg-gray-100 rounded-full flex items-center justify-center group-hover:bg-primary-50 smooth-transition">
                        <!-- Icon Takeaway -->
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-gray-700 group-hover:text-primary-500 smooth-transition"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">À Emporter</h3>
                        <p class="text-sm text-gray-600">Venez récupérer votre commande</p>
                    </div>
                </div>
            </button>
        </div>

        <!-- Step 2: Details Form -->
        <div id="step-details" class="hidden fade-in">
            <form id="checkout-form" onsubmit="handleSubmit(event)" class="space-y-6">

                <!-- Livraison Form -->
                <div id="form-livraison" class="hidden bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations de Livraison</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="livraison-name"
                                name="name"
                                autocomplete="name"
                                minlength="2" maxlength="80"
                                placeholder="Prénom et Nom"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                            <input type="tel" id="livraison-phone"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                minlength="8" maxlength="20"
                                pattern="[\d\s\+\-\(\)]{8,20}"
                                placeholder="+225 07 XX XX XX XX"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quartier *</label>
                            <input type="text" id="livraison-neighborhood"
                                name="neighborhood"
                                autocomplete="address-level2"
                                minlength="3" maxlength="100"
                                placeholder="Ex: Cocody, Yopougon..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                    </div>
                </div>

                <!-- Emporter Form -->
                <div id="form-emporter" class="hidden bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations de Retrait</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="emporter-name"
                                name="name"
                                autocomplete="name"
                                minlength="2" maxlength="80"
                                placeholder="Prénom et Nom"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                            <input type="tel" id="emporter-phone"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                minlength="8" maxlength="20"
                                pattern="[\d\s\+\-\(\)]{8,20}"
                                placeholder="+225 07 XX XX XX XX"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent smooth-transition">
                        </div>
                    </div>
                </div>

                <!-- À Table Form -->
                <div id="form-a-table" class="hidden bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Service à Table</h3>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3" id="table-grid">
                        <!-- JS generated -->
                    </div>
                    <input type="hidden" id="selected-table">
                </div>

                <!-- Summary & Notes -->
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Notes (optionnel)</h3>
                    <textarea id="order-notes" rows="3"
                        maxlength="500"
                        placeholder="Allergies, instructions spéciales..."
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 smooth-transition resize-none"></textarea>
                    <p class="text-right text-xs text-gray-400 mt-1"><span id="notes-counter">0</span> / 500</p>
                </div>

                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Récapitulatif</h3>
                    <div id="order-summary" class="space-y-3 mb-4"></div>
                    <div class="pt-4 border-t-2 border-gray-900 flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900">Total</span>
                        <span id="order-total" class="text-2xl font-bold text-gray-900">0 F</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <button type="submit"
                        class="w-full py-4 bg-primary-500 text-white rounded-xl font-semibold hover:bg-primary-600 smooth-transition shadow-sm">Confirmer
                        la commande</button>
                    <button type="button" onclick="backToServiceType()"
                        class="w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 smooth-transition">Retour</button>
                </div>
            </form>
        </div>

        <!-- Step 3: Success -->
        <div id="step-success" class="hidden fade-in text-center">
            <div class="bg-white rounded-2xl p-8 border border-gray-200">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Commande confirmée !</h2>
                <p class="text-gray-600 mb-6">Merci pour votre commande.</p>

                <!-- Order Details Summary -->
                <div id="order-summary-success"
                    class="mb-8 text-left bg-gray-50 rounded-xl p-5 border border-gray-100 hidden">
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Résumé de votre commande
                    </h3>
                    <div id="summary-items-list" class="space-y-3 mb-4"></div>
                    <div class="pt-4 border-t border-gray-200 flex justify-between items-center font-bold">
                        <span class="text-gray-900">Total</span>
                        <span id="summary-total-amount" class="text-orange-500 text-lg"></span>
                    </div>
                </div>

                <div class="space-y-3">
                    <a id="btn-track-order" href="#"
                        class="w-full py-4 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 smooth-transition flex items-center justify-center gap-2 shadow-lg shadow-orange-200">
                        <i data-lucide="satellite" class="w-5 h-5"></i> Suivre ma commande en direct
                    </a>
                    <button onclick="downloadReceipt()"
                        class="w-full py-3.5 bg-gray-900 text-white rounded-xl font-medium hover:bg-gray-800 smooth-transition flex items-center justify-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5"></i> Télécharger le reçu
                    </button>
                    <button onclick="window.location.href='index.php'"
                        class="w-full py-3.5 bg-primary-500 text-white rounded-xl font-medium hover:bg-primary-600 smooth-transition">
                        Nouvelle commande
                    </button>
                </div>
            </div>

        </div>

        <!-- Confirmation Modal -->
        <div id="confirmmodal"
            class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden fade-in shadow-2xl border border-white/20">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-xl font-bold text-gray-800 text-left">Confirmer la commande ?</h2>
                </div>
                <div class="p-6">
                    <p id="confirmMessage" class="text-gray-600 leading-relaxed font-medium text-base text-left"></p>
                </div>
                <div class="p-6 bg-gray-50 flex gap-3">
                    <button onclick="closeConfirmModal()"
                        class="flex-1 py-3 bg-white border border-gray-200 rounded-xl font-bold text-gray-700 hover:bg-gray-100 transition shadow-sm">Annuler</button>
                    <button onclick="processOrder()"
                        class="flex-1 py-3 bg-primary-500 text-white rounded-xl font-bold hover:bg-primary-600 transition shadow-sm">Confirmer</button>
                </div>
            </div>
        </div>

        <script>
            let currentServiceType = '';
            let orderData = {};

            document.addEventListener('DOMContentLoaded', () => {
                const cart = JSON.parse(localStorage.getItem('restaurantCart'));
                if (!cart || cart.length === 0) {
                    alert('Panier vide !');
                    window.location.href = 'index.php';
                    return;
                }
                generateTableOptions();
                displayOrderSummary(cart);
            });

            function generateTableOptions() {
                const grid = document.getElementById('table-grid');
                for (let i = 1; i <= 12; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'p-4 border-2 border-gray-200 rounded-lg font-semibold text-gray-700 hover:bg-primary-50';
                    btn.textContent = i;
                    btn.onclick = () => {
                        document.querySelectorAll('#table-grid button').forEach(b => {
                            b.classList.remove('bg-primary-500', 'text-white', 'border-primary-500');
                            b.classList.add('border-gray-200', 'text-gray-700');
                        });
                        btn.classList.remove('border-gray-200', 'text-gray-700', 'hover:bg-primary-50');
                        btn.classList.add('bg-primary-500', 'text-white', 'border-primary-500');
                        document.getElementById('selected-table').value = i;
                    };
                    grid.appendChild(btn);
                }
            }

            function selectServiceType(type) {
                currentServiceType = type;
                document.getElementById('step-service-type').classList.add('hidden');
                document.getElementById('step-details').classList.remove('hidden');

                document.getElementById('form-livraison').classList.add('hidden');
                document.getElementById('form-emporter').classList.add('hidden');
                document.getElementById('form-a-table').classList.add('hidden');

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
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <div>
                            <span class="font-semibold">${item.quantity}x ${item.name}</span>
                            <div class="text-sm text-gray-500">
                                ${item.selectedOptions.map(o => o.name).join(', ')}
                            </div>
                        </div>
                        <span class="font-bold">${itemTotal.toLocaleString('fr-FR')} F</span>
                    </div>
                `;
                }).join('');
                document.getElementById('order-total').textContent = `${total.toLocaleString('fr-FR')} F`;
                orderData.total = total;
                orderData.items = cart;
            }

            function openConfirmModal() {
                const msg = currentServiceType === 'livraison'
                    ? "Le livreur va vous appeler pour confirmer la commande et le prix de la livraison."
                    : "Le restaurant vous appellera pour confirmer la commande.";
                document.getElementById('confirmMessage').textContent = msg;
                document.getElementById('confirmmodal').classList.remove('hidden');
            }

            function closeConfirmModal() {
                document.getElementById('confirmmodal').classList.add('hidden');
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
                el.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                el.classList.remove('border-gray-300');
                let errEl = document.getElementById(id + '-err');
                if (!errEl) {
                    errEl = document.createElement('p');
                    errEl.id = id + '-err';
                    errEl.className = 'text-xs text-red-500 mt-1';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = msg;
            }

            function clearFieldError(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                el.classList.add('border-gray-300');
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

            // Attach live validation on blur for each field
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
                } else {
                    orderData.table = document.getElementById('selected-table').value;
                    if (!orderData.table) {
                        alert('Veuillez sélectionner une table.');
                        valid = false;
                    }
                }

                if (!valid) return;
                openConfirmModal();
            }

            async function processOrder() {
                closeConfirmModal();

                const notesRaw = (document.getElementById('order-notes').value || '').trim().slice(0, NOTES_MAX);
                orderData.serviceType = currentServiceType;
                orderData.notes       = sanitize(notesRaw);

                // Disable confirm button to prevent double submission
                const confirmBtn = document.querySelector('#confirmmodal button[onclick="processOrder()"]');
                if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Envoi…'; }

                try {
                    const response = await fetch('backend/save_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(orderData)
                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const result = await response.json();

                    if (result.success) {
                        orderData.orderId = result.order_id;
                        localStorage.setItem('lastOrder', JSON.stringify(orderData));
                        localStorage.removeItem('restaurantCart');

                        await saveOrderToIndexedDB(result.order_id, orderData.total);

                        document.getElementById('step-details').classList.add('hidden');
                        document.getElementById('step-success').classList.remove('hidden');

                        const trackBtn = document.getElementById('btn-track-order');
                        if (trackBtn) trackBtn.href = `track.php?id=${result.order_id}`;

                        const summaryDiv  = document.getElementById('order-summary-success');
                        const summaryList = document.getElementById('summary-items-list');
                        const summaryTot  = document.getElementById('summary-total-amount');
                        if (summaryDiv && summaryList && summaryTot) {
                            summaryList.innerHTML = orderData.items.map(item => `
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700">${item.quantity}x ${item.name}</span>
                                    <span class="font-bold text-gray-900">${formatAmount(item.itemPrice * item.quantity)}</span>
                                </div>
                            `).join('');
                            summaryTot.textContent = formatAmount(orderData.total);
                            summaryDiv.classList.remove('hidden');
                        }
                    } else {
                        alert('Erreur: ' + (result.message || 'Impossible de passer la commande.'));
                        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirmer'; }
                    }
                } catch (err) {
                    console.error(err);
                    alert('Une erreur est survenue. Veuillez réessayer.');
                    if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirmer'; }
                }
            }

            function formatAmount(amt) {
                return amt.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' F';
            }

            function downloadReceipt() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Header: Navy Blue Bar
                doc.setFillColor(15, 23, 42);
                doc.rect(0, 0, 210, 40, 'F');

                doc.setTextColor(255, 255, 255);
                doc.setFontSize(26);
                doc.setFont('helvetica', 'bold');
                doc.text('OPERA RESTO', 105, 22, { align: 'center' });

                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text('REÇU DE COMMANDE - MERCI POUR VOTRE COMMANDE', 105, 32, { align: 'center' });

                // Content
                doc.setTextColor(40, 40, 40);
                doc.setFontSize(12);
                doc.setFont('helvetica', 'bold');
                doc.text('DÉTAILS DE LA COMMANDE', 20, 55);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                const date = new Date().toLocaleString('fr-FR');
                doc.text(`Date : ${date}`, 20, 65);
                doc.text(`Mode : ${currentServiceType.toUpperCase()}`, 20, 71);
                if (orderData.name) doc.text(`Client : ${orderData.name}`, 20, 77);
                if (orderData.phone) doc.text(`Tél : ${orderData.phone}`, 20, 83);
                if (orderData.neighborhood) doc.text(`Quartier : ${orderData.neighborhood}`, 20, 89);

                // Table
                let y = 105;
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
                doc.text('TOTAL GÉNÉRAL', 120, y);
                doc.setTextColor(234, 88, 12); // Primary color
                doc.text(formatAmount(orderData.total), 170, y);

                doc.save(`recu-opera-${Date.now()}.pdf`);
            }
        </script>
        <script>lucide.createIcons();</script>
</body>

</html>