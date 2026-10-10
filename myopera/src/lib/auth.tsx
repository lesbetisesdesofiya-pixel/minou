import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { api } from './api';

const KEY = 'opera_token';

// SecureStore ne fonctionne pas sur web : repli localStorage (natif inchangÃ©).
const webStore = {
  getItemAsync: async (k: string) => {
    try {
      return typeof localStorage !== 'undefined' ? localStorage.getItem(k) : null;
    } catch {
      return null;
    }
  },
  setItemAsync: async (k: string, v: string) => {
    try {
      localStorage.setItem(k, v);
    } catch {}
  },
  deleteItemAsync: async (k: string) => {
    try {
      localStorage.removeItem(k);
    } catch {}
  },
};
const store = Platform.OS === 'web' ? webStore : SecureStore;

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
      await store.deleteItemAsync(KEY);
      setToken(null);
      setRole('');
      setDriverId(null);
    } finally {
      setReady(true);
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => {
      store.getItemAsync(KEY).then((stored) => {
        if (!stored) {
          setReady(true);
          return;
        }
        setToken(stored);
        resolveMe(stored);
      });
    }, 0);
    return () => clearTimeout(t);
  }, [resolveMe]);

  const login = async (email: string, password: string) => {
    const data = await api('/auth/login', { method: 'POST', body: { email, password } });
    await store.setItemAsync(KEY, data.token);
    setToken(data.token);
    setRole(data.role || '');
    setReady(true);
    if (data.token) await resolveMe(data.token);
    return { role: data.role as string };
  };

  const logout = async () => {
    await store.deleteItemAsync(KEY);
    setToken(null);
    setRole('');
    setDriverId(null);
  };

  return (
    <Ctx.Provider value={{ token, role, driverId, ready, login, logout, handleUnauthorized: logout }}>
      {children}
    </Ctx.Provider>
  );
}
