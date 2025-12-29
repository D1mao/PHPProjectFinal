import { isAuthed, login, register, logout } from './auth.js';
import { apiFetch } from './api.js';
import { fetchMe, hasRole } from './me.js';

let ME = null;
let USERS_CACHE = null;

async function getUsersForSelect() {
  if (USERS_CACHE) return USERS_CACHE;
  USERS_CACHE = await apiFetch('/api/users');
  return USERS_CACHE;
}

document.addEventListener('DOMContentLoaded', async () => {
  setupHeaderButtons();
  setupForms();

  const ok = await bootstrapAuthGuard();
  if (!ok) return;

  if (location.pathname === '/app') {
    await loadRooms();
    await loadMyBookings();
  }

  if (location.pathname === '/admin/users') {
    await loadAdminUsers();
  }

  if (location.pathname.startsWith('/rooms/')) {
    await loadRoomView();
  }

});

function setupHeaderButtons() {
  const logoutBtn = document.getElementById('btn-logout');
  if (!logoutBtn) return;

  logoutBtn.addEventListener('click', () => {
    logout();
    location.replace('/login');
  });
}

function setupForms() {
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(loginForm);
      try {
        await login(fd.get('username'), fd.get('password'));
        location.replace('/app');
      } catch (err) {
        showFlash(err?.message || 'Ошибка логина', true);
      }
    });
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(registerForm);
      try {
        await register(fd.get('fullName'), fd.get('email'), fd.get('password'));
        location.replace('/app');
      } catch (err) {
        showFlash(err?.message || 'Ошибка регистрации', true);
      }
    });
  }
}

async function bootstrapAuthGuard() {
  const path = location.pathname;
  const isProtected =
  path === '/app' ||
  path.startsWith('/admin') ||
  path.startsWith('/rooms/');


  if ((path === '/login' || path === '/register') && isAuthed()) {
    location.replace('/app');
    return false;
  }

  if (isProtected && !isAuthed()) {
    location.replace('/login');
    return false;
  }

  if (isProtected && isAuthed()) {
    try {
      ME = await fetchMe();
    } catch (e) {
      logout();
      location.replace('/login');
      return false;
    }

    if (path.startsWith('/admin') && !hasRole(ME, 'ROLE_ADMIN')) {
      location.replace('/app');
      return false;
    }
  }

  showApp();
  fillHeaderMe();
  return true;
}

function showApp() {
  const boot = document.getElementById('boot-screen');
  const root = document.getElementById('app-root');
  if (boot) boot.style.display = 'none';
  if (root) root.style.display = 'block';

  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn && isAuthed()) logoutBtn.style.display = 'inline-block';
}

function fillHeaderMe() {
  const authEmail = document.getElementById('auth-email');
  if (!authEmail) return;

  if (ME?.fullName) {
    const adminMark = hasRole(ME, 'ROLE_ADMIN') ? ' (админ)' : '';
    authEmail.textContent = `${ME.fullName}${adminMark}`;
  } else if (ME?.email) {
    const adminMark = hasRole(ME, 'ROLE_ADMIN') ? ' (админ)' : '';
    authEmail.textContent = `${ME.email}${adminMark}`;
  } else {
    authEmail.textContent = '';
  }

  const adminLink = document.getElementById('admin-link');
  if (adminLink) {
    adminLink.style.display = hasRole(ME, 'ROLE_ADMIN') ? 'inline' : 'none';
  }
}

/** ===== Комнаты ===== */

async function loadRooms() {
  const el = document.getElementById('rooms');
  if (!el) return;

  el.innerHTML = 'Загрузка комнат...';

  try {
    const rooms = await apiFetch('/api/rooms');
    el.innerHTML = renderRoomsView(rooms);
    bindRoomActions();
  } catch (e) {
    el.innerHTML = `<div style="border:1px solid #d33;padding:10px;">Ошибка: ${escapeHtml(e.message)}</div>`;
  }
}

