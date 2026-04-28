// ============================================================
//  MediRDV — app.js
// ============================================================

// Chemin de base automatique : fonctionne que le site soit à
// http://localhost/medirdv/ ou http://monsite.com/ ou file://
const BASE = (function() {
  if (window.location.protocol === 'file:') {
    // Pour les tests locaux en file://, utiliser un chemin relatif
    return './';
  }
  var p = window.location.pathname;
  var m = p.match(/^(\/[^\/]+\/)/);
  return m ? m[1] : '/';
})();

const API = {
  auth: BASE + 'php/auth.php',
  docs: BASE + 'php/medecins.php',
  rdvs: BASE + 'php/rendez_vous.php',
};


// ── GLOBAL STATE ─────────────────────────────────────────────
let currentUser = null;
let currentRole = null;
let bookingDoc  = null;
let calYear, calMonth;
let selectedDate = null, selectedSlot = null;
let allDoctors = [];
let apptFilter = 'all';

// ── HELPERS ───────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const show = id => $(id)?.classList.remove('hidden');
const hide = id => $(id)?.classList.add('hidden');
const html = (id, h) => { if($(id)) $(id).innerHTML = h; };

function toast(msg, type = 'info') {
  const t = $('toast');
  t.textContent = msg;
  t.className = `show ${type}`;
  setTimeout(() => t.classList.remove('show'), 3200);
}

async function api(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  try {
    const res = await fetch(url, opts);
    const text = await res.text();
    const contentType = res.headers.get('content-type') || '';

    if (!res.ok) {
      if (contentType.includes('application/json')) {
        const errData = JSON.parse(text);
        throw new Error(errData.error || `Erreur HTTP ${res.status}`);
      }
      if (text.trim().startsWith('<')) {
        throw new Error('Erreur serveur PHP : réponse HTML reçue (vérifiez Apache/PHP).');
      }
      throw new Error(`Erreur HTTP ${res.status}: ${res.statusText}`);
    }

    if (!contentType.includes('application/json')) {
      if (text.trim().startsWith('<')) {
        throw new Error('Erreur serveur PHP : réponse HTML reçue (vérifiez Apache/PHP).');
      }
    }

    try {
      return JSON.parse(text);
    } catch (jsonError) {
      console.error('Invalid JSON response from', url, text);
      throw new Error('Erreur serveur : réponse JSON invalide');
    }
  } catch(e) {
    if (e.message.includes('Failed to fetch') || e.message.includes('NetworkError')) {
      throw new Error('Erreur réseau: Impossible de contacter le serveur. Vérifiez que vous accédez au site via http://localhost/ et non via file://');
    }
    throw new Error('Erreur réseau: ' + e.message);
  }
}

// ── MODAL HELPERS ─────────────────────────────────────────────
function openModal(id)  { show(id); document.body.style.overflow = 'hidden'; }
function closeModal(id) { hide(id); document.body.style.overflow = ''; }
function switchModal(from, to) { closeModal(from); openModal(to); }

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.add('hidden');
    document.body.style.overflow = '';
  }
});

// ── ROLE TABS ─────────────────────────────────────────────────
let loginRole = 'patient';
let regRole   = 'patient';

function setLoginRole(r) {
  loginRole = r;
  $('lRolePatient').classList.toggle('active', r === 'patient');
  $('lRoleDoctor').classList.toggle('active',  r === 'medecin');
}
function setRegRole(r) {
  regRole = r;
  $('rRolePatient').classList.toggle('active', r === 'patient');
  $('rRoleDoctor').classList.toggle('active',  r === 'medecin');
  r === 'medecin' ? show('doctorRegFields') : hide('doctorRegFields');
}

// ── AUTH ──────────────────────────────────────────────────────
async function checkSession() {
  try {
    const res = await api(`${API.auth}?action=session`);
    if (res.success && res.logged) {
      currentUser = res.user;
      currentRole = res.role;
      renderNav();
      res.role === 'patient' ? renderPatientDash() : renderDoctorDash();
    } else {
      hide('patientDash'); hide('doctorDash'); show('heroSection');
    }
  } catch(e) {
    console.error('Session check failed:', e);
    hide('patientDash'); hide('doctorDash'); show('heroSection');
  }
}

