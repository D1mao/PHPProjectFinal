import { apiFetch } from './api.js';

export async function fetchMe() {
  return await apiFetch('/api/me');
}

export function hasRole(me, role) {
  return Array.isArray(me?.roles) && me.roles.includes(role);
}
