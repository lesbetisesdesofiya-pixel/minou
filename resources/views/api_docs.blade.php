<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Opera Resto — Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; }
        code.inline { background: #f1f5f9; padding: 1px 6px; border-radius: 6px; font-size: 12px; }
        pre { background: #0f172a; color: #e2e8f0; border-radius: 12px; padding: 14px 16px; font-size: 12px; overflow-x: auto; }
        .badge { font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 999px; letter-spacing: .5px; }
        .m-get { background: #dcfce7; color: #15803d; }
        .m-post { background: #dbeafe; color: #1d4ed8; }
        .m-patch { background: #fef3c7; color: #b45309; }
        .m-delete { background: #fee2e2; color: #b91c1c; }
        .a-public { background: #f1f5f9; color: #475569; }
        .a-jwt { background: #ede9fe; color: #6d28d9; }
        details summary { cursor: pointer; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-orange-400 via-orange-500 to-red-500"></div>

    <div class="max-w-5xl mx-auto px-4 py-10">
        <header class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-slate-900">API Opera Resto</h1>
            <p class="text-slate-500 mt-2">L'app fonctionne intégralement via ces endpoints · <span class="font-bold text-slate-700">v1.0</span></p>
            <div class="mt-4 inline-flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm shadow-sm">
                <i data-lucide="server" class="w-4 h-4 text-orange-500"></i>
                <code class="font-bold text-slate-800">{{ url('/api') }}</code>
            </div>
        </header>

        <!-- Auth -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold flex items-center gap-2 mb-3"><i data-lucide="key-round" class="w-5 h-5 text-violet-600"></i> Authentification (JWT)</h2>
            <p class="text-sm text-slate-600 mb-3">Endpoints <span class="badge a-public">PUBLIC</span> : aucun token. Endpoints <span class="badge a-jwt">JWT</span> : header <code class="inline">Authorization: Bearer &lt;token&gt;</code>. Token obtenu via <code class="inline">POST /api/auth/login</code> <code class="inline">{"email","password"}</code> → <code class="inline">{"token","role","user_id"}</code> (valide 7 jours). Erreurs : <code class="inline">401 {"error":"Token manquant ou invalide"}</code>.</p>
            <p class="text-sm text-slate-600">Prix : <code class="inline">total = sous-total + service (10% du sous-total, hors livraison) + livraison (0 emporter · 1000 F défaut · tarif quartier sinon)</code>. Statuts : <code class="inline">En attente de paiement · Payée · Annulée · PREPARING · READY_FOR_PICKUP · delivered · pending</code>.</p>
        </section>

        <!-- Client -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold flex items-center gap-2 mb-4"><i data-lucide="shopping-bag" class="w-5 h-5 text-orange-500"></i> Client — catalogue, devis, commande, suivi</h2>
            <div class="space-y-3">
                @php
                $client = [
                    ['GET','/api/menu','PUBLIC','Catalogue : produits, attributs, frais, zones de livraison.', null],
                    ['GET','/api/delivery/zones','PUBLIC','Tarif livraison : <code class="inline">{default_fee, zones:[{id,quartier,fee}]}</code>.', null],
                    ['POST','/api/quote','PUBLIC','Devis sans créer de commande.', '<code class="inline">{items:[{itemPrice,quantity}], service_type, neighborhood?}</code> → <code class="inline">{subtotal, service_fee, delivery_fee, total}</code>'],
                    ['POST','/api/orders','PUBLIC','Créer commande → <code class="inline">201 {order_id, subtotal, service_fee, delivery_fee, total, status}</code>.', '<code class="inline">{serviceType, name, phone, neighborhood?, items:[{id?,name,quantity,itemPrice,selectedOptions?}]}</code>'],
                    ['POST','/api/orders/{id}/moneyfusion/initiate','PUBLIC','Initier paiement → <code class="inline">{payment_url, token}</code>. Ouvrir <code class="inline">payment_url</code> au client.', '<code class="inline">{numeroSend, nomclient}</code>'],
                    ['GET','/api/orders/{id}/moneyfusion/status','PUBLIC','Poller toutes les 5 s → <code class="inline">{paid, status: paid|pending|failure}</code>. <code class="inline">paid</code> = commande <code class="inline">Payée</code>.', null],
                    ['POST','/api/orders/{id}/cancel','PUBLIC','Annuler (stocks restitués).', null],
                    ['GET','/api/orders/{id}/tracking','PUBLIC','Suivi public : statut, client, montants, livreur, articles.', null],
                ];
                @endphp
                @foreach ($client as [$m,$p,$a,$d,$ex])
                <div class="border border-slate-100 rounded-xl p-4 hover:border-orange-200 transition">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="badge m-{{ strtolower($m) }}">{{ $m }}</span>
                        <code class="text-sm font-bold text-slate-800">{{ $p }}</code>
                        <span class="badge a-{{ $a === 'PUBLIC' ? 'public' : 'jwt' }}">{{ $a }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{!! $d !!}</p>
                    @if($ex)<p class="text-xs text-slate-500 mt-1">Body : {!! $ex !!}</p>@endif
                </div>
                @endforeach
            </div>
        </section>

        <!-- Admin -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold flex items-center gap-2 mb-4"><i data-lucide="shield-check" class="w-5 h-5 text-violet-600"></i> Administration <span class="badge a-jwt">JWT</span></h2>
            <div class="space-y-3">
                @php
                $admin = [
                    ['POST','/api/auth/login','Login admin → <code class="inline">{token, role, user_id}</code>.', '<code class="inline">{email, password}</code>'],
                    ['GET','/api/admin/stats','Chiffres dashboard : commandes, CA, frais service/livraison, en attente, payées, livrées + 10 récentes.', null],
                    ['GET','/api/orders','Lister (filtres <code class="inline">?status=&date=&limit=</code>).', null],
                    ['GET','/api/orders/{id}','Détail commande + articles + livreur.', null],
                    ['PATCH','/api/orders/{id}/status','Changer statut.', '<code class="inline">{status: PREPARING|READY_FOR_PICKUP|delivered|pending}</code>'],
                    ['GET','/api/delivery/ready','Commandes prêtes à retirer.', null],
                    ['POST','/api/delivery/assign','Affecter livreur.', '<code class="inline">{order_id, driver_id}</code>'],
                    ['PATCH','/api/delivery/{id}/complete','Marquer livrée.', null],
                    ['GET','/api/delivery/persons','Lister livreurs.', null],
                    ['POST','/api/delivery/persons','Créer livreur → 201.', '<code class="inline">{first_name, last_name, email, phone?}</code>'],
                    ['PATCH','/api/delivery/persons/{id}','Modifier livreur.', '<code class="inline">{first_name?, last_name?, phone?, active?, suspended?}</code>'],
                    ['POST','/api/delivery/zones','Citer quartier à tarif spécial → 201.', '<code class="inline">{quartier, fee}</code>'],
                    ['DELETE','/api/delivery/zones/{id}','Retirer quartier (retour forfait).', null],
                    ['GET','/api/admin/menu','Tous les plats (actifs + inactifs) pour gestion.', null],
                    ['PATCH','/api/dishes/{id}','Activer/désactiver un plat.', '<code class="inline">{active: true|false}</code>'],
                    ['GET','/api/inventory/fish','Stock poissons.', null],
                    ['PATCH','/api/inventory/fish/{id}','Ajuster stock.', '<code class="inline">{stock_kg}</code>'],
                    ['GET','/api/analytics/revenue','CA par période.', '<code class="inline">?period=daily|weekly|monthly</code>'],
                    ['GET','/api/analytics/deliveries/metrics','Métriques livraisons + par livreur.', null],
                ];
                @endphp
                @foreach ($admin as [$m,$p,$d,$ex])
                <div class="border border-slate-100 rounded-xl p-4 hover:border-violet-200 transition">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="badge m-{{ strtolower($m) }}">{{ $m }}</span>
                        <code class="text-sm font-bold text-slate-800">{{ $p }}</code>
                    </div>
                    <p class="text-sm text-slate-600">{!! $d !!}</p>
                    @if($ex)<p class="text-xs text-slate-500 mt-1">Body : {!! $ex !!}</p>@endif
                </div>
                @endforeach
            </div>
        </section>

        <!-- Webhook -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold flex items-center gap-2 mb-3"><i data-lucide="webhook" class="w-5 h-5 text-blue-600"></i> Webhook MoneyFusion</h2>
            <div class="border border-slate-100 rounded-xl p-4">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="badge m-post">POST</span>
                    <code class="text-sm font-bold text-slate-800">/api/payment/webhook</code>
                    <span class="badge a-public">PUBLIC</span>
                </div>
                <p class="text-sm text-slate-600">Appelé par MoneyFusion (serveur-à-serveur). Évents : <code class="inline">payin.session.pending|completed|cancelled</code>. Idempotent via <code class="inline">tokenPay</code> (doublons ignorés). <code class="inline">completed</code> → commande <code class="inline">Payée</code>. À déclarer dans le dashboard MoneyFusion.</p>
            </div>
            <details class="mt-3 border border-slate-100 rounded-xl p-4">
                <summary class="text-sm font-bold text-slate-700">Exemple d'appel (cURL)</summary>
<pre class="mt-2"># 1. Login
curl -X POST {{ url('/api/auth/login') }} -H "Content-Type: application/json" \
  -d '{"email":"admin@opera.tg","password":"***"}'

# 2. Devis
curl -X POST {{ url('/api/quote') }} -H "Content-Type: application/json" \
  -d '{"items":[{"itemPrice":5000,"quantity":2}],"service_type":"livraison","neighborhood":"Agoè"}'

# 3. Suivi
curl {{ url('/api/orders/1/tracking') }}</pre>
            </details>
        </section>

        <p class="text-center text-xs text-slate-400">Opera Resto API v1.0 · JSON · Fuseau : Afrique/Lomé</p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
