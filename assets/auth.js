import { apiFetch, setToken, clearToken, getToken } from './api.js';

export async function login(username, password) {
  const data = await apiFetch('/api/login', {
    method: 'POST',
    auth: false,
    body: { username, password },
  });

  if (!data?.token) throw new Error('Токен не пришёл в ответе');
  setToken(data.token);
  return data.token;
}

export async function register(fullName, email, password) {
  const data = await apiFetch('/api/register', {
    method: 'POST',
    auth: false,
    body: { fullName, email, password },
  });

  if (data?.token) setToken(data.token);

  return data;
}

export function logout() {
  clearToken();
}

export function isAuthed() {
  return !!getToken();
}
