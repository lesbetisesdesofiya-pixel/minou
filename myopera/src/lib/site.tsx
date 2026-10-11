import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

export type Site = { id: string; label: string; host: string };

export const SITES: Site[] = [
  { id: 'avedji', label: 'Avedji', host: 'avedji.operatogo.net' },
  { id: 'legbassito', label: 'Legbassito', host: 'legbassito.operatogo.net' },
  { id: 'avepozo', label: 'Avepozo', host: 'avepozo.operatogo.net' },
];

const KEY = 'opera_site';

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

// Base API déterminée par le site choisi (une seule fois, avant le login).
let apiBase = 'https://avepozo.operatogo.net/api';
export const getApiBase = () => apiBase;

function applySite(s: Site | null) {
  if (s) apiBase = `https://${s.host}/api`;
}

export async function loadStoredSite(): Promise<Site | null> {
  try {
    const id = await store.getItemAsync(KEY);
    const s = SITES.find((x) => x.id === id) || null;
    applySite(s);
    return s;
  } catch {
    return null;
  }
}

export async function storeSite(id: string): Promise<Site | null> {
  const s = SITES.find((x) => x.id === id) || null;
  if (s) {
    try {
      await store.setItemAsync(KEY, s.id);
    } catch {}
    applySite(s);
  }
  return s;
}

export async function clearSite() {
  try {
    await store.deleteItemAsync(KEY);
  } catch {}
}

type SiteCtx = {
  site: Site | null;
  siteReady: boolean;
  chooseSite: (id: string) => Promise<void>;
  forgetSite: () => Promise<void>;
};

const Ctx = createContext<SiteCtx>({
  site: null,
  siteReady: false,
  chooseSite: async () => {},
  forgetSite: async () => {},
});

export const useSite = () => useContext(Ctx);

export function SiteProvider({ children }: { children: React.ReactNode }) {
  const [site, setSite] = useState<Site | null>(null);
  const [siteReady, setSiteReady] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => {
      loadStoredSite().then((s) => {
        setSite(s);
        setSiteReady(true);
      });
    }, 0);
    return () => clearTimeout(t);
  }, []);

  const chooseSite = useCallback(async (id: string) => {
    const s = await storeSite(id);
    setSite(s);
  }, []);

  const forgetSite = useCallback(async () => {
    await clearSite();
    setSite(null);
  }, []);

  return <Ctx.Provider value={{ site, siteReady, chooseSite, forgetSite }}>{children}</Ctx.Provider>;
}
