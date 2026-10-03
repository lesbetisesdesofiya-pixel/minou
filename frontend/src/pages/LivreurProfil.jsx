import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useAuth } from '../auth';
import { TopBar, LivreurNav } from '../components/layout';

// Profil coursier : infos liées au compte (via GET /api/me + /delivery/persons)
export default function LivreurProfil() {
  const { logout, driverId } = useAuth();
  const nav = useNavigate();
  const [me, setMe] = useState(null);
  const [driver, setDriver] = useState(null);

  useEffect(() => {
    api.get('/me').then(async ({ data }) => {
      setMe(data);
      if (data?.delivery_person_id) {
        const { data: persons } = await api.get('/delivery/persons');
        setDriver((persons || []).find((p) => p.id === data.delivery_person_id) || null);
      }
    }).catch(() => {});
  }, []);

  return (
    <div className="shell">
      <TopBar title="Mon Profil" subtitle={me?.email || ''} />
      <div className="page">
        <div className="card">
          <b>👤 {driver ? `${driver.first_name} ${driver.last_name}` : 'Coursier'}</b>
          <div className="muted">{me?.email} • Rôle {me?.role}</div>
          {driver && <div className="muted" style={{ marginTop: 6 }}>📞 {driver.phone} • {driver.active ? '✅ Actif' : '⏸ Inactif'}</div>}
        </div>
        <button className="btn btn-ghost" onClick={() => { logout(); nav('/livreur/login', { replace: true }); }}>Se déconnecter</button>
      </div>
      <LivreurNav />
    </div>
  );
}
