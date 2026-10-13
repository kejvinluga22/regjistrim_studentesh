// ---------- Gjenerimi i emailit dhe fjalekalimit (njesoj si ne lib.php) ----------
// TRANSLIT vjen nga lib.php (translit_map), qe emaili ne ekran te jete i njejte me ate qe ruhet
const toAscii = s => [...s].map(c => TRANSLIT[c] ?? c).join('');
const emailPart = s => toAscii(s).replace(/[^A-Za-z]/g, '').toLowerCase();
const ucfirst = s => s.charAt(0).toUpperCase() + s.slice(1);
const randDigits = n => Array.from({ length: n }, () => Math.floor(Math.random() * 10)).join('');
const fixedDigits = n => '1234567890'.slice(0, n);

// I njejte me make_password() ne lib.php
function passwordFromTemplate(template, firstName, lastName, num) {
  const f = emailPart(firstName), l = emailPart(lastName);
  const tokens = {
    Emri: ucfirst(f), emri: f, EMRI: f.toUpperCase(),
    Mbiemri: ucfirst(l), mbiemri: l, MBIEMRI: l.toUpperCase(),
    E: f.charAt(0).toUpperCase(), M: l.charAt(0).toUpperCase(), e: f.charAt(0), m: l.charAt(0),
    NUM: num, VITI: String(new Date().getFullYear()),
  };
  return template.replace(/\{(\w+)\}/g, (all, k) => (k in tokens ? tokens[k] : all));
}

const $ = id => document.getElementById(id);
const form = $('form');
const first = $('first_name'), last = $('last_name'), digits = $('digits');
const tplSelect = $('pass-template'), customTpl = $('custom-template'), lenSelect = $('digits-length');
const randomToggle = $('random-digits');

// Zgjedhjet e fjalekalimit mbahen mend ne kete shfletues
const store = {
  get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
};

function currentTemplate() {
  return tplSelect.value === 'custom' ? (customTpl.value.trim() || DEFAULT_TEMPLATE) : tplSelect.value;
}

function updateCreds() {
  const f = emailPart(first.value), l = emailPart(last.value);
  $('out-email').textContent = (f || 'emri') + '.' + (l || 'mbiemri') + '@' + EMAIL_DOMAIN;

  const tpl = currentTemplate();
  const usesNum = tpl.includes('{NUM}');
  const pass = passwordFromTemplate(tpl, first.value || 'Emri', last.value || 'Mbiemri', digits.value);
  $('out-pass').textContent = pass;
  $('password_template').value = tpl;
  $('pass-warn').hidden = pass.length >= 8;
  $('digits-row').hidden = !usesNum;
  $('random-row').hidden = !usesNum;
}

function applyDigitsMode() {
  const n = +lenSelect.value;
  digits.maxLength = n;
  digits.value = randomToggle.checked ? randDigits(n) : fixedDigits(n);
  $('btn-digits').hidden = !randomToggle.checked;
  updateCreds();
}

function applyTemplateMode() {
  const custom = tplSelect.value === 'custom';
  $('custom-row').hidden = !custom;
  $('tokens-help').hidden = !custom;
  updateCreds();
}

first.addEventListener('input', updateCreds);
last.addEventListener('input', updateCreds);
digits.addEventListener('input', () => { digits.value = digits.value.replace(/\D/g, '').slice(0, +lenSelect.value); updateCreds(); });
$('btn-digits').addEventListener('click', () => { digits.value = randDigits(+lenSelect.value); updateCreds(); });
randomToggle.addEventListener('change', () => { store.set('randomDigits', randomToggle.checked ? '1' : '0'); applyDigitsMode(); });
lenSelect.addEventListener('change', () => { store.set('digitsLength', lenSelect.value); applyDigitsMode(); });
tplSelect.addEventListener('change', () => {
  store.set('passTemplate', tplSelect.value);
  applyTemplateMode();
  if (tplSelect.value === 'custom') customTpl.focus();
});
customTpl.addEventListener('input', () => { store.set('customTemplate', customTpl.value); updateCreds(); });
// klik mbi {Emri}, {NUM}... e shton ne modelin tjeter
$('tokens-help').addEventListener('click', e => {
  if (e.target.tagName !== 'CODE') return;
  const pos = customTpl.selectionStart ?? customTpl.value.length;
  customTpl.value = customTpl.value.slice(0, pos) + e.target.textContent + customTpl.value.slice(customTpl.selectionEnd ?? pos);
  customTpl.focus();
  customTpl.dispatchEvent(new Event('input'));
});