async function doLogin() {
  hide('loginError');
  const email = $('loginEmail').value.trim();
  const pwd   = $('loginPwd').value;
  if (!email || !pwd) { showError('loginError', 'Veuillez remplir tous les champs'); return; }
  $('btnLogin').disabled = true; $('btnLogin').textContent = 'Connexion...';
  try {
    const res = await api(`${API.auth}?action=login`, 'POST', { email, mot_de_passe: pwd, role: loginRole });
    $('btnLogin').disabled = false; $('btnLogin').textContent = 'Se connecter';
    if (!res.success) { showError('loginError', res.error); return; }
    currentUser = res.user; currentRole = res.role;
    closeModal('loginModal');
    renderNav();
    res.role === 'patient' ? renderPatientDash() : renderDoctorDash();
    toast(`Bienvenue, ${res.user.prenom} !`, 'success');
  } catch(e) {
    $('btnLogin').disabled = false; $('btnLogin').textContent = 'Se connecter';
    showError('loginError', e.message);
  }
}

async function doRegister() {
  hide('regError');
  const body = {
    prenom: $('rPrenom').value.trim(), nom: $('rNom').value.trim(),
    email: $('rEmail').value.trim(),   telephone: $('rPhone').value.trim(),
    mot_de_passe: $('rPwd').value,
  };
  if (regRole === 'medecin') {
    body.specialite  = $('rSpec').value;
    body.ville       = $('rVille').value;
    body.adresse     = $('rAdresse').value.trim();
    body.tarif       = $('rTarif').value;
    body.description = $('rDesc').value.trim();
  }
  if (!body.prenom || !body.nom || !body.email || !body.mot_de_passe) {
    showError('regError', 'Les champs marqués * sont obligatoires'); return;
  }
  $('btnRegister').disabled = true; $('btnRegister').textContent = 'Création...';
  try {
    const action = regRole === 'medecin' ? 'register_medecin' : 'register_patient';
    const res = await api(`${API.auth}?action=${action}`, 'POST', body);
    $('btnRegister').disabled = false; $('btnRegister').textContent = 'Créer mon compte';
    if (!res.success) { showError('regError', res.error); return; }
    currentUser = res.user; currentRole = res.role;
    closeModal('registerModal');
    renderNav();
    res.role === 'patient' ? renderPatientDash() : renderDoctorDash();
    toast('Compte créé avec succès ! Bienvenue 🎉', 'success');
  } catch(e) {
    $('btnRegister').disabled = false; $('btnRegister').textContent = 'Créer mon compte';
    showError('regError', e.message);
  }
}

async function logout() {
  try {
    await api(`${API.auth}?action=logout`, 'POST');
  } catch(e) {
    console.error('Logout failed:', e);
  }
  currentUser = null; currentRole = null;
  hide('patientDash'); hide('doctorDash'); show('heroSection');
  renderNav();
  toast('Déconnecté');
}

function showError(id, msg) { $(id).textContent = msg; show(id); }

// ── NAV ───────────────────────────────────────────────────────
function renderNav() {
  const menu = $('navMenu');
  if (!currentUser) {
    menu.innerHTML = `
      <button class="btn btn-ghost" onclick="openModal('loginModal')">Connexion</button>
      <button class="btn btn-accent" onclick="openModal('registerModal')">S'inscrire</button>`;
  } else {
    menu.innerHTML = `
      <span class="navbar-user">👤 ${currentUser.prenom} ${currentUser.nom}</span>
      <button class="btn btn-ghost btn-sm" onclick="logout()">Déconnexion</button>`;
  }
}

// ── TABS ──────────────────────────────────────────────────────
function activateTab(groupClass, targetId) {
  document.querySelectorAll(`.${groupClass} .tab`).forEach(t => t.classList.remove('active'));
  document.querySelectorAll(`.${groupClass}-pane`).forEach(p => p.classList.remove('active'));
  const activeTab = document.querySelector(`.${groupClass} .tab[data-target="${targetId}"]`);
  if (activeTab) activeTab.classList.add('active');
  const pane = $(targetId);
  if (pane) pane.classList.add('active');
}

// ── PATIENT DASHBOARD ─────────────────────────────────────────
async function renderPatientDash() {
  hide('heroSection'); hide('doctorDash'); show('patientDash');
  html('patientName', `Bonjour, <em>${currentUser.prenom}</em> !`);
  await loadDoctors();
  await loadMyAppointments();
  activateTab('patient-tabs', 'pSearchTab');
}

