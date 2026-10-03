import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../auth';

// Design: op_ra_livreur_connexion — carte mobile, préfixe +225, callout assistance
export default function LoginLivreur() {
  const { login } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('admin2@opera.com');
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
        <div className="logo">🛵</div>
        <div><b>Opéra Coursier</b><div className="muted" style={{ fontSize: 11 }}>Plateforme logistique</div></div>
        <span className="badge b-grey" style={{ marginLeft: 'auto' }}>v2.4</span>
      </div>
      <div className="login-main">
        <div className="card login-card">
          <span className="badge b-amber">Espace coursiers & flotte</span>
          <h1 style={{ margin: '10px 0 4px' }}>Bonjour, champion 👋</h1>
          <p className="muted">Connecte-toi pour voir tes courses et tes gains du shift.</p>
          {err && <div className="err">{err}</div>}
          <form onSubmit={submit}>
            <label className="lbl">Email coursier</label>
            <input className="input" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="ton-email@opera.com" autoComplete="username" />
            <label className="lbl">Mot de passe</label>
            <div style={{ position: 'relative' }}>
              <input className="input" type={show ? 'text' : 'password'} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" autoComplete="current-password" />
              <button type="button" className="btn btn-ghost btn-sm" style={{ position: 'absolute', right: 6, top: 7 }} onClick={() => setShow(!show)}>{show ? 'Masquer' : 'Voir'}</button>
            </div>
            <div style={{ height: 14 }} />
            <button className="btn btn-orange" disabled={loading}>🛵 {loading ? 'Connexion…' : 'Se connecter aux livraisons'} →</button>
          </form>
          <div className="card" style={{ marginTop: 12, background: '#fff7ed', borderColor: '#fed7aa' }}>
            <b style={{ fontSize: 13 }}>Assistance régie</b>
            <div className="muted">Un souci de compte ? Appelle la régie : <a href="tel:+2252720000000">+225 27 20 00 00</a></div>
          </div>
          <div className="row" style={{ marginTop: 12 }}>
            <span className="muted">🔒 SSL 256-bit</span>
            <Link to="/admin/login" className="muted">Espace admin →</Link>
          </div>
        </div>
      </div>
      <div className="muted" style={{ textAlign: 'center', padding: 12 }}>Opéra Dispatch © 2025</div>
    </div>
  );
}