// Rikthe zgjedhjet e fundit (ne fillim dhe pas pastrimit te formularit)
function restorePasswordPrefs() {
  const has = (sel, v) => v !== null && [...sel.options].some(o => o.value === v);
  randomToggle.checked = store.get('randomDigits') === '1';
  if (has(lenSelect, store.get('digitsLength'))) lenSelect.value = store.get('digitsLength');
  customTpl.value = store.get('customTemplate') || '';
  if (has(tplSelect, store.get('passTemplate'))) tplSelect.value = store.get('passTemplate');
  // raundi mbetet i zgjedhur per studentin tjeter
  const round = [...form.elements.round].find(r => r.value === store.get('round'));
  if (round) round.checked = true;
  // edhe viti akademik mbetet i zgjedhur
  const year = store.get('academicYear');
  if (year && [...form.academic_year.options].some(o => o.value === year)) form.academic_year.value = year;
  applyTemplateMode();
  applyDigitsMode();
}
for (const r of form.elements.round) r.addEventListener('change', () => store.set('round', r.value));
form.academic_year.addEventListener('change', () => store.set('academicYear', form.academic_year.value));
restorePasswordPrefs();

// ---------- Kamera ----------
// Celesi "Kamera: Ndezur / Fikur": kur eshte ndezur, kamera mbetet hapur per te gjithe studentet
// (mbahet mend ne kete shfletues), keshtu per cdo student shtypet vetem "Bëj foton".
const video = $('video'), img = $('photo'), box = document.querySelector('.cam-box');
const btnCam = $('btn-cam'), btnSnap = $('btn-snap'), btnRetake = $('btn-retake'), camSelect = $('cam-select');
let stream = null;
let photoData = '';
let camOn = store.get('cameraOn') === '1';
let camCount = 0;

function show(el, on) { el.hidden = !on; }

// Gjendja e butonave dhe e kutise sipas: kamera ndezur/fikur, a ka foto
function updateCamUI() {
  box.classList.toggle('streaming', !!stream);
  box.classList.toggle('live', !!stream && !photoData);
  // pamja e drejtperdrejte: sigurohu qe videoja luan (jo kuadri i fundit i ngrire)
  if (stream && !photoData && video.paused) video.play().catch(() => {});
  box.classList.toggle('has-photo', !!photoData);
  btnCam.textContent = camOn ? '📷 Kamera: Ndezur' : '📷 Kamera: Fikur';
  btnCam.classList.toggle('cam-on', camOn);
  btnCam.title = camOn ? 'Kliko për ta fikur kamerën' : 'Kliko për ta ndezur kamerën (mbetet e ndezur për të gjithë studentët)';
  show(btnSnap, !!stream && !photoData);
  show(btnRetake, !!photoData && camOn);
  show(camSelect, !!stream && !photoData && camCount > 1);
}

function stopCamera() {
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
  video.srcObject = null;
  updateCamUI();
}

const openStream = deviceId => navigator.mediaDevices.getUserMedia({
  // rezolucioni me i larte qe jep kamera (deri ne Full HD)
  video: { ...(deviceId ? { deviceId: { exact: deviceId } } : {}), width: { ideal: 1920 }, height: { ideal: 1080 } },
  audio: false,
});