async function loadDoctors(params = {}) {
  html('doctorsList', '<div class="spinner"></div>');
  try {
    let url = API.docs;
    const qs = new URLSearchParams(params).toString();
    if (qs) url += '?' + qs;
    const res = await api(url);
    if (!res.success) { html('doctorsList', '<p class="text-muted text-center mt-2">Erreur de chargement</p>'); return; }
    allDoctors = res.medecins;
    renderDoctorCards(allDoctors);
  } catch(e) {
    html('doctorsList', '<p class="text-muted text-center mt-2">Erreur réseau: ' + e.message + '</p>');
  }
}

function renderDoctorCards(docs) {
  if (!docs.length) {
    html('doctorsList', `<div class="empty-state"><div class="icon">🔍</div><h3>Aucun médecin trouvé</h3><p>Essayez d'autres critères</p></div>`);
    return;
  }
  html('doctorsList', docs.map(d => `
    <div class="doctor-card" onclick="openBookingModal(${d.id})">
      <div class="dc-head">
        <div class="dc-avatar">${d.prenom[0]}${d.nom[0]}</div>
        <div style="flex:1">
          <div class="dc-name">Dr. ${d.prenom} ${d.nom}</div>
          <div class="dc-spec">${d.specialite}</div>
          <span class="badge ${d.disponible ? 'badge-green' : 'badge-coral'}">
            <span class="dot ${d.disponible ? 'dot-green' : 'dot-red'}"></span>
            ${d.disponible ? 'Disponible' : 'Indisponible'}
          </span>
        </div>
        ${d.note_moyenne > 0 ? `<div class="dc-rating">★ ${parseFloat(d.note_moyenne).toFixed(1)}</div>` : ''}
      </div>
      <div class="dc-info">
        <span>📍 ${d.ville}</span>
        <span>🏥 ${d.adresse || ''}</span>
        ${d.nb_avis ? `<span>💬 ${d.nb_avis} avis</span>` : ''}
      </div>
      <div class="dc-footer">
        <span class="dc-fee">${d.tarif} TND</span>
        <span class="badge badge-blue">${d.specialite}</span>
        <button class="btn btn-accent btn-sm">Réserver →</button>
      </div>
    </div>`).join(''));
}

function filterDoctors() {
  const name = $('searchName').value.toLowerCase();
  const spec = $('searchSpec').value;
  const city = $('searchCity').value;
  const filtered = allDoctors.filter(d =>
    (!name || (d.prenom+' '+d.nom+' '+d.specialite).toLowerCase().includes(name)) &&
    (!spec || d.specialite === spec) &&
    (!city || d.ville === city)
  );
  renderDoctorCards(filtered);
}

async function loadMyAppointments() {
  html('myApptsList', '<div class="spinner"></div>');
  try {
    const res = await api(API.rdvs);
    if (!res.success) { html('myApptsList', '<p class="text-muted text-center">Erreur de chargement</p>'); return; }
    renderPatientAppts(res.rdvs);
  } catch(e) {
    html('myApptsList', '<p class="text-muted text-center">Erreur réseau: ' + e.message + '</p>');
  }
}

function renderPatientAppts(rdvs) {
  if (!rdvs.length) {
    html('myApptsList', `<div class="empty-state"><div class="icon">📋</div><h3>Aucun rendez-vous</h3><p>Trouvez un médecin et prenez votre premier rendez-vous</p></div>`);
    return;
  }
  const statusMap = {
    en_attente: `<span class="badge badge-warn"><span class="dot dot-orange"></span>En attente</span>`,
    confirme:   `<span class="badge badge-green"><span class="dot dot-green"></span>Confirmé</span>`,
    annule:     `<span class="badge badge-coral"><span class="dot dot-red"></span>Annulé</span>`,
    termine:    `<span class="badge badge-blue">Terminé</span>`,
  };
  html('myApptsList', '<div class="appt-list">' + rdvs.map(a => {
    const dt = new Date(a.date_rdv + 'T00:00:00');
    const day = dt.getDate().toString().padStart(2,'0');
    const mon = dt.toLocaleDateString('fr-FR', {month:'short'}).replace('.','');
    return `<div class="appt-item">
      <div class="appt-date"><div class="day">${day}</div><div class="mon">${mon}</div></div>
      <div class="appt-avatar">${a.med_prenom[0]}${a.med_nom[0]}</div>
      <div class="appt-info">
        <h4>Dr. ${a.med_prenom} ${a.med_nom}</h4>
        <p>${a.specialite} — ⏰ ${a.heure_rdv.slice(0,5)} — ${a.motif}</p>
        ${a.notes_medecin ? `<p style="color:var(--primary);font-size:12px;margin-top:3px">📝 ${a.notes_medecin}</p>` : ''}
      </div>
      <div class="appt-actions">
        ${statusMap[a.statut] || ''}
        ${a.statut === 'en_attente' || a.statut === 'confirme'
          ? `<button class="btn btn-danger btn-sm" onclick="cancelAppt(${a.id})">Annuler</button>` : ''}
      </div>
    </div>`;
  }).join('') + '</div>');
}

