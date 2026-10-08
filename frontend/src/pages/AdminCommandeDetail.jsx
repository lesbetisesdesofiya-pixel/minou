import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api, fmt } from '../api';
import { TopBar, AdminNav, StatusBadge } from '../components/layout';

// Détail commande admin (ouvert depuis l'app Android après "Accepter").
// 100% API réelle : GET /orders/{id}, PATCH /orders/{id}/status,
// POST /delivery/assign, POST /orders/{id}/driver-paid
export default function AdminCommandeDetail() {
  const { id } = useParams();
  const [order, setOrder] = useState(null);
  const [drivers, setDrivers] = useState([]);
  const [driverSel, setDriverSel] = useState('');
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');

  const load = async () => {
    try {
      const [{ data: o }, persons] = await Promise.all([
        api.get(`/orders/${id}`),
        api.get('/delivery/persons').catch(() => ({ data: [] })),
      ]);
      setOrder(o);
      setDrivers(Array.isArray(persons.data) ? persons.data : []);
      setDriverSel(o.assigned_driver_id ? String(o.assigned_driver_id) : '');
      setErr('');
    } catch (e) {
      setErr(e?.response?.data?.error || 'Commande introuvable.');
    }
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  const act = async (fn, okMsg) => {
    setMsg('');
    try {
      await fn();
      setMsg(okMsg);
      await load();
    } catch (e) {
      setErr(e?.response?.data?.error || e?.response?.data?.message || 'Action impossible.');
    }
  };

  const setStatus = (status) => act(
    async () => {
      try {
        await api.patch(`/orders/${id}/status`, { status });
      } catch (e) {
        if (status === 'Annulée') await api.patch(`/orders/${id}/status`, { status: 'pending' });
        else throw e;
      }
    },
    `Commande #${id} mise à jour.`
  );

  const assign = () => {
    if (!driverSel) return;
    act(() => api.post('/delivery/assign', { order_id: Number(id), driver_id: Number(driverSel) }), 'Livreur assigné.');
  };

  const togglePaid = () => act(
    () => api.post(`/orders/${id}/driver-paid`, { driver_paid: !order.driver_paid }),
    'Paie livreur mise à jour.'
  );

  if (err && !order) {
    return (
      <div className="shell">
        <TopBar title={`Commande #${id}`} />
        <div className="page"><div className="err">{err}</div><Link to="/admin/commandes">← Retour</Link></div>
        <AdminNav />
      </div>
    );
  }
  if (!order) {
    return (
      <div className="shell">
        <TopBar title={`Commande #${id}`} />
        <div className="page"><div className="muted">Chargement…</div></div>
        <AdminNav />
      </div>
    );
  }

  const items = order.items || [];
  const total = order.total ?? order.total_amount ?? 0;

  return (
    <div className="shell">
      <TopBar title={`Commande #OP-${order.id}`} subtitle={order.created_at ? String(order.created_at).slice(0, 16).replace('T', ' ') : ''} right={<StatusBadge status={order.status} />} />
      <div className="page">
        {err && <div className="err">{err}</div>}
        {msg && <div className="ok">{msg}</div>}
        <Link to="/admin/commandes" className="muted">← Toutes les commandes</Link>

        <div className="hero">
          <div className="big">{fmt(total)}</div>
          <div className="muted" style={{ color: '#cbd5e1' }}>
            {order.service_type} • Sous-total {fmt(order.subtotal ?? order.subtotal_amount)} •
            Service {fmt(order.service_fee)} • Livraison {fmt(order.delivery_fee)}
          </div>
        </div>

        <div className="card">
          <b>👤 Client</b>
          <div className="muted" style={{ marginTop: 6 }}>
            {order.client_name} • {order.client_phone}<br />
            📍 {order.neighborhood || '—'}{order.table_number ? ` • Table ${order.table_number}` : ''}
          </div>
          <div className="row" style={{ marginTop: 8 }}>
            <a className="btn btn-ghost btn-sm" href={`tel:${order.client_phone}`}>📞 Appeler</a>
            {(order.driver_first || order.driver) && (
              <span className="muted">🛵 {order.driver_first || order.driver?.first_name} {order.driver_last || order.driver?.last_name || ''}</span>
            )}
          </div>
        </div>

        <div className="card">
          <b>🧾 Articles ({items.length})</b>
          <table className="tbl" style={{ marginTop: 8 }}>
            <thead><tr><th>Plat</th><th>Qté</th><th>Prix</th></tr></thead>
            <tbody>
              {items.map((it, i) => (
                <tr key={i}>
                  <td>{it.product_name || it.name}{it.options ? <div className="muted">{it.options}</div> : null}</td>
                  <td>×{it.quantity}</td>
                  <td>{fmt(it.price)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {order.notes && <div className="muted" style={{ marginTop: 8 }}>📝 Note : {order.notes}</div>}
        </div>

        <div className="card">
          <b>⚡ Actions</b>
          <div className="row" style={{ marginTop: 8 }}>
            <button className="btn btn-orange btn-sm" onClick={() => setStatus('PREPARING')}>Accepter & Cuisine</button>
            <button className="btn btn-ghost btn-sm" onClick={() => setStatus('Annulée')}>Refuser</button>
          </div>
          <div className="row" style={{ marginTop: 8 }}>
            <button className="btn btn-navy btn-sm" onClick={() => setStatus('READY_FOR_PICKUP')}>Prête → livraison</button>
            <button className="btn btn-navy btn-sm" onClick={() => setStatus('delivered')}>Marquer livrée</button>
          </div>
        </div>

        <div className="card">
          <b>🛵 Livreur</b>
          <div className="row" style={{ marginTop: 8 }}>
            <select className="select" style={{ height: 38 }} value={driverSel} onChange={(e) => setDriverSel(e.target.value)}>
              <option value="">— Choisir —</option>
              {drivers.filter((d) => d.active && !d.suspended).map((d) => (
                <option key={d.id} value={d.id}>{d.first_name} {d.last_name}</option>
              ))}
            </select>
            <button className="btn btn-ghost btn-sm" onClick={assign}>Assigner</button>
          </div>
          <div className="row" style={{ marginTop: 8 }}>
            <span className="muted">Gain livreur : {fmt(order.driver_amount)} • {order.driver_paid ? '✅ Reversé' : '⏳ Non reversé'}</span>
            <button className="btn btn-ghost btn-sm" onClick={togglePaid}>
              {order.driver_paid ? 'Marquer non reversé' : 'Marquer reversé'}
            </button>
          </div>
        </div>
      </div>
      <AdminNav />
    </div>
  );
}