function renderRoomsView(rooms) {
  const isAdmin = hasRole(ME, 'ROLE_ADMIN');

  const rows = (rooms || []).map(r => `
    <tr>
      <td>${r.id}</td>
      <td><a href="/rooms/${r.id}" class="btn-inline">${escapeHtml(r.name)}</a></td>
      <td>${r.capacity}</td>
      <td>${escapeHtml(r.location)}</td>
      <td>${escapeHtml(r.description ?? '')}</td>
      <td>
        ${isAdmin ? `
          <button class="btn" data-action="edit-room" data-id="${r.id}" type="button">Редактировать</button>
          <button class="btn btn-danger" data-action="delete-room" data-id="${r.id}" type="button">Удалить</button>
        ` : `<span class="muted">—</span>`}
      </td>
    </tr>
  `).join('');

  return `
    <div class="card">
      <div class="row">
        <h2 style="margin:0;">Переговорные</h2>
        ${isAdmin ? `<button class="btn btn-primary" data-action="create-room" type="button">+ Создать</button>` : ''}
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Название</th><th>Вместимость</th><th>Локация</th><th>Описание</th><th>Действия</th>
          </tr>
        </thead>
        <tbody>
          ${rows || '<tr><td colspan="6" class="muted">Комнат нет</td></tr>'}
        </tbody>
      </table>

      ${isAdmin ? renderRoomModal() : ''}
    </div>
  `;
}

function renderRoomModal() {
  return `
  <dialog id="room-modal">
    <div class='modal'>
      <form method="dialog" id="room-form" style="min-width:420px; display:grid; gap:10px;">
        <h3 id="room-modal-title" style="margin:0;">Комната</h3>

        <input type="hidden" name="id" />

        <label>Название
          <input name="name" required />
        </label>

        <label>Вместимость
          <input name="capacity" type="number" min="1" required />
        </label>

        <label>Локация
          <input name="location" required />
        </label>

        <label>Описание
          <textarea name="description" rows="3"></textarea>
        </label>

        <div class="form-actions">
          <button class="btn btn-ghost" type="button" data-action="close-modal">Отмена</button>
          <button class="btn btn-primary" type="submit">Сохранить</button>
        </div>
        <div id="room-form-error" style="color:#d33;"></div>
      </form>
    <div>  
  </dialog>
  `;
}

function bindRoomActions() {
  document.querySelectorAll('[data-action="create-room"]').forEach(btn => {
    btn.addEventListener('click', () => openRoomModal({}));
  });

  document.querySelectorAll('[data-action="edit-room"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const tr = btn.closest('tr');
      openRoomModal({
        id,
        name: tr.children[1].textContent,
        capacity: tr.children[2].textContent,
        location: tr.children[3].textContent,
        description: tr.children[4].textContent,
      });
    });
  });

  document.querySelectorAll('[data-action="delete-room"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if (!confirm(`Удалить переговорную #${id}?`)) return;

      try {
        await apiFetch(`/api/admin/rooms/delete/${id}`, { method: 'DELETE' });
        showFlash('Удалено ✅');
        await loadRooms();
      } catch (e) {
        showFlash(e.message || 'Ошибка удаления', true);
      }
    });
  });

  const modal = document.getElementById('room-modal');
  const form = document.getElementById('room-form');
  const closeBtn = document.querySelector('[data-action="close-modal"]');

  if (closeBtn && modal) closeBtn.addEventListener('click', () => modal.close());

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('room-form-error');
      if (errEl) errEl.textContent = '';

      const fd = new FormData(form);
      const id = fd.get('id');

      const payload = {
        name: fd.get('name'),
        capacity: Number(fd.get('capacity')),
        location: fd.get('location'),
        description: fd.get('description'),
      };

      try {
        if (!id) {
          await apiFetch('/api/admin/rooms/create', { method: 'POST', body: payload });
          showFlash('Комната создана ✅');
        } else {
          await apiFetch(`/api/admin/rooms/update/${id}`, { method: 'PATCH', body: payload });
          showFlash('Комната обновлена ✅');
        }

        if (modal) modal.close();
        await loadRooms();
      } catch (e2) {
        if (errEl) errEl.textContent = e2.message || 'Ошибка сохранения';
      }
    });
  }
}

function openRoomModal(room) {
  const modal = document.getElementById('room-modal');
  const form = document.getElementById('room-form');
  const title = document.getElementById('room-modal-title');
  const errEl = document.getElementById('room-form-error');

  if (!modal || !form) return;

  if (errEl) errEl.textContent = '';

  form.elements['id'].value = room.id || '';
  form.elements['name'].value = room.name || '';
  form.elements['capacity'].value = room.capacity || '';
  form.elements['location'].value = room.location || '';
  form.elements['description'].value = room.description || '';

  if (title) title.textContent = room.id ? `Редактирование #${room.id}` : 'Создание переговорной';

  modal.showModal();
}

