import { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { useAuth } from '../auth';
import { TopBar, LivreurNav, StatusBadge } from '../components/layout';

// Design: op_ra_livreur_gestion_des_commandes
// 100% API réelle : GET /orders (filtrées assigned_driver_id), GET /delivery/ready, PATCH /delivery/{id}/complete, POST /delivery/assign (auto-accept)
export default function LivreurCommandes() {
  const { driverId } = useAuth();
  const [tab, setTab] = useState('new'); // new | done
  const [orders, setOrders] = useState([]);
  const [ready, setReady] = useState([]);
  const [err, setErr] = useState('');

  const load = async () => {
    try {
      const [{ data: list }, readyRes] = await Promise.all([
        api.get('/orders', { params: { limit: 100 } }),
        api.get('/delivery/ready').catch(() => ({ data: [] })),
      ]);
      setOrders(Array.isArray(list) ? list : []);
      setReady(Array.isArray(readyRes.data) ? readyRes.data : []);
      setErr('');
    } catch (e) {
      setErr(e?.response?.data?.error || 'Chargement impossible.');
    }
  };
  useEffect(() => {
    load();
    const t = setInterval(load, 20000);
    return () => clearInterval(t);
  }, []);

  const mine = orders.filter((o) => driverId && Number(o.assigned_driver_id) === Number(driverId));
  const todo = mine.filter((o) => ['PREPARING', 'READY_FOR_PICKUP'].includes(o.status));
  const done = mine.filter((o) => o.status === 'delivered');
  const deliveredToday = done.length;
  const cashToday = done.reduce((s, o) => s + Number(o.driver_amount || 0), 0);

  const accept = async (orderId) => {
    if (!driverId) return;
    await api.post('/delivery/assign', { order_id: orderId, driver_id: driverId });
    load();
  };

  const complete = async (id) => {
    await api.patch(`/delivery/${id}/complete`);
    load();
  };

  return (
    <div className="shell">
      <TopBar title="Mes courses" subtitle={driverId ? `Coursier #${driverId} • En service` : 'En service'} right={<span className="badge b-green"><span className="pulse" /> En service</span>} />
      <div className="page">
        {err && <div className="err">{err}</div>}
        {!driverId && <div className="err">Compte livreur non lié (delivery_person_id manquant). Contacte la régie.</div>}
        <div className="stats">
          <div className="stat"><b>{deliveredToday}</b><span>Livrées</span></div>
          <div className="stat"><b>{fmt(cashToday)}</b><span>Gains</span></div>
        </div>
        <div className="chips">
          <button className={'chip' + (tab === 'new' ? ' active' : '')} onClick={() => setTab('new')}>Nouveau ({todo.length + ready.length})</button>
          <button className={'chip' + (tab === 'done' ? ' active' : '')} onClick={() => setTab('done')}>Livré ({done.length})</button>
        </div>
        {tab === 'new' && (
          <>
            {todo.map((o) => (
              <div className="card" key={o.id} style={{ borderLeft: '4px solid #fd761a' }}>
                <div className="row"><b>#OP-{o.id} — En route</b><StatusBadge status={o.status} /></div>
                <div className="muted" style={{ margin: '6px 0' }}>📍 {o.neighborhood} • {o.client_name} • {o.client_phone}</div>
                <div className="row"><span className="muted">💵 À encaisser : {fmt(o.driver_amount)}</span></div>
                <div className="row" style={{ marginTop: 8 }}>
                  <a className="btn btn-ghost btn-sm" href={`tel:${o.client_phone}`}>📞 Appeler</a>
                  <button className="btn btn-orange btn-sm" onClick={() => complete(o.id)}>Confirmer la livraison</button>
                </div>
              </div>
            ))}
            <b style={{ fontSize: 13 }}>Disponibles au retrait ({ready.length})</b>
            {ready.filter((o) => !o.assigned_driver_id).map((o) => (
              <div className="card" key={o.id}>
                <div className="row"><b>#OP-{o.id} — Prête en cuisine</b><span className="badge b-amber">+{fmt(o.driver_amount)}</span></div>
                <div className="muted">{o.neighborhood} • {o.client_name}</div>
                <div className="row" style={{ marginTop: 8 }}>
                  <button className="btn btn-ghost btn-sm" onClick={load}>Refuser</button>
                  <button className="btn btn-orange btn-sm" onClick={() => accept(o.id)}>Accepter la course</button>
                </div>
              </div>
            ))}
            {todo.length === 0 && ready.length === 0 && <div className="card muted">Aucune course pour le moment. Reste en ligne 🛵</div>}
          </>
        )}
        {tab === 'done' && (
          done.length === 0 ? <div className="card muted">Aucune livraison terminée.</div> :
          done.map((o) => (
            <div className="card" key={o.id}>
              <div className="row"><b>#OP-{o.id}</b><StatusBadge status={o.status} /></div>
              <div className="muted">{o.neighborhood} • {fmt(o.driver_amount)} • {o.driver_paid ? '✅ Payé' : '⏳ Non reversé'}</div>
            </div>
          ))
        )}
      </div>
      <LivreurNav />
    </div>
  );
}
