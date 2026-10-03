import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../auth';

// Design: op_ra_admin_connexion — carte centrée, sélecteur de rôle démo, email + password
export default function LoginAdmin() {
  const { login } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('admin@opera.com');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setErr('');
    setLoading(true);
    try {
      const data = await login(email.trim(), password);
      nav(data.role === 'DELIVERY' ? '/livreur/commandes' : '/admin/commandes', { replace: true });
    } catch (e2) {
      setErr(e2?.response?.data?.error || 'Identifiants invalides.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-wrap">
      <div className="topbar">
        <div className="logo">O</div>
        <b>Opéra Admin</b>
        <span className="badge b-green" style={{ marginLeft: 'auto' }}><span className="pulse" /> Système central actif</span>
      </div>
      <div className="login-main">
        <div className="card login-card">
          <span className="badge b-navy">Accès réservé</span>
          <h1 style={{ margin: '10px 0 4px' }}>Connexion sécurisée</h1>
          <p className="muted">Gérant de salle & Chef de cuisine — Management OS</p>
          {err && <div className="err">{err}</div>}
          <form onSubmit={submit}>
            <label className="lbl">Email professionnel</label>
            <input className="input" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="direction@opera-restaurant.ci" autoComplete="username" />
            <label className="lbl">Mot de passe</label>
            <div style={{ position: 'relative' }}>
              <input className="input" type={show ? 'text' : 'password'} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" autoComplete="current-password" />
              <button type="button" className="btn btn-ghost btn-sm" style={{ position: 'absolute', right: 6, top: 7 }} onClick={() => setShow(!show)}>{show ? 'Masquer' : 'Voir'}</button>
            </div>
            <div style={{ height: 14 }} />
            <button className="btn btn-navy" disabled={loading}>{loading ? 'Connexion…' : 'Se connecter au tableau de bord'}</button>
          </form>
          <div className="row" style={{ marginTop: 12 }}>
            <span className="muted">🔒 SSL 256-bit</span>
            <Link to="/livreur/login" className="muted">Espace livreur →</Link>
          </div>
        </div>
      </div>
      <div className="muted" style={{ textAlign: 'center', padding: 12 }}>Management OS v3.8.4 — Support technique</div>
    </div>
  );
}