async function startCamera(deviceId) {
  if (!navigator.mediaDevices?.getUserMedia) {
    message('err', 'Kamera nuk punon këtu. Hapeni programin me START (ose nga http://localhost/…), ose ngarkoni foton.');
    setCamOn(false);
    return;
  }
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
  // kamera e zgjedhur heren e fundit (p.sh. kamera USB)
  deviceId = deviceId || store.get('cameraId') || undefined;
  try {
    try {
      stream = await openStream(deviceId);
    } catch (e) {
      if (!deviceId) throw e;
      stream = await openStream(); // kamera e ruajtur nuk eshte e lidhur: perdor cilendo
    }
  } catch (e) {
    message('err', 'Kamera nuk u hap: ' + e.message + '. Kontrolloni që kamera është e lidhur dhe e lejuar në shfletues.');
    setCamOn(false);
    return;
  }
  if (!camOn) { stopCamera(); return; } // u fik nderkohe
  video.srcObject = stream;

  // Zgjedhja e kameres kur ka me shume se nje (lista rifreskohet, se kamera USB mund te lidhet me vone)
  const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
  camCount = cams.length;
  camSelect.innerHTML = '';
  cams.forEach((c, i) => camSelect.add(new Option(c.label || 'Kamera ' + (i + 1), c.deviceId)));
  const active = stream.getVideoTracks()[0]?.getSettings().deviceId;
  if (active) camSelect.value = active;
  updateCamUI();
}

function setCamOn(on) {
  camOn = on;
  store.set('cameraOn', on ? '1' : '0');
  if (on && !stream) startCamera();
  if (!on) stopCamera();
  updateCamUI();
}

camSelect.addEventListener('change', () => {
  store.set('cameraId', camSelect.value);
  startCamera(camSelect.value);
});

function setPhoto(dataUrl) {
  photoData = dataUrl;
  if (dataUrl) img.src = dataUrl; else img.removeAttribute('src'); // asnje gjurme e fotos se vjeter
  updateCamUI();
}

function snap() {
  // Fotoja ruhet e plote, ashtu si e sheh kamera: pa prerje dhe pa zmadhim, me rezolucionin e kameres
  const vw = video.videoWidth, vh = video.videoHeight;
  if (!vw) return;
  const canvas = $('canvas');
  canvas.width = vw; canvas.height = vh;
  canvas.getContext('2d').drawImage(video, 0, 0, vw, vh);
  setPhoto(canvas.toDataURL('image/jpeg', 0.92)); // kamera mbetet e hapur per "Ribëj" dhe studentin tjeter
}

// Pas ruajtjes / pastrimit: kamera gati per studentin tjeter (nese eshte ndezur)
function cameraReadyForNext() {
  setPhoto('');
  // nese lidhja me kameren eshte nderprere (p.sh. kamera u shkeput), rihapet
  const alive = stream && stream.getVideoTracks().some(t => t.readyState === 'live');
  if (camOn && !alive) startCamera();
}

btnCam.addEventListener('click', () => setCamOn(!camOn));
btnSnap.addEventListener('click', snap);
btnRetake.addEventListener('click', cameraReadyForNext);

// Kur hapet faqja: nese kamera ishte ndezur, ndizet vete
updateCamUI();
if (camOn) startCamera();

$('file').addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    const im = new Image();
    im.onload = () => {
      // zvogelo foton qe te mos jete shume e rende
      const scale = Math.min(1, 1200 / Math.max(im.width, im.height));
      const canvas = $('canvas');
      canvas.width = Math.round(im.width * scale); canvas.height = Math.round(im.height * scale);
      canvas.getContext('2d').drawImage(im, 0, 0, canvas.width, canvas.height);
      setPhoto(canvas.toDataURL('image/jpeg', 0.9));
    };
    im.src = reader.result;
  };
  reader.readAsDataURL(file);
  e.target.value = '';
});

// Foto e vogel 90x120 per listen (rreth 4 KB), qe faqja te hapet shpejt edhe me qindra studente
function makeThumb(dataUrl) {
  return new Promise((resolve, reject) => {
    const im = new Image();
    im.onload = () => {
      const w = 90, h = 120, c = document.createElement('canvas');
      c.width = w; c.height = h;
      const scale = Math.max(w / im.width, h / im.height);
      const sw = w / scale, sh = h / scale;
      c.getContext('2d').drawImage(im, (im.width - sw) / 2, (im.height - sh) / 2, sw, sh, 0, 0, w, h);
      resolve(c.toDataURL('image/jpeg', 0.8));
    };
    im.onerror = reject;
    im.src = dataUrl;
  });
}