async function cancelAppt(id) {
  if (!confirm('Annuler ce rendez-vous ?')) return;
  try {
    const res = await api(`${API.rdvs}?id=${id}&action=annuler`, 'PUT');
    if (res.success) { toast('Rendez-vous annulé'); await loadMyAppointments(); }
    else toast(res.error, 'error');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}

// ── BOOKING ───────────────────────────────────────────────────
async function openBookingModal(docId) {
  try {
    const res = await api(`${API.docs}?id=${docId}`);
    if (!res.success) { toast('Erreur chargement médecin', 'error'); return; }
    bookingDoc = res.medecin;
    selectedDate = null; selectedSlot = null;
    $('bookingDocName').textContent = `Dr. ${bookingDoc.prenom} ${bookingDoc.nom}`;
    $('bookingDocInfo').textContent = `${bookingDoc.specialite} — ${bookingDoc.ville} — ${bookingDoc.tarif} TND`;
    hide('bookingConfirm');
    html('slotsGrid', '<p class="text-muted" style="font-size:13px">Sélectionnez une date</p>');
    const now = new Date();
    calYear = now.getFullYear(); calMonth = now.getMonth();
    renderCalendar();
    openModal('bookingModal');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}

function renderCalendar() {
  const now = new Date();
  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const label = new Date(calYear, calMonth).toLocaleDateString('fr-FR', {month:'long', year:'numeric'});
  $('calLabel').textContent = label.charAt(0).toUpperCase() + label.slice(1);
  const offset = (firstDay + 6) % 7;
  let cells = '';
  for (let i = 0; i < offset; i++) cells += `<div class="cday empty"></div>`;
  for (let d = 1; d <= daysInMonth; d++) {
    const dt = new Date(calYear, calMonth, d);
    const isToday  = dt.toDateString() === now.toDateString();
    const isPast   = dt < new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const isWeekend= dt.getDay() === 0 || dt.getDay() === 6;
    const dateStr  = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isSel    = selectedDate === dateStr;
    let cls = isPast || isWeekend ? 'past' : 'avail';
    if (isSel)   cls += ' sel';
    if (isToday) cls += ' today';
    const click = (isPast || isWeekend) ? '' : `onclick="pickDate('${dateStr}')"`;
    cells += `<div class="cday ${cls}" ${click}>${d}</div>`;
  }
  $('calBody').innerHTML = cells;
}

function changeMonth(dir) {
  calMonth += dir;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  if (calMonth < 0)  { calMonth = 11; calYear--; }
  renderCalendar();
}

async function pickDate(dateStr) {
  selectedDate = dateStr; selectedSlot = null;
  hide('bookingConfirm');
  renderCalendar();
  html('slotsGrid', '<div class="spinner"></div>');
  try {
    const res = await api(`${API.docs}?action=disponibilites&medecin_id=${bookingDoc.id}&date=${dateStr}`);
    if (!res.success || !res.slots.length) {
      html('slotsGrid', '<p class="text-muted" style="font-size:13px">Aucun créneau disponible ce jour</p>');
      return;
    }
    html('slotsGrid', res.slots.map(s =>
      `<div class="slot ${s.disponible ? '' : 'taken'}" ${s.disponible ? `onclick="pickSlot('${s.heure}')"` : ''}>${s.heure}</div>`
    ).join(''));
  } catch(e) {
    html('slotsGrid', '<p class="text-muted" style="font-size:13px">Erreur réseau: ' + e.message + '</p>');
  }
}

function pickSlot(heure) {
  selectedSlot = heure;
  document.querySelectorAll('.slot').forEach(s => s.classList.toggle('sel', s.textContent.trim() === heure));
  const dt = new Date(selectedDate + 'T00:00:00').toLocaleDateString('fr-FR', {weekday:'long', day:'numeric', month:'long'});
  $('bookingSummary').textContent = `${dt} à ${heure}`;
  $('bookingReason').value = '';
  show('bookingConfirm');
}

async function confirmBooking() {
  if (!selectedDate || !selectedSlot) return;
  const btn = $('btnConfirmBooking');
  btn.disabled = true; btn.textContent = 'Confirmation...';
  try {
    const res = await api(API.rdvs, 'POST', {
      medecin_id: bookingDoc.id,
      date_rdv:   selectedDate,
      heure_rdv:  selectedSlot,
      motif:      $('bookingReason').value || 'Consultation',
    });
    btn.disabled = false; btn.textContent = 'Confirmer le rendez-vous';
    if (!res.success) { toast(res.error, 'error'); return; }
    closeModal('bookingModal');
    toast(`✓ Rendez-vous pris avec Dr. ${bookingDoc.nom} !`, 'success');
    await loadMyAppointments();
    activateTab('patient-tabs', 'pApptTab');
  } catch(e) {
    btn.disabled = false; btn.textContent = 'Confirmer le rendez-vous';
    toast('Erreur réseau: ' + e.message, 'error');
  }
}

// ── DOCTOR DASHBOARD ──────────────────────────────────────────
async function renderDoctorDash() {
  hide('heroSection'); hide('patientDash'); show('doctorDash');
  html('doctorName', `Bonjour, Dr. <em>${currentUser.nom}</em> !`);
  html('doctorSub', `${currentUser.specialite} — ${currentUser.ville}`);
  activateTab('doctor-tabs', 'dApptTab');
  await loadDoctorAppts();
  renderDoctorProfile();
}

async function loadDoctorAppts() {
  html('doctorApptsList', '<div class="spinner"></div>');
  try {
    let url = API.rdvs;
    if (apptFilter !== 'all') url += `?statut=${apptFilter}`;
    const res = await api(url);
    if (!res.success) { html('doctorApptsList', '<p class="text-muted text-center">Erreur</p>'); return; }
    renderDoctorStats(res.stats);
    renderDoctorAppts(res.rdvs);
  } catch(e) {
    html('doctorApptsList', '<p class="text-muted text-center">Erreur réseau: ' + e.message + '</p>');
  }
}

function renderDoctorStats(s) {
  if (!s) return;
  html('statsGrid', `
    <div class="stat-card"><div class="stat-num">${s.total}</div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--warn)">${s.en_attente}</div><div class="stat-label">En attente</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--accent)">${s.confirmes}</div><div class="stat-label">Confirmés</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--coral)">${s.aujourd_hui}</div><div class="stat-label">Aujourd'hui</div></div>
  `);
}

function renderDoctorAppts(rdvs) {
  if (!rdvs.length) {
    html('doctorApptsList', `<div class="empty-state"><div class="icon">📋</div><h3>Aucun rendez-vous</h3><p>Pas de rendez-vous dans cette catégorie</p></div>`);
    return;
  }
  const statusMap = {
    en_attente: `<span class="badge badge-warn"><span class="dot dot-orange"></span>En attente</span>`,
    confirme:   `<span class="badge badge-green"><span class="dot dot-green"></span>Confirmé</span>`,
    annule:     `<span class="badge badge-coral"><span class="dot dot-red"></span>Annulé</span>`,
    termine:    `<span class="badge badge-blue">Terminé</span>`,
  };
  html('doctorApptsList', '<div class="appt-list">' + rdvs.map(a => {
    const dt = new Date(a.date_rdv + 'T00:00:00');
    const day = dt.getDate().toString().padStart(2,'0');
    const mon = dt.toLocaleDateString('fr-FR', {month:'short'}).replace('.','');
    return `<div class="appt-item">
      <div class="appt-date"><div class="day">${day}</div><div class="mon">${mon}</div></div>
      <div class="appt-avatar">${a.pat_prenom[0]}${a.pat_nom[0]}</div>
      <div class="appt-info">
        <h4>${a.pat_prenom} ${a.pat_nom}</h4>
        <p>⏰ ${a.heure_rdv.slice(0,5)} — ${a.motif} — 📞 ${a.pat_tel || ''}</p>
      </div>
      <div class="appt-actions">
        ${statusMap[a.statut] || ''}
        ${a.statut === 'en_attente'
          ? `<button class="btn btn-accent btn-sm" onclick="confirmAppt(${a.id})">✓ Confirmer</button>` : ''}
        ${a.statut === 'confirme'
          ? `<button class="btn btn-warn btn-sm" onclick="terminerAppt(${a.id})">Terminer</button>` : ''}
        ${a.statut !== 'annule' && a.statut !== 'termine'
          ? `<button class="btn btn-danger btn-sm" onclick="cancelApptDoc(${a.id})">Annuler</button>` : ''}
        <button class="btn btn-outline btn-sm" onclick="openNotesModal(${a.id},'${(a.notes_medecin||'').replace(/'/g,'&#39;')}')">📝 Notes</button>
      </div>
    </div>`;
  }).join('') + '</div>');
}

function setApptFilter(f, btn) {
  apptFilter = f;
  document.querySelectorAll('#dApptTab .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  loadDoctorAppts();
}

async function confirmAppt(id) {
  try {
    const res = await api(`${API.rdvs}?id=${id}&action=confirmer`, 'PUT');
    if (res.success) { toast('Rendez-vous confirmé ✓', 'success'); await loadDoctorAppts(); }
    else toast(res.error, 'error');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}
async function terminerAppt(id) {
  try {
    const res = await api(`${API.rdvs}?id=${id}&action=terminer`, 'PUT');
    if (res.success) { toast('Rendez-vous terminé'); await loadDoctorAppts(); }
    else toast(res.error, 'error');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}
async function cancelApptDoc(id) {
  if (!confirm('Annuler ce rendez-vous ?')) return;
  try {
    const res = await api(`${API.rdvs}?id=${id}&action=annuler`, 'PUT');
    if (res.success) { toast('Rendez-vous annulé'); await loadDoctorAppts(); }
    else toast(res.error, 'error');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}

let notesRdvId = null;
function openNotesModal(id, existing) {
  notesRdvId = id;
  $('notesText').value = existing || '';
  openModal('notesModal');
}
async function saveNotes() {
  try {
    const res = await api(`${API.rdvs}?id=${notesRdvId}&action=notes`, 'PUT', { notes_medecin: $('notesText').value });
    if (res.success) { toast('Notes enregistrées', 'success'); closeModal('notesModal'); await loadDoctorAppts(); }
    else toast(res.error, 'error');
  } catch(e) {
    toast('Erreur réseau: ' + e.message, 'error');
  }
}

function renderDoctorProfile() {
  const u = currentUser;
  html('doctorProfileInfo', `
    <div class="profile-grid mb-3">
      <div class="pf-field"><span>Prénom</span><p>${u.prenom}</p></div>
      <div class="pf-field"><span>Nom</span><p>${u.nom}</p></div>
      <div class="pf-field"><span>Email</span><p>${u.email}</p></div>
      <div class="pf-field"><span>Téléphone</span><p>${u.telephone || '—'}</p></div>
      <div class="pf-field"><span>Spécialité</span><p>${u.specialite}</p></div>
      <div class="pf-field"><span>Ville</span><p>${u.ville}</p></div>
      <div class="pf-field" style="grid-column:span 2"><span>Adresse</span><p>${u.adresse || '—'}</p></div>
      <div class="pf-field"><span>Tarif</span><p style="color:var(--primary);font-weight:700">${u.tarif} TND</p></div>
      ${u.note_moyenne > 0 ? `<div class="pf-field"><span>Note</span><p style="color:#F6AD55">★ ${parseFloat(u.note_moyenne).toFixed(1)} (${u.nb_avis} avis)</p></div>` : ''}
    </div>
  `);
}

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  checkSession();

  // Enter key on login
  ['loginEmail','loginPwd'].forEach(id => {
    $(id)?.addEventListener('keydown', e => { if(e.key==='Enter') doLogin(); });
  });
});
