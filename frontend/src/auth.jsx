import { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api';

const AuthCtx = createContext(null);
export const useAuth = () => useContext(AuthCtx);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('opera_token') || '');
  const [role, setRole] = useState(() => localStorage.getItem('opera_role') || '');
  const [driverId, setDriverId] = useState(
    () => Number(localStorage.getItem('opera_driver_id') || 0) || null
  );

  useEffect(() => {
    if (!token) return;
    // Résout le driver lié au compte livreur (GET /api/me ajouté côté Laravel)
    api.get('/me').then(({ data }) => {
      if (data?.delivery_person_id) {
        setDriverId(Number(data.delivery_person_id));
        localStorage.setItem('opera_driver_id', String(data.delivery_person_id));
      }
      if (data?.role) {
        setRole(data.role);
        localStorage.setItem('opera_role', data.role);
      }
    }).catch(() => {});
  }, [token]);

  const login = async (email, password) => {
    const { data } = await api.post('/auth/login', { email, password });
    localStorage.setItem('opera_token', data.token);
    localStorage.setItem('opera_role', data.role);
    setToken(data.token);
    setRole(data.role);
    return data; // { token, role, user_id }
  };

  const logout = () => {
    localStorage.removeItem('opera_token');
    localStorage.removeItem('opera_role');
    localStorage.removeItem('opera_driver_id');
    setToken('');
    setRole('');
    setDriverId(null);
  };

  return (
    <AuthCtx.Provider value={{ token, role, driverId, setDriverId, login, logout }}>
      {children}
    </AuthCtx.Provider>
  );
}