// ---------- Ruajtja ----------
// Mesazhi i suksesit zhduket vete pas 4 sekondash; gabimet mbeten derisa te rregullohen
let msgTimer = null;
function message(type, html) {
  const m = $('msg');
  clearTimeout(msgTimer);
  m.className = 'msg ' + type;
  m.innerHTML = html;
  m.hidden = false;
  if (type === 'ok') msgTimer = setTimeout(() => { m.hidden = true; }, 4000);
}
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

form.addEventListener('submit', async e => {
  e.preventDefault();
  const mode = e.submitter?.dataset.mode || 'save';
  if (!photoData) { message('err', 'Bëni foton e studentit (ose ngarkoni një foto) para se ta ruani.'); return; }

  const buttons = form.querySelectorAll('button');
  buttons.forEach(b => b.disabled = true);
  const fd = new FormData(form);
  fd.append('photo', photoData);
  fd.append('thumb', await makeThumb(photoData).catch(() => ''));
  let res;
  try {
    const r = await fetch('save.php', { method: 'POST', body: fd });
    res = await r.json();
  } catch (err) {
    res = { ok: false, error: 'Serveri nuk u përgjigj: ' + err.message };
  }
  buttons.forEach(b => b.disabled = false);

  if (!res.ok) {
    message('err', esc(res.error));
    return;
  }

  message('ok',
    `✔ <b>${esc(res.name)}</b> u regjistrua — <b>${esc(res.email)}</b> / <b>${esc(res.password)}</b>
     &nbsp; <a href="${esc(res.print_url)}" class="print-link">Printo</a>
     <span class="path">📁 ${esc(res.path)} → <b>${res.files.map(esc).join('</b> + <b>')}</b>${IS_LOCAL ? ` — <a href="${esc(res.open_url)}" target="hidden-frame">Hap dosjen</a>` : ''}</span>`);

  if (mode === 'print') printInPage(res.print_url);

  addRow(res);
  resetForm(false);
});

let resetting = false;
form.addEventListener('reset', () => { if (!resetting) setTimeout(() => resetForm(true)); });

function resetForm(clearMsg) {
  resetting = true;
  form.reset();
  resetting = false;
  restorePasswordPrefs();
  cameraReadyForNext();
  if (clearMsg) $('msg').hidden = true;
  updateCreds();
  first.focus();
}

// ---------- Lista ----------
// Numeruesi per raund: "Raundi 1: 45 Bachelor, 12 Master" (numerohet nga rreshtat e listes)
// Lista e viteve akademike per numeruesin: viti i parazgjedhur + vitet e studenteve te regjistruar
const yearSelect = $('count-year');
function buildYearOptions() {
  const years = new Set([DEFAULT_YEAR, ...[...$('rows').children].map(tr => tr.dataset.year).filter(Boolean)]);
  const keep = yearSelect.value || store.get('countYear') || DEFAULT_YEAR;
  yearSelect.innerHTML = '<option value="all">Të gjitha vitet</option>' +
    [...years].sort().reverse().map(y => `<option value="${esc(y)}">${esc(y)}</option>`).join('');
  yearSelect.value = [...yearSelect.options].some(o => o.value === keep) ? keep : DEFAULT_YEAR;
}
yearSelect.addEventListener('change', () => { store.set('countYear', yearSelect.value); renderCounts(); });