/** ===== Мои брони ===== */

async function loadMyBookings() {
  const el = document.getElementById('my-bookings');
  if (!el) return;

  el.innerHTML = 'Загрузка броней...';

  try {
    const bookings = await apiFetch('/api/bookings/my');
    el.innerHTML = renderMyBookings(bookings);
    bindMyBookingActions();
  } catch (e) {
    el.innerHTML = `<div style="border:1px solid #d33;padding:10px;">Ошибка: ${escapeHtml(e.message)}</div>`;
  }
}

function renderMyBookings(bookings) {
  const rows = (bookings || []).map(b => {
    const room = b.room;
    const creator = b.createdBy;
    const participants = (b.participants || []).map(p => p.fullName).join(', ');

    const isCreator = creator?.id === ME?.id;
    const canCancel = isCreator && b.status !== 'cancelled';

    return `
      <tr>
        <td>${b.id}</td>
        <td>
          <a href="/rooms/${room.id}" class="btn-inline">${escapeHtml(room.name)}</a>
          <div><small>${escapeHtml(room.location ?? '')}</small></div>
        </td>
        <td>${formatIso(b.startAt)}</td>
        <td>${formatIso(b.endAt)}</td>
        <td>${escapeHtml(b.status)}</td>
        <td>${escapeHtml(creator?.fullName || '')}</td>
        <td>${escapeHtml(participants)}</td>
        <td>
          ${canCancel
            ? `<button class="btn btn-danger" data-action="cancel-my-booking" data-id="${b.id}" type="button">Отменить</button>`
            : `<span class="muted">—</span>`
          }
        </td>
      </tr>
    `;
  }).join('');

  return `
    <div class="card">
      <div class="row">
        <h2 style="margin:0;">Мои брони</h2>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Комната</th><th>Start</th><th>End</th><th>Status</th><th>Created by</th><th>Participants</th><th></th>
          </tr>
        </thead>
        <tbody>
          ${rows || '<tr><td colspan="8" class="muted">Броней нет</td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

function bindMyBookingActions() {
  document.querySelectorAll('[data-action="cancel-my-booking"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if (!confirm(`Отменить бронь #${id}?`)) return;

      try {
        await apiFetch(`/api/bookings/cancel/${id}`, { method: 'DELETE' });
        showFlash('Бронь отменена ✅');
        await loadMyBookings();
      } catch (e) {
        showFlash(e.message || 'Ошибка отмены', true);
      }
    });
  });
}

/** ===== Одна комната ===== */

async function loadRoomView() {
  const el = document.getElementById('room-view');
  if (!el) return;

  const roomId = el.dataset.roomId || getRoomIdFromPath();
  if (!roomId) {
    el.innerHTML = 'Некорректный roomId';
    return;
  }

  el.innerHTML = 'Загрузка переговорки...';

  try {
    const room = await apiFetch(`/api/rooms/${roomId}`);
    el.innerHTML = renderRoomView(room);
    bindRoomViewActions(room);
  } catch (e) {
    el.innerHTML = `<div style="border:1px solid #d33;padding:10px;">Ошибка: ${escapeHtml(e.message)}</div>`;
  }
}

