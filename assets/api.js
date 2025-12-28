export const API_BASE = '';

export function getToken() {
  return localStorage.getItem('token');
}

export function setToken(token) {
  localStorage.setItem('token', token);
}

export function clearToken() {
  localStorage.removeItem('token');
}

export async function apiFetch(path, { method = 'GET', body = null, auth = true } = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  if (auth) {
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
  }

  const resp = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  const status = resp.status;
  const text = await resp.text();
  const data = text ? safeJsonParse(text) : null;

  if (!resp.ok) {
    const rawMsg = data?.message || data?.error || resp.statusText;
    throw new Error(translateApiError(rawMsg, status));
  }

  return data;
}

function safeJsonParse(text) {
  try { return JSON.parse(text); } catch { return { raw: text }; }
}

function translateApiError(message, status) {
  const msg = String(message || '').trim();

  if (status === 401 && msg === 'Invalid credentials.') return 'Неверный логин или пароль';
  if (status === 401 && msg.toLowerCase().includes('jwt')) return 'Сессия истекла. Войдите заново';
  if (status === 403) return 'Недостаточно прав';

  return msg || 'Ошибка запроса';
}
