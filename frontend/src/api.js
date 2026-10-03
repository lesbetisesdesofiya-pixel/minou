import axios from 'axios';

// En dev Vite : proxy /api -> Laravel.
// En prod : détection automatique du préfixe.
//   - XAMPP local  : http://localhost/opera/public/app  -> /opera/public/api
//   - VPS (Docker) : https://avepozo.operatogo.net/app -> /api
function guessBase() {
  if (import.meta.env.VITE_API_URL) return import.meta.env.VITE_API_URL;
  if (import.meta.env.DEV) return '/api';
  const m = window.location.pathname.match(/^(\/.+?\/public)(?=\/|$)/);
  return (m ? m[1] : '') + '/api';
}

const baseURL = guessBase();

export const api = axios.create({ baseURL });

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('opera_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.response?.status === 401) {
      localStorage.removeItem('opera_token');
      localStorage.removeItem('opera_role');
      localStorage.removeItem('opera_driver_id');
      if (!window.location.hash.includes('login')) {
        const isLivreur = window.location.hash.includes('livreur');
        window.location.hash = isLivreur ? '#/livreur/login' : '#/admin/login';
      }
    }
    return Promise.reject(err);
  }
);

export const fmt = (n) =>
  `${Number(n || 0).toLocaleString('fr-FR').replace(/,/g, ' ')} FCFA`;
