import { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { useAuth } from '../auth';
import { TopBar, LivreurNav } from '../components/layout';

// Design: op_ra_livreur_rapports_paiements
// 100% API réelle : GET /delivery/earnings?driver_id=
export default function LivreurRapports() {
  const { driverId } = useAuth();
  const [data, setData] = useState(null);
  const [err, setErr] = useState('');

  useEffect(() => {
    if (!driverId) return;
    api.get('/delivery/earnings', { params: { driver_id: driverId } })
      .then(({ data }) => setData(data))
      .catch((e) => setErr(e?.response?.data?.error || 'Chargement impossible.'));
  }, [driverId]);

  const goal = 12;
  const count = data?.history?.length || 0;
  const pct = Math.min(100, Math.round((count / goal) * 100));

  return (
    <div className="shell">
      <TopBar title="Rapports & Paiements" subtitle="Gains du shift" />
      <div className="page">
        {err && <div className="err">{err}</div>}
        {!driverId && <div className="err">Compte livreur non lié. Contacte la régie.</div>}
        {data && (
          <>
            <div className="hero">
              <div className="muted" style={{ color: '#cbd5e1' }}>Gains nets</div>
              <div className="big">{fmt(data.earned)}</div>
              <div className="row" style={{ marginTop: 8 }}>
                <span className="badge b-green">Reçu : {fmt(data.received)}</span>
                <span className="badge b-amber">En attente : {fmt(data.pending)}</span>
              </div>
              <div className="muted" style={{ color: '#fbbf24', marginTop: 8 }}>💡 Reversement avant 23h30 auprès du régisseur</div>
            </div>
            <div className="card">
              <div className="row"><b>Objectif shift : {count}/{goal} courses</b><span>{pct}%</span></div>
              <div className="bar" style={{ marginTop: 8 }}><i style={{ width: `${pct}%` }} /></div>
              <div className="muted" style={{ marginTop: 6 }}>+3 000 FCFA de bonus si 12 courses • Niveau 1</div>
            </div>
            <div className="card">
              <b>Historique</b>
              <table className="tbl" style={{ marginTop: 8 }}>
                <thead><tr><th>#</th><th>Quartier</th><th>Gain</th><th>Statut</th></tr></thead>
                <tbody>
                  {data.history.map((h) => (
                    <tr key={h.id}>
                      <td>#OP-{h.id}</td>
                      <td>{h.neighborhood}</td>
                      <td>{Number(h.driver_amount).toLocaleString('fr-FR')} F</td>
                      <td>{h.driver_paid ? <span className="badge b-green">Validé</span> : <span className="badge b-amber">Clôturé</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <button className="btn btn-navy" onClick={() => window.print()}>📄 Générer mon récapitulatif de shift (PDF via impression)</button>
          </>
        )}
      </div>
      <LivreurNav />
    </div>
  );
}
