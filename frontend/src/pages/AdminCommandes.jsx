import { useCallback, useEffect, useRef, useState } from 'react';
import { api, fmt } from '../api';
import { TopBar, AdminNav, StatusBadge, NewOrderAlert } from '../components/layout';

// Design: op_ra_admin_gestion_des_commandes + op_ra_alerte_nouvelle_commande_mobile
// 100% API réelle : GET /orders, PATCH /orders/{id}/status, POST /delivery/assign, GET /delivery/persons
const TABS = [
  { key: 'new', label: 'Nouvelles', statuses: ['pending', 'En attente de paiement', 'Payée'] },
  { key: 'kitchen', label: 'En cuisine', statuses: ['PREPARING'] },
  { key: 'delivery', label: 'En livraison', statuses: ['READY_FOR_PICKUP'] },
  { key: 'done', label: 'Terminées', statuses: ['delivered'] },
];

export default function AdminCommandes() {
  const [tab, setTab] = useState('new');
  const [orders, setOrders] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState('');
  const [alertOrder, setAlertOrder] = useState(null);
  const [assignSel, setAssignSel] = useState({});
  const seenRef = useRef(new Set());
  const firstRef = useRef(true);

  const load = useCallback(async () => {
    try {
      const [{ data: list }, { data: persons }] = await Promise.all([
        api.get('/orders', { params: { limit: 100 } }),
        api.get('/delivery/persons').catch(() => ({ data: [] })),
      ]);
      const arr = Array.isArray(list) ? list : [];
      // Alerte sur toute nouvelle commande "pending" jamais vue (hors 1er chargement)
      if (!firstRef.current) {
        const fresh = arr.find((o) => o.status === 'pending' && !seenRef.current.has(o.id));
        if (fresh) setAlertOrder(fresh);
      }
      firstRef.current = false;
      arr.forEach((o) => seenRef.current.add(o.id));
      setOrders(arr);
      setDrivers(Array.isArray(persons) ? persons : []);
      setErr('');
    } catch (e) {
      setErr(e?.response?.data?.error || 'Impossible de charger les commandes.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    const t = setInterval(load, 15000); // temps réel : polling 15s
    return () => clearInterval(t);
  }, [load]);

  const setStatus = async (id, status) => {
    await api.patch(`/orders/${id}/status`, { status });
    load();
    setAlertOrder(null);
  };

  const assign = async (orderId) => {
    const driver_id = assignSel[orderId];
    if (!driver_id) return;
    await api.post('/delivery/assign', { order_id: orderId, driver_id: Number(driver_id) });
    load();
  };

  const filtered = orders.filter((o) =>
    TABS.find((t) => t.key === tab).statuses.includes(o.status)
  );
  const counts = Object.fromEntries(
    TABS.map((t) => [t.key, orders.filter((o) => t.statuses.includes(o.status)).length])
  );

  return (
    <div className="shell">
      <TopBar title="Gestion des commandes" subtitle="Service en direct" right={<span className="badge b-green"><span className="pulse" /> En direct</span>} />
      <div className="page">
        <div className="hero row">
          <div>
            <div className="big">{counts.new} nouvelles</div>
            <div className="muted" style={{ color: '#cbd5e1' }}>{orders.length} commandes au total • auto-refresh 15s</div>
          </div>
          <button className="btn btn-orange btn-sm" onClick={load}>↻ Actualiser</button>
        </div>
        {err && <div className="err">{err}</div>}
        <div className="chips">
          {TABS.map((t) => (
            <button key={t.key} className={'chip' + (tab === t.key ? ' active' : '')} onClick={() => setTab(t.key)}>
              {t.label} {counts[t.key]}
            </button>
          ))}
        </div>
        {loading ? <div className="muted">Chargement…</div> : filtered.length === 0 ? (
          <div className="card muted">Aucune commande dans cet onglet.</div>
        ) : (
          <div className="grid2">
            {filtered.map((o) => (
              <div className="card" key={o.id}>
                <div className="row">
                  <b>#OP-{o.id}</b>
                  <StatusBadge status={o.status} />
                </div>
                <div className="muted" style={{ margin: '6px 0' }}>
                  {o.client_name} • {o.client_phone}<br />{o.neighborhood || o.service_type} • {fmt(o.total_amount)}
                </div>
                {o.driver_first && <div className="muted">🛵 {o.driver_first} {o.driver_last}</div>}
                <div className="row" style={{ marginTop: 10 }}>
                  {(o.status === 'pending' || o.status === 'En attente de paiement' || o.status === 'Payée') && (
                    <>
                      <button className="btn btn-orange btn-sm" onClick={() => setStatus(o.id, 'PREPARING')}>Accepter & Cuisine</button>
                      <button className="btn btn-ghost btn-sm" onClick={() => setStatus(o.id, 'Annulée').catch(() => setStatus(o.id, 'pending'))}>Refuser</button>
                    </>
                  )}
                  {o.status === 'PREPARING' && (
                    <button className="btn btn-navy btn-sm" onClick={() => setStatus(o.id, 'READY_FOR_PICKUP')}>Prête → livraison</button>
                  )}
                  {o.status === 'READY_FOR_PICKUP' && (
                    <button className="btn btn-navy btn-sm" onClick={() => setStatus(o.id, 'delivered')}>Marquer livrée</button>
                  )}
                </div>
                {(o.status === 'Payée' || o.status === 'PREPARING' || o.status === 'READY_FOR_PICKUP') && drivers.length > 0 && (
                  <div className="row" style={{ marginTop: 8 }}>
                    <select className="select" style={{ height: 38 }} value={assignSel[o.id] || ''} onChange={(e) => setAssignSel({ ...assignSel, [o.id]: e.target.value })}>
                      <option value="">— Assigner un livreur —</option>
                      {drivers.filter((d) => d.active && !d.suspended).map((d) => (
                        <option key={d.id} value={d.id}>{d.first_name} {d.last_name}</option>
                      ))}
                    </select>
                    <button className="btn btn-ghost btn-sm" onClick={() => assign(o.id)}>Assigner</button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
      <AdminNav />
      <NewOrderAlert
        order={alertOrder}
        onClose={() => setAlertOrder(null)}
        onAccept={(o) => setStatus(o.id, 'PREPARING')}
        onRefuse={(o) => setAlertOrder(null)}
      />
    </div>
  );
}