function renderCounts() {
  buildYearOptions();
  const year = yearSelect.value;
  const rows = [...$('rows').children].filter(tr => year === 'all' || tr.dataset.year === year);
  const count = (round, level) => rows.filter(tr =>
    (round === null || tr.dataset.round === round) && (level === null || tr.dataset.level === level)).length;
  const card = (title, round, extraClass = '') => `
    <div class="count-card ${extraClass}">
      <div class="count-title">${esc(title)}</div>
      ${LEVELS.map(lv => `<div class="count-line"><span>${esc(lv)}</span><b>${count(round, lv)}</b></div>`).join('')}
      <div class="count-line total"><span>Gjithsej</span><b>${count(round, null)}</b></div>
    </div>`;
  $('counts').innerHTML = ROUNDS.map(r => card(r, r)).join('') + card('Të gjitha raundet', null, 'all');
}
renderCounts();


// Nje rresht ne listen e studenteve (i njejte pas ruajtjes dhe pas ndryshimit)
function rowHtml(s) {
  return `
    <td><img class="thumb" src="${esc(s.photo_url)}" alt="" loading="lazy"></td>
    <td>${esc(s.name)}${s.notes ? `<div class="note" title="${esc(s.notes)}">📝 ${esc(s.notes)}</div>` : ''}</td>
    <td>${esc(s.email)}</td>
    <td><code>${esc(s.password)}</code></td>
    <td>${esc(s.round)}</td>
    <td>${esc(s.program)}</td>
    <td class="row-actions"><button type="button" class="edit" data-id="${esc(s.id)}" title="Ndrysho të dhënat e studentit">✏️ Ndrysho</button><a href="${esc(s.docx_url)}" class="word-link" data-id="${esc(s.id)}">Word</a><a href="${esc(s.print_url)}" class="print-link">Printo</a>${IS_LOCAL ? `<a href="${esc(s.open_url)}" target="hidden-frame">Dosja</a>` : ''}<button type="button" class="del" data-id="${esc(s.id)}" data-name="${esc(s.name)}" title="Fshi studentin, Word-in dhe foton">🗑 Fshi</button></td>`;
}

function fillRow(tr, s) {
  tr.dataset.id = s.id;
  tr.dataset.round = s.round;
  tr.dataset.level = s.level;
  tr.dataset.year = s.academic_year;
  tr.innerHTML = rowHtml(s);
}

function addRow(s) {
  const tr = document.createElement('tr');
  fillRow(tr, s);
  $('rows').prepend(tr);
  applyFilters();
  renderCounts();
}

// ---------- Ndryshimi i studentit ----------
const editModal = $('edit-modal'), editForm = $('edit-form');
let editOld = null; // emri para ndryshimit, per "↻ Email & fjalekalim sipas emrit"

function editMessage(text) {
  $('edit-msg').textContent = text;
  $('edit-msg').hidden = !text;
}

async function openEditModal(id) {
  editMessage('');
  let res;
  try {
    res = await (await fetch('update.php?s=' + encodeURIComponent(id))).json();
  } catch (err) {
    res = { ok: false, error: err.message };
  }
  if (!res.ok) { message('err', esc(res.error || 'Studenti nuk u gjet.')); return; }
  const s = res.student;
  editOld = { first: s.first_name, last: s.last_name };
  for (const k of ['id', 'first_name', 'last_name', 'round', 'academic_year', 'program', 'phone', 'email', 'password', 'notes']) {
    if (editForm.elements[k]) editForm.elements[k].value = s[k] ?? '';
  }
  // fushat shtese te administratorit: custom[ID]
  for (const el of editForm.querySelectorAll('[name^="custom["]')) {
    const fid = el.name.slice(7, -1);
    el.value = (s.custom && s.custom[fid]) ?? '';
  }
  // raundi / programi i vjeter qe eshte hequr nga lista: shtohet perkohesisht qe te mos humbase
  for (const k of ['round', 'program']) {
    if (s[k] && editForm.elements[k].value !== s[k]) editForm.elements[k].add(new Option(s[k] + ' (jo aktiv)', s[k], true, true));
  }
  // vitet / raundet e vjetra qe nuk jane me ne liste
  if (s.academic_year && editForm.academic_year.value !== s.academic_year) editForm.academic_year.add(new Option(s.academic_year, s.academic_year, true, true));
  $('edit-title').textContent = '✏️ Ndrysho: ' + s.first_name + ' ' + s.last_name;
  editModal.hidden = false;
  document.body.classList.add('modal-open');
  editForm.first_name.focus();
}

