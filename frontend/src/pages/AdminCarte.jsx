import { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { TopBar, AdminNav } from '../components/layout';

// Design: op_ra_admin_gestion_de_la_carte_plats (tab Carte) + op_ra_admin_plats_stock_poissons (tab Poissons)
// 100% API réelle : GET /admin/menu, PATCH /dishes/{id}, GET /inventory/fish, PATCH /inventory/fish/{id}, POST/DELETE /inventory/fish
export default function AdminCarte() {
  const [view, setView] = useState('plats'); // plats | poissons
  const [dishes, setDishes] = useState([]);
  const [fish, setFish] = useState([]);
  const [q, setQ] = useState('');
  const [err, setErr] = useState('');
  const [newFish, setNewFish] = useState({ dish_id: '', name: '', price: '', stock_kg: '' });
  const [showAdd, setShowAdd] = useState(false);

  const load = async () => {
    try {
      const [{ data: menu }, { data: inv }] = await Promise.all([
        api.get('/admin/menu'),
        api.get('/inventory/fish'),
      ]);
      setDishes(Array.isArray(menu) ? menu : []);
      setFish(Array.isArray(inv) ? inv : []);
      setErr('');
    } catch (e) {
      setErr(e?.response?.data?.error || 'Chargement impossible.');
    }
  };
  useEffect(() => { load(); }, []);

  const toggleDish = async (d) => {
    await api.patch(`/dishes/${d.id}`, { active: !d.active });
    load();
  };

  const setStock = async (f, delta) => {
    const stock_kg = Math.max(0, Number(f.stock_kg || 0) + delta);
    await api.patch(`/inventory/fish/${f.id}`, { stock_kg });
    load();
  };

  const addFish = async (e) => {
    e.preventDefault();
    await api.post('/inventory/fish', {
      dish_id: Number(newFish.dish_id),
      name: newFish.name,
      price: Number(newFish.price),
      stock_kg: Number(newFish.stock_kg),
    });
    setShowAdd(false);
    setNewFish({ dish_id: '', name: '', price: '', stock_kg: '' });
    load();
  };

  const filtered = dishes.filter((d) =>
    (d.nom + ' ' + d.category).toLowerCase().includes(q.toLowerCase())
  );

  return (
    <div className="shell">
      <TopBar title="Menu & Stocks" subtitle="Carte des plats • Poissons frais" right={<span className="badge b-green"><span className="pulse" /> En direct</span>} />
      <div className="page">
        {err && <div className="err">{err}</div>}
        <div className="chips">
          <button className={'chip' + (view === 'plats' ? ' active' : '')} onClick={() => setView('plats')}>Carte des plats</button>
          <button className={'chip' + (view === 'poissons' ? ' active' : '')} onClick={() => setView('poissons')}>Stock poissons frais</button>
        </div>

        {view === 'plats' ? (
          <>
            <div className="stats">
              <div className="stat"><b>{dishes.length}</b><span>Total</span></div>
              <div className="stat"><b>{dishes.filter((d) => d.active).length}</b><span>Actifs</span></div>
              <div className="stat"><b>{dishes.filter((d) => !d.active).length}</b><span>Masqués</span></div>
            </div>
            <input className="input" placeholder="🔍 Rechercher un plat…" value={q} onChange={(e) => setQ(e.target.value)} />
            {filtered.map((d) => (
              <div className="card" key={d.id}>
                <div className="row">
                  <div>
                    <b>{d.nom}</b>
                    <div className="muted">{d.category} • {fmt(d.prix)}</div>
                  </div>
                  <span className={'badge ' + (d.active ? 'b-green' : 'b-grey')}>{d.active ? 'Actif' : 'Masqué'}</span>
                </div>
                <div className="row" style={{ marginTop: 10 }}>
                  <button className="btn btn-ghost btn-sm" onClick={() => toggleDish(d)}>{d.active ? 'Désactiver' : 'Activer'}</button>
                  <button className={'toggle' + (d.active ? ' on' : '')} onClick={() => toggleDish(d)} aria-label="Disponibilité" />
                </div>
              </div>
            ))}
            <div className="card muted">🔄 Synchronisation caisse : toute activation / désactivation est immédiate sur le menu client.</div>
          </>
        ) : (
          <>
            <div className="stats">
              <div className="stat"><b>{fish.length}</b><span>Variétés</span></div>
              <div className="stat"><b>{fish.filter((f) => Number(f.stock_kg) > 0).length}</b><span>Disponibles</span></div>
              <div className="stat"><b>{fish.filter((f) => Number(f.stock_kg) <= 0).length}</b><span>Ruptures</span></div>
            </div>
            <button className="btn btn-orange" onClick={() => setShowAdd(true)}>+ Ajouter un poisson</button>
            {fish.map((f) => (
              <div className="card" key={f.id}>
                <div className="row">
                  <div>
                    <b>{f.type_poisson}</b>
                    <div className="muted">{f.plat_nom} • {fmt(f.price)}</div>
                  </div>
                  <span className={'badge ' + (Number(f.stock_kg) <= 0 ? 'b-grey' : Number(f.stock_kg) < 5 ? 'b-amber' : 'b-green')}>
                    {Number(f.stock_kg) <= 0 ? 'Épuisé' : `${f.stock_kg} kg`}
                  </span>
                </div>
                <div className="row" style={{ marginTop: 10 }}>
                  <span className="stepper">
                    <button onClick={() => setStock(f, -1)}>−</button>
                    <b>{f.stock_kg}</b>
                    <button onClick={() => setStock(f, 1)}>+</button>
                  </span>
                  <button className="btn btn-ghost btn-sm" onClick={() => setStock(f, 10)}>Réapprovisionner +10</button>
                </div>
              </div>
            ))}
          </>
        )}
      </div>
      <AdminNav />
      {showAdd && (
        <div className="modal-back" onClick={() => setShowAdd(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <form onSubmit={addFish} style={{ padding: 18, display: 'grid', gap: 4 }}>
              <h3 style={{ margin: 0 }}>Nouveau poisson (variation)</h3>
              <label className="lbl">Plat (catégorie Poissons)</label>
              <input className="input" placeholder="ID du plat (ex: 3)" value={newFish.dish_id} onChange={(e) => setNewFish({ ...newFish, dish_id: e.target.value })} required />
              <label className="lbl">Nom de la variation</label>
              <input className="input" placeholder="Ex: Bar — Assinie" value={newFish.name} onChange={(e) => setNewFish({ ...newFish, name: e.target.value })} required />
              <label className="lbl">Prix (FCFA)</label>
              <input className="input" type="number" value={newFish.price} onChange={(e) => setNewFish({ ...newFish, price: e.target.value })} required />
              <label className="lbl">Stock (kg)</label>
              <input className="input" type="number" step="0.5" value={newFish.stock_kg} onChange={(e) => setNewFish({ ...newFish, stock_kg: e.target.value })} required />
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
