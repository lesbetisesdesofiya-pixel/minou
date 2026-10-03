import { useEffect, useState } from 'react';
import { api } from '../api';
import { TopBar, AdminNav } from '../components/layout';

// Design: op_ra_admin_livreurs_rapports (vue Flotte)
// 100% API réelle : GET/POST/PATCH /delivery/persons, GET /analytics/deliveries/metrics
export default function AdminLivreurs() {
  const [drivers, setDrivers] = useState([]);
  const [metrics, setMetrics] = useState(null);
  const [filter, setFilter] = useState('all');
  const [q, setQ] = useState('');
  const [err, setErr] = useState('');
  const [showAdd, setShowAdd] = useState(false);
  const [form, setForm] = useState({ first_name: '', last_name: '', email: '', phone: '' });

  const load = async () => {
    try {
      const [{ data: persons }, { data: m }] = await Promise.all([
        api.get('/delivery/persons'),
        api.get('/analytics/deliveries/metrics').catch(() => ({ data: null })),
      ]);
      setDrivers(Array.isArray(persons) ? persons : []);
      setMetrics(m);
      setErr('');
    } catch (e) {
      setErr(e?.response?.data?.error || 'Chargement impossible.');
    }
  };
  useEffect(() => { load(); }, []);

  const toggleActive = async (d) => {
    await api.patch(`/delivery/persons/${d.id}`, { active: !d.active });
    load();
  };

  const add = async (e) => {
    e.preventDefault();
    await api.post('/delivery/persons', form);
    setShowAdd(false);
    setForm({ first_name: '', last_name: '', email: '', phone: '' });
    load();
  };

  const list = drivers.filter((d) => {
    if (filter === 'active' && !d.active) return false;
    if (filter === 'paused' && (d.active || !d.suspended)) return false;
    if (filter === 'suspended' && !d.suspended) return false;
    return (d.first_name + ' ' + d.last_name + ' ' + (d.phone || '')).toLowerCase().includes(q.toLowerCase());
  });

  return (
    <div className="shell">
      <TopBar title="Livreurs & Flotte" subtitle="Gestion des coursiers" />
      <div className="page">
        {err && <div className="err">{err}</div>}
        <input className="input" placeholder="🔍 Rechercher un livreur…" value={q} onChange={(e) => setQ(e.target.value)} />
        <div className="chips">
          {[['all', 'Tous'], ['active', 'Disponibles'], ['paused', 'En pause'], ['suspended', 'Suspendus']].map(([k, l]) => (
            <button key={k} className={'chip' + (filter === k ? ' active' : '')} onClick={() => setFilter(k)}>{l}</button>
          ))}
        </div>
        {metrics && (
          <div className="stats">
            <div className="stat"><b>{metrics.total_deliveries}</b><span>Livraisons</span></div>
            <div className="stat"><b>{metrics.completed}</b><span>Terminées</span></div>
            <div className="stat"><b>{metrics.in_progress}</b><span>En cours</span></div>
          </div>
        )}
        <button className="btn btn-orange" onClick={() => setShowAdd(true)}>+ Ajouter un livreur</button>
        {list.map((d) => (
          <div className="card" key={d.id}>
            <div className="row">
              <div>
                <b>{d.first_name} {d.last_name}</b>
                <div className="muted">{d.phone} • {d.email}</div>
              </div>
              <span className={'badge ' + (d.suspended ? 'b-grey' : d.active ? 'b-green' : 'b-amber')}>
                {d.suspended ? 'Suspendu' : d.active ? 'Disponible' : 'En pause'}
              </span>
            </div>
            <div className="row" style={{ marginTop: 10 }}>
              <a className="btn btn-ghost btn-sm" href={`tel:${d.phone}`}>📞 Appeler</a>
              <button className={'toggle' + (d.active ? ' on' : '')} onClick={() => toggleActive(d)} aria-label="Actif" />
              <button
                className="btn btn-ghost btn-sm"
                onClick={() => api.patch(`/delivery/persons/${d.id}`, { suspended: !d.suspended }).then(load)}
              >{d.suspended ? 'Réactiver' : 'Suspendre'}</button>
            </div>
          </div>
        ))}
        {metrics?.per_driver && (
          <div className="card">
            <b>Performance flotte</b>
            <table className="tbl" style={{ marginTop: 8 }}>
              <thead><tr><th>Livreur</th><th>Courses</th><th>CA géré</th></tr></thead>
              <tbody>
                {metrics.per_driver.map((p) => (
                  <tr key={p.id}><td>{p.driver_name}</td><td>{p.deliveries}</td><td>{Number(p.revenue_handled || 0).toLocaleString('fr-FR')} F</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <AdminNav />
      {showAdd && (
        <div className="modal-back" onClick={() => setShowAdd(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <form onSubmit={add} style={{ padding: 18, display: 'grid', gap: 4 }}>
              <h3 style={{ margin: 0 }}>Nouveau livreur</h3>
              <label className="lbl">Prénom</label>
              <input className="input" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} required />
              <label className="lbl">Nom</label>
              <input className="input" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} required />
              <label className="lbl">Email</label>
              <input className="input" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
              <label className="lbl">Téléphone</label>
              <input className="input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+225 …" />
              <div className="row" style={{ marginTop: 12 }}>
                <button type="button" className="btn btn-ghost" onClick={() => setShowAdd(false)}>Annuler</button>
                <button className="btn btn-orange" type="submit">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