function renderRoomView(room) {
  const bookings = (room.bookings || []).filter(b => b.status !== 'archived');

  const rows = bookings.map(b => {
    const creatorName = b.createdBy?.fullName || '';
    const participants = (b.participants || []).map(p => p.fullName).join(', ');

    const isCreator = b.createdBy?.id === ME?.id;
    const canCancel = isCreator && b.status !== 'cancelled';
    const canEdit = isCreator && b.status !== 'cancelled';

    return `
      <tr>
        <td>${b.id}</td>
        <td>${formatIso(b.startAt)}</td>
        <td>${formatIso(b.endAt)}</td>
        <td>${escapeHtml(b.status)}</td>
        <td>${escapeHtml(creatorName)}</td>
        <td>${escapeHtml(participants)}</td>
        <td>
          ${canEdit ? `<button class="btn" data-action="edit-booking" data-id="${b.id}" type="button">Изменить</button>` : `<span class="muted">—</span>`}
          ${canCancel ? `<button class="btn btn-danger" data-action="cancel-booking-room" data-id="${b.id}" type="button">Отменить</button>` : ''}
        </td>
      </tr>
    `;
  }).join('');

  return `
    <div class="card">
      <div class="row" style="justify-content:flex-start;">
        <a href="/app" class="btn btn-ghost btn-inline">← Назад</a>
      </div>

      <h1 style="margin:10px 0 6px 0;">${escapeHtml(room.name)}</h1>

      <div class="muted" style="display:grid; gap:4px;">
        <div><b>Вместимость:</b> ${room.capacity}</div>
        <div><b>Локация:</b> ${escapeHtml(room.location)}</div>
        <div><b>Описание:</b> ${escapeHtml(room.description || '')}</div>
      </div>

      <hr class="hr">

      <div class="row">
        <h2 style="margin:0;">Брони по этой переговорке</h2>
        <button class="btn btn-primary" data-action="create-booking" type="button">+ Создать бронь</button>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Start</th><th>End</th><th>Status</th><th>Created by</th><th>Participants</th><th></th>
          </tr>
        </thead>
        <tbody>
          ${rows || '<tr><td colspan="7" class="muted">Броней нет</td></tr>'}
        </tbody>
      </table>

      ${renderBookingModal(room.id)}
    </div>
  `;
}

function renderBookingModal(roomId) {
  return `
  <dialog id="booking-modal">
    <div class='modal'>
      <form method="dialog" id="booking-form" style="min-width:540px; display:grid; gap:10px;">
        <h3 id="booking-modal-title" style="margin:0;">Бронь</h3>

        <input type="hidden" name="id" />
        <input type="hidden" name="roomId" value="${roomId}" />

        <label>Start
          <input name="startAt" type="datetime-local" required />
        </label>

        <label>End
          <input name="endAt" type="datetime-local" required />
        </label>

        <label>Участники
          <select name="participants" id="participants-select" multiple size="6" style="width:100%;"></select>
        </label>
        <div style="opacity:.7; font-size:13px;">
          Ctrl (Cmd на Mac) — выделить несколько.
        </div>


        <div class="form-actions">
          <button class="btn btn-ghost" type="button" data-action="close-booking-modal">Отмена</button>
          <button class="btn btn-primary" type="submit">Сохранить</button>
        </div>

        <div id="booking-form-error" style="color:#d33;"></div>
      </form>
    <div>  
  </dialog>
  `;
}

function bindRoomViewActions(room) {
  const createBtn = document.querySelector('[data-action="create-booking"]');
  if (createBtn) {
    createBtn.addEventListener('click', async () => await openBookingModal({ roomId: room.id }));
  }

  document.querySelectorAll('[data-action="edit-booking"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.id);
      const b = (room.bookings || []).find(x => x.id === id);
      if (!b) return;

      await openBookingModal({
        id: b.id,
        roomId: room.id,
        startAt: isoToDatetimeLocal(b.startAt),
        endAt: isoToDatetimeLocal(b.endAt),
        participants: (b.participants || []).map(p => p.id).join(','),
      });
    });
  });

  document.querySelectorAll('[data-action="cancel-booking-room"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if (!confirm(`Отменить бронь #${id}?`)) return;

      try {
        await apiFetch(`/api/bookings/cancel/${id}`, { method: 'DELETE' });
        showFlash('Бронь отменена ✅');
        await loadRoomView();
        await loadMyBookings?.();
      } catch (e) {
        showFlash(e.message || 'Ошибка отмены', true);
      }
    });
  });

  const modal = document.getElementById('booking-modal');
  const closeBtn = document.querySelector('[data-action="close-booking-modal"]');
  const form = document.getElementById('booking-form');

  if (closeBtn && modal) closeBtn.addEventListener('click', () => modal.close());

  if (form) {
    const startInput = form.elements['startAt'];
    const endInput = form.elements['endAt'];

    const applyMinNow = () => {
      const nowLocal = nowToDatetimeLocalMin();
      startInput.min = nowLocal;
      endInput.min = nowLocal;
    };
    applyMinNow();

    startInput.addEventListener('change', () => {
      if (startInput.value) endInput.min = startInput.value;
      validateBookingForm(form);
    });
    endInput.addEventListener('change', () => validateBookingForm(form));

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const errEl = document.getElementById('booking-form-error');
      if (errEl) errEl.textContent = '';

      const ok = validateBookingForm(form);
      if (!ok) return;

      const fd = new FormData(form);
      const id = fd.get('id');
      const roomId = Number(fd.get('roomId'));
      const participants = getSelectedParticipantIds();

      try {
        if (!id) {
          const payload = {
            startAt: datetimeLocalToApi(fd.get('startAt')),
            endAt: datetimeLocalToApi(fd.get('endAt')),
            roomId,
            participants,
          };
          await apiFetch('/api/bookings/create', { method: 'POST', body: payload });
          showFlash('Бронь создана ✅');
        } else {
          const payload = {
            startAt: datetimeLocalToApi(fd.get('startAt')),
            endAt: datetimeLocalToApi(fd.get('endAt')),
            participants,
          };
          await apiFetch(`/api/bookings/update/${id}`, { method: 'PATCH', body: payload });
          showFlash('Бронь обновлена ✅');
        }

        if (modal) modal.close();
        await loadRoomView();
      } catch (e2) {
        if (errEl) errEl.textContent = e2.message || 'Ошибка сохранения';
      }
    });
  }
}

