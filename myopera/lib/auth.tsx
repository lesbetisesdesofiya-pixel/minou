import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { api } from './api';

const KEY = 'opera_token';

type Auth = {
  token: string | null;
  role: string;
  driverId: number | null;
  ready: boolean;
  login: (email: string, password: string) => Promise<{ role: string }>;
  logout: () => Promise<void>;
  handleUnauthorized: () => Promise<void>;
};

const Ctx = createContext<Auth>({
  token: null,
  role: '',
  driverId: null,
  ready: false,
  login: async () => ({ role: '' }),
  logout: async () => {},
  handleUnauthorized: async () => {},
});

export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [role, setRole] = useState('');
  const [driverId, setDriverId] = useState<number | null>(null);
  const [ready, setReady] = useState(false);

  const resolveMe = useCallback(async (t: string) => {
    try {
      const me = await api('/me', { token: t });
      if (me?.delivery_person_id) setDriverId(Number(me.delivery_person_id));
      if (me?.role) setRole(me.role);
    } catch {
      // token invalide -> déconnexion (retombe sur login, pas de blocage)
      await SecureStore.deleteItemAsync(KEY);
      setToken(null);
      setRole('');
      setDriverId(null);
    } finally {
      setReady(true);
    }
  }, []);

  useEffect(() => {
    (async () => {
      const t = await SecureStore.getItemAsync(KEY);
      if (!t) {
        setReady(true);
        return;
      }
      setToken(t);
      await resolveMe(t);
    })();
  }, [resolveMe]);

  const login = async (email: string, password: string) => {
    const data = await api('/auth/login', { method: 'POST', body: { email, password } });
    await SecureStore.setItemAsync(KEY, data.token);
    setToken(data.token);
    setRole(data.role || '');
    setReady(true);
    if (data.token) await resolveMe(data.token);
    return { role: data.role as string };
  };

  const logout = async () => {
    await SecureStore.deleteItemAsync(KEY);
    setToken(null);
    setRole('');
    setDriverId(null);
  };

  return (
    <Ctx.Provider
      value={{ token, role, driverId, ready, login, logout, handleUnauthorized: logout }}
    >
      {children}
    </Ctx.Provider>
  );
}
