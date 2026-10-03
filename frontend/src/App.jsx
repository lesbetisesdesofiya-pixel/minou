import { HashRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth';
import LoginAdmin from './pages/LoginAdmin';
import LoginLivreur from './pages/LoginLivreur';
import AdminCommandes from './pages/AdminCommandes';
import AdminCarte from './pages/AdminCarte';
import AdminLivreurs from './pages/AdminLivreurs';
import AdminRapports from './pages/AdminRapports';
import LivreurCommandes from './pages/LivreurCommandes';
import LivreurRapports from './pages/LivreurRapports';
import LivreurProfil from './pages/LivreurProfil';

function RequireAuth({ roles }) {
  const { token, role } = useAuth();
  if (!token) return <Navigate to="/admin/login" replace />;
  if (roles && !roles.includes(role)) {
    // Redirige vers l'espace du rôle réel
    return <Navigate to={role === 'DELIVERY' ? '/livreur/commandes' : '/admin/commandes'} replace />;
  }
  return <Outlet />;
}

export default function App() {
  return (
    <AuthProvider>
      <HashRouter>
        <Routes>
          <Route path="/" element={<Navigate to="/admin/login" replace />} />
          <Route path="/admin/login" element={<LoginAdmin />} />
          <Route path="/livreur/login" element={<LoginLivreur />} />

          <Route element={<RequireAuth roles={['RESTAURANT', 'ADMIN', 'SUPER']} />}>
            <Route path="/admin/commandes" element={<AdminCommandes />} />
            <Route path="/admin/carte" element={<AdminCarte />} />
            <Route path="/admin/stocks" element={<AdminCarte />} />
            <Route path="/admin/livreurs" element={<AdminLivreurs />} />
            <Route path="/admin/rapports" element={<AdminRapports />} />
            <Route path="/admin" element={<Navigate to="/admin/commandes" replace />} />
          </Route>

          <Route element={<RequireAuth roles={['DELIVERY', 'RESTAURANT', 'ADMIN', 'SUPER']} />}>
            <Route path="/livreur/commandes" element={<LivreurCommandes />} />
            <Route path="/livreur/rapports" element={<LivreurRapports />} />
            <Route path="/livreur/profil" element={<LivreurProfil />} />
            <Route path="/livreur" element={<Navigate to="/livreur/commandes" replace />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </HashRouter>
    </AuthProvider>
  );
}
