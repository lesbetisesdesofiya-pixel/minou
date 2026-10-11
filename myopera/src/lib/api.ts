// Client API Opera Resto — même base que l'app Kotlin (Api.kt) : JWT Bearer.
// L'URL dépend du site choisi une fois avant le login (voir lib/site.tsx).
import { getApiBase } from './site';

export class UnauthorizedError extends Error {
  constructor() {
    super('Session expirée');
    this.name = 'UnauthorizedError';
  }
}

type Options = {
  method?: string;
  token?: string | null;
  body?: unknown;
};

// Clé API exigée par le serveur (X-API-Key) — à définir dans myopera/.env.local :
// EXPO_PUBLIC_API_KEY=<clé> (fichier ignoré par git, jamais commité).
const API_KEY: string = ((process as any)?.env?.EXPO_PUBLIC_API_KEY as string | undefined) || '';

export async function api(path: string, opts: Options = {}): Promise<any> {
  const res = await fetch(getApiBase() + path, {
    method: opts.method || 'GET',
    headers: {
      Accept: 'application/json',
      ...(API_KEY ? { 'X-API-Key': API_KEY } : {}),
      ...(opts.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(opts.token ? { Authorization: `Bearer ${opts.token}` } : {}),
    },
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  });
  if (res.status === 401) throw new UnauthorizedError();
  const text = await res.text();
  let data: any = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = {};
  }
  if (!res.ok) throw new Error(data?.error || data?.message || `HTTP ${res.status}`);
  return data;
}

export const fmt = (n: number | string | null | undefined): string => {
  const v = Number(n || 0);
  return `${v.toLocaleString('fr-FR').replace(/,/g, ' ')} FCFA`;
};

export const STATUS_LABEL: Record<string, string> = {
  pending: 'Nouvelle',
  'En attente de paiement': 'À payer',
  'Payée': 'Payée',
  PREPARING: 'En cuisine',
  READY_FOR_PICKUP: 'Prête',
  delivered: 'Livrée',
  'Annulée': 'Annulée',
  cancelled: 'Annulée',
};
