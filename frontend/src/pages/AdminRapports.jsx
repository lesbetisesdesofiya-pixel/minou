import { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { TopBar, AdminNav } from '../components/layout';

// Design: op_ra_admin_rapports_financiers
// 100% API réelle : GET /admin/stats, GET /analytics/revenue?period=, GET /analytics/deliveries/metrics
export default function AdminRapports() {
  const [period, setPeriod] = useState('daily');
  const [stats, setStats] = useState(null);
  const [rev, setRev] = useState(null);
  const [metrics, setMetrics] = useState(null);
  const [err, setErr] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const [s, r, m] = await Promise.all([
          api.get('/admin/stats'),
          api.get('/analytics/revenue', { params: { period } }),
          api.get('/analytics/deliveries/metrics').catch(() => ({ data: null })),
        ]);
        setStats(s.data);
        setRev(r.data);
        setMetrics(m.data);
      } catch (e) {
        setErr(e?.response?.data?.error || 'Chargement impossible.');
      }
    })();
  }, [period]);

  const maxRev = Math.max(1, ...(rev?.data || []).map((d) => Number(d.total_revenue || 0)));

  return (
    <div className="shell">
      <TopBar title="Rapports financiers" subtitle="Activité & clôtures" />
      <div className="page">
        {err && <div className="err">{err}</div>}
        <div className="chips">
          {[['daily', "Aujourd'hui"], ['weekly', '7 jours'], ['monthly', 'Ce mois']].map(([k, l]) => (
            <button key={k} className={'chip' + (period === k ? ' active' : '')} onClick={() => setPeriod(k)}>{l}</button>
          ))}
        </div>
        {stats && (
          <>
            <div className="hero">
              <div className="muted" style={{ color: '#cbd5e1' }}>Chiffre d'affaires total</div>
              <div className="big">{fmt(stats.total_revenue)}</div>
              <div className="row" style={{ marginTop: 8 }}>
                <span className="badge b-green">{stats.delivered_orders} livrées</span>
                <span className="badge b-amber">{stats.pending_orders + stats.awaiting_payment} en attente</span>
              </div>
            </div>
            <div className="stats">
              <div className="stat"><b>{stats.total_orders}</b><span>Commandes</span></div>
              <div className="stat"><b>{fmt(stats.service_fees)}</b><span>Frais service</span></div>
              <div className="stat"><b>{fmt(stats.delivery_fees)}</b><span>Frais livraison</span></div>
            </div>
          </>
        )}
        <div className="card">
          <b>Revenus — {rev?.period}</b>
          <div className="hist" style={{ marginTop: 10 }}>
            {(rev?.data || []).slice(0, 12).reverse().map((d, i) => (
              <div key={i} title={`${d.total_revenue}`} style={{ height: `${Math.round((Number(d.total_revenue || 0) / maxRev) * 100)}%` }} />
            ))}
          </div>
          <div className="muted" style={{ marginTop: 6 }}>Total période : {fmt(rev?.totals?.total_revenue)} • {rev?.totals?.total_orders} commandes</div>
        </div>
        {metrics && (
          <div className="card">
            <b>Livraisons</b>
            <div className="muted">{metrics.completed}/{metrics.total_deliveries} terminées • {metrics.waiting_pickup} en attente de retrait • {metrics.in_progress} en cours</div>
          </div>
        )}
        <div className="card">
          <b>Commandes récentes</b>
          <table className="tbl" style={{ marginTop: 8 }}>
            <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Statut</th></tr></thead>
            <tbody>
              {(stats?.recent_orders || []).map((o) => (
                <tr key={o.id}><td>#{o.id}</td><td>{o.client_name}</td><td>{Number(o.total_amount).toLocaleString('fr-FR')} F</td><td>{o.status}</td></tr>
              ))}
            </tbody>
          </table>
        </div>
        <button className="btn btn-navy" onClick={() => window.print()}>🧾 Clôture de caisse (imprimer cette page)</button>
      </div>
      <AdminNav />
    </div>
  );
}