function closeEditModal() {
  editModal.hidden = true;
  document.body.classList.remove('modal-open');
}

// Ndryshon emrin brenda fjalekalimit duke ruajtur shkronjat e medha/vogla: "Anaa.1234" -> "Ana.1234"
function swapName(text, oldPart, newPart) {
  if (!oldPart || !newPart) return text;
  return text.replace(new RegExp(oldPart, 'i'), m =>
    m === m.toUpperCase() ? newPart.toUpperCase() : m[0] === m[0].toUpperCase() ? ucfirst(newPart) : newPart);
}

$('edit-recalc').addEventListener('click', () => {
  const f = emailPart(editForm.first_name.value), l = emailPart(editForm.last_name.value);
  if (!f || !l) { editMessage('Shkruani emrin dhe mbiemrin me shkronja latine.'); return; }
  editForm.email.value = f + '.' + l + '@' + EMAIL_DOMAIN;
  let pass = editForm.password.value;
  pass = swapName(pass, emailPart(editOld.first), f);
  pass = swapName(pass, emailPart(editOld.last), l);
  editForm.password.value = pass;
  editMessage('');
});

editForm.addEventListener('submit', async e => {
  e.preventDefault();
  editMessage('');
  $('edit-save').disabled = true;
  let res;
  try {
    res = await (await fetch('update.php', { method: 'POST', body: new FormData(editForm) })).json();
  } catch (err) {
    res = { ok: false, error: 'Serveri nuk u përgjigj: ' + err.message };
  }
  $('edit-save').disabled = false;
  if (!res.ok) { editMessage(res.error); return; }
  const tr = [...$('rows').children].find(r => r.dataset.id === res.old_id);
  if (tr) fillRow(tr, res.student); else addRow(res.student);
  applyFilters();
  renderCounts();
  closeEditModal();
  message('ok', `✔ Ndryshimet u ruajtën për <b>${esc(res.student.name)}</b> — letra Word u krijua përsëri.`);
});

$('edit-close').addEventListener('click', closeEditModal);
$('edit-cancel').addEventListener('click', closeEditModal);
editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !editModal.hidden) closeEditModal(); });
$('rows').addEventListener('click', e => {
  const btn = e.target.closest('button.edit');
  if (btn) openEditModal(btn.dataset.id);
});

// Filtrimi i listes ne kohe reale: kerkimi + raundi + Bachelor/Master (te njejtat zgjedhje si eksporti)
const filterRound = document.querySelector('.export [name=round]');
const filterLevel = document.querySelector('.export [name=level]');
function applyFilters() {
  const q = toAscii($('search').value).toLowerCase().trim();
  const rows = [...$('rows').children];
  let shown = 0;
  for (const tr of rows) {
    tr.hidden = (q && !toAscii(tr.textContent).toLowerCase().includes(q))
      || (filterRound.value && tr.dataset.round !== filterRound.value)
      || (filterLevel.value && tr.dataset.level !== filterLevel.value);
    if (!tr.hidden) shown++;
  }
  const filtered = shown !== rows.length;
  $('count').textContent = filtered ? `${shown} nga ${rows.length}` : rows.length;
  $('empty').textContent = rows.length ? 'Asnjë student për këtë zgjedhje.' : 'Ende nuk ka studentë të regjistruar.';
  $('empty').hidden = shown > 0;
}
$('search').addEventListener('input', applyFilters);
filterRound.addEventListener('change', applyFilters);
filterLevel.addEventListener('change', applyFilters);
applyFilters();