async function openBookingModal(b) {
  const modal = document.getElementById('booking-modal');
  const form = document.getElementById('booking-form');
  const title = document.getElementById('booking-modal-title');
  const errEl = document.getElementById('booking-form-error');

  if (!modal || !form) return;
  if (errEl) errEl.textContent = '';

  form.elements['id'].value = b.id || '';
  form.elements['roomId'].value = b.roomId || form.elements['roomId'].value;

  form.elements['startAt'].value = b.startAt || '';
  form.elements['endAt'].value = b.endAt || '';

  const nowMin = nowToDatetimeLocalMin();
  form.elements['startAt'].min = nowMin;
  form.elements['endAt'].min = b.startAt || nowMin;

  if (title) title.textContent = b.id ? `Изменить бронь #${b.id}` : 'Создать бронь';

  const selectedIds = Array.isArray(b.participantIds) ? b.participantIds : [];
  await fillParticipantsSelect(selectedIds);

  modal.showModal();
}

function validateBookingForm(form) {
  const errEl = document.getElementById('booking-form-error');
  const start = form.elements['startAt'].value;
  const end = form.elements['endAt'].value;

  if (!start || !end) return true;

  const startMs = Date.parse(start);
  const endMs = Date.parse(end);
  const nowMs = Date.now();

  if (Number.isNaN(startMs) || Number.isNaN(endMs)) {
    if (errEl) errEl.textContent = 'Некорректный формат даты/времени';
    return false;
  }

  if (startMs < nowMs - 60_000) {
    if (errEl) errEl.textContent = 'Start не может быть раньше текущего времени';
    return false;
  }

  if (endMs <= startMs) {
    if (errEl) errEl.textContent = 'End должен быть позже Start';
    return false;
  }

  if (errEl) errEl.textContent = '';
  return true;
}

function getRoomIdFromPath() {
  const parts = location.pathname.split('/').filter(Boolean);
  return parts[1];
}

function datetimeLocalToApi(v) {
  return v;
}

