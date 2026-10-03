import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';

export function TopBar({ title, subtitle, right }) {
  const { logout } = useAuth();
  const nav = useNavigate();
  return (
    <div className="topbar">
      <div className="logo">O</div>
      <div style={{ flex: 1 }}>
        <div style={{ fontWeight: 800 }}>{title || 'Opéra'}</div>
        {subtitle && <div className="muted" style={{ fontSize: 12 }}>{subtitle}</div>}
      </div>
      {right}
      <button
        className="btn btn-ghost btn-sm"
        onClick={() => { logout(); nav('/admin/login', { replace: true }); }}
        title="Déconnexion"
      >⏻</button>
    </div>
  );
}

export function AdminNav() {
  return (
    <nav className="bottomnav">
      <NavLink to="/admin/commandes" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">🧾</span>Commandes</NavLink>
      <NavLink to="/admin/carte" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">🍽️</span>Menu & Stock</NavLink>
      <NavLink to="/admin/livreurs" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">🛵</span>Livreurs</NavLink>
      <NavLink to="/admin/rapports" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">📊</span>Rapports</NavLink>
    </nav>
  );
}

export function LivreurNav() {
  return (
    <nav className="bottomnav n3">
      <NavLink to="/livreur/commandes" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">📦</span>Commandes</NavLink>
      <NavLink to="/livreur/rapports" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">💰</span>Rapports</NavLink>
      <NavLink to="/livreur/profil" className={({ isActive }) => 'navlink' + (isActive ? ' active' : '')}><span className="ico">👤</span>Mon Profil</NavLink>
    </nav>
  );
}

export function StatusBadge({ status }) {
  const map = {
    pending: ['b-amber', 'Nouvelle'],
    'En attente de paiement': ['b-amber', 'À payer'],
    Payée: ['b-blue', 'Payée'],
    PREPARING: ['b-amber', 'En cuisine'],
    READY_FOR_PICKUP: ['b-green', 'Prête'],
    delivered: ['b-green', 'Livrée'],
    Annulée: ['b-grey', 'Annulée'],
    cancelled: ['b-grey', 'Annulée'],
  };
  const [cls, label] = map[status] || ['b-grey', status];
  return <span className={'badge ' + cls}>{label}</span>;
}

// Modale "Alerte Nouvelle Commande" (design op_ra_alerte_nouvelle_commande_mobile)
export function NewOrderAlert({ order, onAccept, onRefuse, onClose }) {
  if (!order) return null;
  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div className="alert-banner">
          <div style={{ fontSize: 34 }}>🔔</div>
          <div className="badge" style={{ background: 'rgba(255,255,255,.25)', color: '#fff' }}>Alerte entrante</div>
          <h2 style={{ margin: '8px 0 0' }}>Nouvelle commande !</h2>
          <div>#OP-{order.id} • {order.total_amount} FCFA</div>
        </div>
        <div style={{ padding: 16, display: 'grid', gap: 10 }}>
          <div className="row">
            <b>{order.client_name}</b>
            <span className="badge b-green">Payé en ligne</span>
          </div>
          <div className="muted">{order.client_phone} • {order.neighborhood}</div>
          <button className="btn btn-orange" onClick={() => onAccept(order)}>Accepter la commande</button>
          <button className="btn btn-ghost" onClick={() => onRefuse(order)}>Refuser la commande</button>
        </div>
      </div>
    </div>
  );
}