// ---------- Fshirja ----------
$('rows').addEventListener('click', async e => {
  const btn = e.target.closest('button.del');
  if (!btn) return;
  if (!confirm(`A jeni i sigurt që doni të fshini studentin "${btn.dataset.name}"?\n\nDo të fshihen edhe dokumenti Word dhe fotoja.`)) return;
  btn.disabled = true;
  const fd = new FormData();
  fd.append('id', btn.dataset.id);
  let res;
  try {
    res = await (await fetch('delete.php', { method: 'POST', body: fd })).json();
  } catch (err) {
    res = { ok: false, error: 'Serveri nuk u përgjigj: ' + err.message };
  }
  if (!res.ok) {
    btn.disabled = false;
    alert(res.error);
    return;
  }
  btn.closest('tr').remove();
  applyFilters();
  renderCounts();
});

// ---------- Printimi ne te njejten faqe (pa skede te re) ----------
// Letra ngarkohet ne nje kornize te padukshme dhe hapet direkt dritarja e printimit.
// Letra vendoset ne #print-area dhe printohet me madhesine e vertete A4 (pa u zvogeluar)
async function printInPage(url) {
  let html;
  try {
    html = await (await fetch(url)).text();
  } catch (err) {
    message('err', 'Letra nuk u hap për printim: ' + esc(err.message));
    return;
  }
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const page = doc.querySelector('.page');
  if (!page) { message('err', 'Letra nuk u gjet për printim.'); return; }
  $('print-area').innerHTML = page.innerHTML;
  // emri i studentit si titull, qe edhe "Save as PDF" ta marre emrin e tij
  const oldTitle = document.title;
  document.title = doc.title || oldTitle;
  window.addEventListener('afterprint', () => { document.title = oldTitle; }, { once: true });
  window.print();
}

document.addEventListener('click', e => {
  const link = e.target.closest('a.print-link');
  if (!link) return;
  e.preventDefault();
  printInPage(link.href);
});

// ---------- Letra Word ne dritare pop-up (pa shkarkim) ----------
const docModal = $('doc-modal');
let docCurrent = null; // { id, docx, print }

async function openDocModal(link) {
  const id = link.dataset.id;
  const printUrl = 'print.php?s=' + encodeURIComponent(id);
  docCurrent = { id, docx: link.href, print: printUrl };
  $('doc-title').textContent = 'Duke hapur…';
  $('doc-paper').innerHTML = '';
  docModal.hidden = false;
  document.body.classList.add('modal-open');
  try {
    const doc = new DOMParser().parseFromString(await (await fetch(printUrl)).text(), 'text/html');
    $('doc-title').textContent = doc.title || id;
    $('doc-paper').innerHTML = doc.querySelector('.page')?.innerHTML || 'Letra nuk u gjet.';
  } catch (err) {
    $('doc-paper').textContent = 'Letra nuk u hap: ' + err.message;
  }
  $('doc-open').focus();
}

function closeDocModal() {
  docModal.hidden = true;
  document.body.classList.remove('modal-open');
  docCurrent = null;
}

$('doc-open').addEventListener('click', async () => {
  if (!docCurrent) return;
  if (!IS_LOCAL) { location.href = docCurrent.docx; return; } // nga kompjuter tjeter: shkarkim
  const btn = $('doc-open');
  btn.disabled = true;
  try {
    const res = await (await fetch('open_doc.php?s=' + encodeURIComponent(docCurrent.id))).json();
    if (!res.ok) throw new Error(res.error);
    btn.textContent = '✔ U hap në Word';
    setTimeout(() => { btn.textContent = '📄 Hap në Word'; }, 2500);
  } catch (err) {
    alert('Dokumenti nuk u hap në Word: ' + err.message);
  }
  btn.disabled = false;
});
$('doc-print').addEventListener('click', () => { if (docCurrent) printInPage(docCurrent.print); });
$('doc-close').addEventListener('click', closeDocModal);
docModal.addEventListener('click', e => { if (e.target === docModal) closeDocModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !docModal.hidden) closeDocModal(); });

document.addEventListener('click', e => {
  const link = e.target.closest('a.word-link');
  if (!link) return;
  e.preventDefault();
  openDocModal(link);
});

// ---------- Shkarkimi i fotove ne ZIP: dritare ku zgjidhen viti, raundet dhe nivelet ----------
const zipModal = $('zip-modal'), zipForm = $('zip-form');
let zipCheckTimer = null;