function isoToDatetimeLocal(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function nowToDatetimeLocalMin() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function fillParticipantsSelect(selectedIds = []) {
  const select = document.getElementById('participants-select');
  if (!select) return;

  const users = await getUsersForSelect();

  select.innerHTML = users.map(u => {
    const selected = selectedIds.includes(u.id) ? 'selected' : '';
    return `<option value="${u.id}" ${selected}>${escapeHtml(u.fullName)}</option>`;
  }).join('');
}

function getSelectedParticipantIds() {
  const select = document.getElementById('participants-select');
  if (!select) return [];
  return Array.from(select.selectedOptions)
    .map(o => Number(o.value))
    .filter(n => Number.isFinite(n) && n > 0);
}

/** ===== Админка: пользователи ===== */

async function loadAdminUsers() {
  const el = document.getElementById('admin-users');
  if (!el) return;

  el.innerHTML = 'Загрузка пользователей...';

  try {
    const users = await apiFetch('/api/admin/users');
    el.innerHTML = renderUsersView(users);
    bindUserActions();
  } catch (e) {
    el.innerHTML = `<div style="border:1px solid #d33;padding:10px;">Ошибка: ${escapeHtml(e.message)}</div>`;
  }
}

function renderUsersView(users) {
  const rows = (users || []).map(u => `
    <tr>
      <td>${u.id}</td>
      <td>${escapeHtml(u.email)}</td>
      <td>${escapeHtml(u.fullName ?? '')}</td>
      <td>${escapeHtml((u.roles || []).join(', '))}</td>
      <td>
        <button class="btn" data-action="edit-user" data-id="${u.id}" type="button">Редактировать</button>
      </td>
    </tr>
  `).join('');

  return `
    <div class="card">
      <div class="row">
        <h2 style="margin:0;">Пользователи</h2>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Email</th><th>Full name</th><th>Roles</th><th>Действия</th>
          </tr>
        </thead>
        <tbody>
          ${rows || '<tr><td colspan="5" class="muted">Пользователей нет</td></tr>'}
        </tbody>
      </table>

      ${renderUserModal()}
    </div>
  `;
}


function renderUserModal() {
  return `
  <dialog id="user-modal">
    <div class="modal">
      <form method="dialog" id="user-form" style="min-width:460px; display:grid; gap:10px;">
        <h3 id="user-modal-title" style="margin:0;">Редактирование пользователя</h3>

        <input type="hidden" name="id" />
        <div style="opacity:.75" id="user-email"></div>

        <label>Full name
          <input name="fullName" required />
        </label>

        <label class="check-row">
          <input type="checkbox" name="role_admin" value="ROLE_ADMIN">
          Админ (ROLE_ADMIN)
        </label>

        <div class="form-actions">
          <button class="btn btn-ghost" type="button" data-action="close-user-modal">Отмена</button>
          <button class="btn btn-primary" type="submit">Сохранить</button>
        </div>

        <div id="user-form-error" style="color:#d33;"></div>
      </form>
    <div>  
  </dialog>
  `;
}

function bindUserActions() {
  document.querySelectorAll('[data-action="edit-user"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      openUserModal({
        id: btn.dataset.id,
        email: tr.children[1].textContent,
        fullName: tr.children[2].textContent,
        roles: tr.children[3].textContent.split(',').map(s => s.trim()).filter(Boolean),
      });
    });
  });

  const modal = document.getElementById('user-modal');
  const closeBtn = document.querySelector('[data-action="close-user-modal"]');
  const form = document.getElementById('user-form');

  if (closeBtn && modal) closeBtn.addEventListener('click', () => modal.close());

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const errEl = document.getElementById('user-form-error');
      if (errEl) errEl.textContent = '';

      const fd = new FormData(form);
      const id = fd.get('id');
      const fullName = fd.get('fullName');

      const roles = ['ROLE_USER'];
      if (fd.get('role_admin')) roles.push('ROLE_ADMIN');

      const payload = { fullName, roles };

      try {
        await apiFetch(`/api/admin/users/update/${id}`, { method: 'PATCH', body: payload });
        showFlash('Пользователь обновлён ✅');
        if (modal) modal.close();
        await loadAdminUsers();
      } catch (e2) {
        if (errEl) errEl.textContent = e2.message || 'Ошибка сохранения';
      }
    });
  }
}

function openUserModal(user) {
  const modal = document.getElementById('user-modal');
  const form = document.getElementById('user-form');
  const errEl = document.getElementById('user-form-error');
  const emailEl = document.getElementById('user-email');

  if (!modal || !form) return;

  if (errEl) errEl.textContent = '';
  if (emailEl) emailEl.textContent = `Email: ${user.email}`;

  form.elements['id'].value = user.id || '';
  form.elements['fullName'].value = user.fullName || '';

  const roles = user.roles || [];
  form.elements['role_admin'].checked = roles.includes('ROLE_ADMIN');

  modal.showModal();
}

/** ===== Допы ===== */

function showFlash(message, isError = false) {
  const el = document.getElementById('flash');
  if (!el) return;
  el.style.display = 'block';
  el.textContent = message;
  el.classList.remove('ok', 'err');
  el.classList.add(isError ? 'err' : 'ok');
}


function formatIso(iso) {
  if (!iso) return '';
  return iso
    .replace('T', ' ')
    .replace(/:\d{2}\+.*$/, '');
}


function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