function zipParams() {
  return new URLSearchParams(new FormData(zipForm));
}

// Parapamja: fotot dhe emrat qe do te futen ne ZIP (rifreskohet sa here ndryshon nje kuti).
// Studentet e hequr me kuti (zipSkip) mbahen mend edhe kur ndryshon raundi / niveli.
const zipSkip = new Set();
let zipList = [];

function updateZipSelectedCount() {
  const chosen = zipList.filter(s => !zipSkip.has(s.id)).length;
  $('zip-count').textContent = zipList.length
    ? `${chosen} nga ${zipList.length} foto do të shkarkohen`
    : 'Nuk ka foto për këtë zgjedhje.';
  $('zip-go').disabled = !chosen;
}

function renderZipGrid() {
  $('zip-grid').innerHTML = zipList.map(s => `
    <label class="zip-item${zipSkip.has(s.id) ? ' off' : ''}" title="${esc(s.name)}">
      <input type="checkbox" data-id="${esc(s.id)}" ${zipSkip.has(s.id) ? '' : 'checked'}>
      <img src="${esc(s.thumb)}" alt="" loading="lazy">
      <span class="zip-name">${esc(s.name)}</span>
      <span class="zip-info">${esc(s.info)}</span>
    </label>`).join('');
  updateZipSelectedCount();
}

async function updateZipCount() {
  const params = zipParams();
  const out = $('zip-count'), go = $('zip-go');
  if (!params.getAll('rounds[]').length || !params.getAll('levels[]').length) {
    zipList = []; $('zip-grid').innerHTML = '';
    out.textContent = 'Zgjidhni të paktën një raund dhe një nivel.';
    go.disabled = true;
    return;
  }
  out.textContent = 'Duke ngarkuar…';
  try {
    const res = await (await fetch('photos_zip.php?check=1&' + params)).json();
    zipList = res.students || [];
    renderZipGrid();
  } catch (err) {
    out.textContent = 'Gabim: ' + err.message;
    go.disabled = true;
  }
}

$('zip-grid').addEventListener('change', e => {
  const cb = e.target.closest('input[data-id]');
  if (!cb) return;
  e.stopPropagation(); // nuk rifreskon listen, vetem numrin
  cb.checked ? zipSkip.delete(cb.dataset.id) : zipSkip.add(cb.dataset.id);
  cb.closest('.zip-item').classList.toggle('off', !cb.checked);
  updateZipSelectedCount();
});
$('zip-all').addEventListener('click', () => { zipList.forEach(s => zipSkip.delete(s.id)); renderZipGrid(); });
$('zip-none').addEventListener('click', () => { zipList.forEach(s => zipSkip.add(s.id)); renderZipGrid(); });

function openZipModal() {
  zipModal.hidden = false;
  document.body.classList.add('modal-open');
  updateZipCount();
}
function closeZipModal() {
  zipModal.hidden = true;
  document.body.classList.remove('modal-open');
}

$('btn-photos-zip').addEventListener('click', openZipModal);
$('zip-close').addEventListener('click', closeZipModal);
$('zip-cancel').addEventListener('click', closeZipModal);
zipModal.addEventListener('click', e => { if (e.target === zipModal) closeZipModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !zipModal.hidden) closeZipModal(); });
zipForm.addEventListener('change', () => { clearTimeout(zipCheckTimer); zipCheckTimer = setTimeout(updateZipCount, 150); });

zipForm.addEventListener('submit', e => {
  e.preventDefault();
  const f = document.createElement('form');
  f.method = 'post'; f.action = 'photos_zip.php'; f.hidden = true;
  const add = (name, value) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value; f.appendChild(i); };
  for (const [k, v] of zipParams()) add(k, v);
  zipList.filter(s => zipSkip.has(s.id)).forEach(s => add('skip[]', s.id));
  document.body.appendChild(f); f.submit(); f.remove();
  closeZipModal();
  message('ok', '⬇ Fotot po shkarkohen… (shikoni shkarkimet e shfletuesit)');
});
