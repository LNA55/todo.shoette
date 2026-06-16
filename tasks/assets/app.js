'use strict';

const API = '/tasks/api.php';
// Clé de la liste de cette page (définie par index.php via window.TODO_LIST). Défaut 'tasks'.
const LIST = (typeof window !== 'undefined' && window.TODO_LIST && /^[a-z0-9_-]+$/i.test(window.TODO_LIST))
  ? window.TODO_LIST : 'tasks';

/* ---------- état ---------- */
let STATE = { tasks: [], tags: [], task_tags: [], max_level: 6 };
let byId = new Map();          // id -> tâche (avec .children et .tagIds)
let view = loadView();         // préférences d'affichage (persistées, par liste)

function loadView() {
  try {
    const v = JSON.parse(localStorage.getItem('tasksView:' + LIST) || '{}');
    return { depth: v.depth || '6', filterTags: new Set(v.filterTags || []) };
  } catch (_) {
    return { depth: '6', filterTags: new Set() };
  }
}
function saveView() {
  localStorage.setItem('tasksView:' + LIST, JSON.stringify({
    depth: view.depth,
    filterTags: [...view.filterTags],
  }));
}

/* ---------- notes (texte repliable par item) ---------- */
const NOTE_COLORS = { black: '#111111', green: '#1a7f37', red: '#cf222e' };
let openNotes = loadOpenNotes();           // ids des notes dépliées (état client, persisté par liste)
function loadOpenNotes() {
  try { return new Set(JSON.parse(localStorage.getItem('notesOpen:' + LIST) || '[]')); }
  catch (_) { return new Set(); }
}
function saveOpenNotes() {
  localStorage.setItem('notesOpen:' + LIST, JSON.stringify([...openNotes]));
}

/* ---------- API ---------- */
async function api(action, data = null, method = 'POST') {
  const opts = { method };
  if (method === 'POST') {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(data || {});
  }
  const res = await fetch(`${API}?action=${encodeURIComponent(action)}&list=${encodeURIComponent(LIST)}`, opts);
  let json;
  try { json = await res.json(); } catch (_) { json = { error: 'Réponse invalide du serveur.' }; }
  if (!res.ok || json.error) throw new Error(json.error || `Erreur ${res.status}`);
  return json;
}

async function loadState() {
  STATE = await api('state', null, 'GET');
  buildIndex();
  render();
}

function buildIndex() {
  byId = new Map();
  for (const t of STATE.tasks) { t.children = []; t.tagIds = []; byId.set(t.id, t); }
  for (const t of STATE.tasks) {
    if (t.parent_id != null && byId.has(t.parent_id)) byId.get(t.parent_id).children.push(t);
  }
  for (const t of byId.values()) t.children.sort((a, b) => a.position - b.position || a.id - b.id);
  for (const link of STATE.task_tags) {
    const t = byId.get(link.task_id);
    if (t) t.tagIds.push(link.tag_id);
  }
}

const tagById = (id) => STATE.tags.find((t) => t.id === id);

function realRoot(t) {
  let cur = t;
  while (cur.parent_id != null && byId.has(cur.parent_id)) cur = byId.get(cur.parent_id);
  return cur;
}
function isHiddenExcluded(t) { return !!realRoot(t).hidden; }

/* ---------- utilitaires DOM ---------- */
const $ = (sel) => document.querySelector(sel);

function el(tag, props = {}, ...kids) {
  const e = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (k === 'class') e.className = v;
    else if (k === 'text') e.textContent = v;
    else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2), v);
    else if (v === true) e.setAttribute(k, '');
    else if (v !== false && v != null) e.setAttribute(k, v);
  }
  for (const kid of kids) if (kid != null) e.append(kid);
  return e;
}

/* ---------- rendu ---------- */
function computeShowSet() {
  // tâches qui portent au moins un tag filtré, + tous leurs ancêtres (pour le contexte)
  const show = new Set();
  const sel = view.filterTags;
  for (const t of STATE.tasks) {
    if (t.tagIds.some((id) => sel.has(id))) {
      let cur = t;
      while (cur) { show.add(cur.id); cur = cur.parent_id != null ? byId.get(cur.parent_id) : null; }
    }
  }
  return show;
}

function render() {
  renderTagFilter();
  renderHiddenMenu();

  const filterActive = view.filterTags.size > 0;
  const showSet = filterActive ? computeShowSet() : null;
  const depthLimit = view.depth === 'all' ? Infinity : parseInt(view.depth, 10);

  const renderable = (t, isDone) =>
    (!!t.done === isDone) && !isHiddenExcluded(t) && (!showSet || showSet.has(t.id));

  renderForest($('#active-list'), false, renderable, depthLimit);
  renderForest($('#done-list'), true, renderable, depthLimit);

  if (!$('#active-list').children.length) {
    $('#active-list').append(el('li', {
      class: 'empty-hint',
      text: filterActive ? 'Aucune tâche ne correspond au filtre.' : 'Aucune tâche. Ajoute la première ci-dessus 👆',
    }));
  }

  const hasDone = STATE.tasks.some((t) => t.done && renderable(t, true));
  $('#done-section').style.display = hasDone ? '' : 'none';
}

function displayParent(t, isDone, renderable) {
  let cur = t.parent_id != null ? byId.get(t.parent_id) : null;
  while (cur) {
    if (renderable(cur, isDone)) return cur;
    cur = cur.parent_id != null ? byId.get(cur.parent_id) : null;
  }
  return null;
}

function renderForest(container, isDone, renderable, depthLimit) {
  container.innerHTML = '';
  const childrenOf = new Map(); // id (ou 'root') -> [tâches]
  const add = (pid, t) => { const k = pid == null ? 'root' : pid; if (!childrenOf.has(k)) childrenOf.set(k, []); childrenOf.get(k).push(t); };
  for (const t of STATE.tasks) {
    if (!renderable(t, isDone)) continue;
    const dp = displayParent(t, isDone, renderable);
    add(dp ? dp.id : null, t);
  }
  for (const arr of childrenOf.values()) arr.sort((a, b) => a.position - b.position || a.id - b.id);
  for (const t of (childrenOf.get('root') || [])) {
    renderNode(container, t, 1, isDone, childrenOf, depthLimit);
  }
}

function countDescendants(t, childrenOf) {
  let n = 0;
  const stack = [...(childrenOf.get(t.id) || [])];
  while (stack.length) { const x = stack.pop(); n++; stack.push(...(childrenOf.get(x.id) || [])); }
  return n;
}

function actBtn(label, title, onclick) {
  return el('button', { class: 'act', type: 'button', title, text: label, onclick });
}

function renderNode(container, t, level, isDone, childrenOf, depthLimit) {
  const kids = childrenOf.get(t.id) || [];
  const hasKids = kids.length > 0;

  const li = el('li', { class: 'node', 'data-id': t.id });
  const row = el('div', { class: 'row' + (t.done ? ' is-done' : '') });
  row.style.paddingLeft = ((level - 1) * 22) + 'px';

  // triangle de repli
  if (hasKids) {
    row.append(el('button', {
      class: 'twisty', type: 'button', title: t.collapsed ? 'Déplier' : 'Replier',
      text: t.collapsed ? '▸' : '▾', onclick: () => toggleCollapse(t),
    }));
  } else {
    row.append(el('span', { class: 'twisty placeholder', text: '·' }));
  }

  // case à cocher
  const cb = el('input', { type: 'checkbox', class: 'check', title: 'Cocher / décocher' });
  cb.checked = !!t.done;
  cb.addEventListener('change', () => toggleDone(t, cb.checked));
  row.append(cb);

  // titre (clic pour éditer)
  const title = el('span', { class: 'title', text: t.title, title: 'Cliquer pour modifier' });
  title.addEventListener('click', () => editTitle(t, title));
  row.append(title);

  // tags
  const tagWrap = el('span', { class: 'row-tags' });
  for (const tid of t.tagIds) {
    const tag = tagById(tid);
    if (tag) tagWrap.append(el('span', { class: 'tag-chip', style: `background:${tag.color}`, text: tag.name }));
  }
  row.append(tagWrap);

  // actions
  const act = el('span', { class: 'actions' });
  if (!isDone) act.append(actBtn('＋', 'Ajouter une sous-tâche', () => addSubtask(t, li, level)));
  act.append(actBtn('🏷', 'Tags', (ev) => { ev.stopPropagation(); openTaskTagPop(t, ev.currentTarget); }));
  const noteBtn = actBtn('T', (t.note && String(t.note).trim()) ? 'Voir / éditer la note' : 'Ajouter une note', (ev) => { ev.stopPropagation(); toggleNote(t); });
  noteBtn.classList.add('note-btn');
  if (t.note && String(t.note).trim() !== '') {
    noteBtn.classList.add('has-note');
    noteBtn.style.color = NOTE_COLORS[t.note_color] || NOTE_COLORS.black;
  }
  row.append(noteBtn);
  if (!isDone) {
    act.append(actBtn('→', 'Imbriquer (sous la tâche du dessus)', () => simpleAction('task.indent', t)));
    act.append(actBtn('←', 'Désimbriquer', () => simpleAction('task.outdent', t)));
    act.append(actBtn('↑', 'Monter', () => simpleAction('task.moveUp', t)));
    act.append(actBtn('↓', 'Descendre', () => simpleAction('task.moveDown', t)));
    if (t.parent_id == null) act.append(actBtn('🙈', 'Masquer ce groupe', () => hideGroup(t)));
  }
  const del = actBtn('🗑', 'Supprimer', () => deleteTask(t));
  del.classList.add('danger');
  act.append(del);
  row.append(act);

  li.append(row);

  // note de texte (repliable)
  if (openNotes.has(t.id)) li.append(renderNotePanel(t, level));

  // enfants
  if (hasKids && !t.collapsed && level < depthLimit) {
    const ul = el('ul', { class: 'tree children' });
    for (const c of kids) renderNode(ul, c, level + 1, isDone, childrenOf, depthLimit);
    li.append(ul);
  } else if (hasKids) {
    row.append(el('span', { class: 'more', title: 'Sous-tâches masquées', text: '+' + countDescendants(t, childrenOf) }));
  }

  container.append(li);
}

/* ---------- actions sur les tâches ---------- */
async function simpleAction(action, t) {
  try { await api(action, { id: t.id }); await loadState(); }
  catch (e) { toast(e.message); }
}
async function toggleCollapse(t) {
  try { await api('task.collapse', { id: t.id, collapsed: !t.collapsed }); await loadState(); }
  catch (e) { toast(e.message); }
}
async function toggleDone(t, done) {
  try { await api('task.toggle', { id: t.id, done }); await loadState(); }
  catch (e) { toast(e.message); }
}
async function hideGroup(t) {
  try { await api('task.hide', { id: t.id, hidden: true }); await loadState(); }
  catch (e) { toast(e.message); }
}
async function deleteTask(t) {
  if (!confirm('Supprimer cette tâche et ses sous-tâches ?')) return;
  try { await api('task.delete', { id: t.id }); await loadState(); }
  catch (e) { toast(e.message); }
}

/* ---------- note de texte (repliable) ---------- */
function toggleNote(t) {
  if (openNotes.has(t.id)) openNotes.delete(t.id); else openNotes.add(t.id);
  saveOpenNotes();
  render();
  if (openNotes.has(t.id)) {
    const li = document.querySelector(`li.node[data-id="${t.id}"]`);
    const ta = li && li.querySelector(':scope > .note-panel .note-text');
    if (ta) ta.focus();
  }
}

function autoGrow(ta) {
  ta.style.height = 'auto';
  ta.style.height = Math.min(ta.scrollHeight, 400) + 'px';
}

function renderNotePanel(t, level) {
  const panel = el('div', { class: 'note-panel' });
  panel.style.marginLeft = ((level - 1) * 22 + 28) + 'px';

  const ta = el('textarea', { class: 'note-text', placeholder: "Note… (s'enregistre toute seule)" });
  ta.value = t.note || '';
  ta.style.color = NOTE_COLORS[t.note_color] || NOTE_COLORS.black;
  ta.addEventListener('input', () => autoGrow(ta));
  ta.addEventListener('blur', () => saveNote(t, ta.value));
  panel.append(ta);
  setTimeout(() => autoGrow(ta), 0);

  const colors = el('div', { class: 'note-colors' });
  for (const key of ['black', 'green', 'red']) {
    const sw = el('button', {
      class: 'note-swatch' + ((t.note_color || 'black') === key ? ' active' : ''),
      type: 'button', title: key, style: `background:${NOTE_COLORS[key]}`,
    });
    sw.addEventListener('click', () => setNoteColor(t, key, ta, colors));
    colors.append(sw);
  }
  panel.append(colors);
  return panel;
}

async function saveNote(t, value) {
  const v = (value || '').trim();
  if (v === (t.note || '')) return;
  try {
    await api('task.note', { id: t.id, note: v });
    t.note = v;
    const li = document.querySelector(`li.node[data-id="${t.id}"]`);
    const btn = li && li.querySelector(':scope > .row .note-btn');
    if (btn) {
      if (v) { btn.classList.add('has-note'); btn.style.color = NOTE_COLORS[t.note_color] || NOTE_COLORS.black; }
      else { btn.classList.remove('has-note'); btn.style.color = ''; }
    }
  } catch (e) { toast(e.message); }
}

async function setNoteColor(t, color, ta, colorsEl) {
  try {
    await api('task.note', { id: t.id, color });
    t.note_color = color;
    if (ta) ta.style.color = NOTE_COLORS[color];
    if (colorsEl) {
      const sw = colorsEl.querySelectorAll('.note-swatch');
      sw.forEach((b) => b.classList.remove('active'));
      const idx = ['black', 'green', 'red'].indexOf(color);
      if (sw[idx]) sw[idx].classList.add('active');
    }
    const li = document.querySelector(`li.node[data-id="${t.id}"]`);
    const btn = li && li.querySelector(':scope > .row .note-btn');
    if (btn && btn.classList.contains('has-note')) btn.style.color = NOTE_COLORS[color];
  } catch (e) { toast(e.message); }
}

function editTitle(t, span) {
  const input = el('input', { class: 'title-edit', type: 'text' });
  input.value = t.title;
  span.replaceWith(input);
  input.focus();
  input.select();
  let settled = false;
  const finish = async (save) => {
    if (settled) return;
    settled = true;
    const val = input.value.trim();
    if (save && val && val !== t.title) {
      try { await api('task.rename', { id: t.id, title: val }); await loadState(); return; }
      catch (e) { toast(e.message); }
    }
    await loadState(); // restaure l'affichage
  };
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); finish(true); }
    else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
  });
  input.addEventListener('blur', () => finish(true));
}

function addSubtask(t, li, level) {
  if (level >= STATE.max_level) { toast(`Profondeur maximale : ${STATE.max_level} niveaux.`); return; }
  let ul = li.querySelector(':scope > ul.children');
  if (!ul) { ul = el('ul', { class: 'tree children' }); li.append(ul); }
  const holder = el('li', { class: 'node' });
  const row = el('div', { class: 'row' });
  row.style.paddingLeft = (level * 22) + 'px';
  const input = el('input', { class: 'title-edit', type: 'text', placeholder: 'Sous-tâche…' });
  row.append(el('span', { class: 'twisty placeholder', text: '·' }), input);
  holder.append(row);
  ul.prepend(holder);
  input.focus();
  let settled = false;
  const finish = async (save) => {
    if (settled) return;
    settled = true;
    const val = input.value.trim();
    if (save && val) {
      try {
        await api('task.add', { parent_id: t.id, title: val });
        if (t.collapsed) await api('task.collapse', { id: t.id, collapsed: false });
        await loadState();
        return;
      } catch (e) { toast(e.message); }
    }
    holder.remove();
  };
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); finish(true); }
    else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
  });
  input.addEventListener('blur', () => finish(true));
}

/* ---------- tags sur une tâche (popover) ---------- */
function openTaskTagPop(t, anchor) {
  const pop = $('#task-tag-pop');
  pop.innerHTML = '';
  if (STATE.tags.length === 0) {
    pop.append(el('div', { class: 'pop-empty', text: 'Aucun tag.' }));
    pop.append(el('button', { class: 'linkbtn', type: 'button', text: 'En créer un', onclick: () => { pop.hidden = true; openTagManager(); } }));
  } else {
    for (const tag of STATE.tags) {
      const cb = el('input', { type: 'checkbox' });
      cb.checked = t.tagIds.includes(tag.id);
      cb.addEventListener('change', async () => {
        try { await api('tasktag.toggle', { task_id: t.id, tag_id: tag.id, on: cb.checked }); await loadState(); }
        catch (e) { toast(e.message); }
      });
      const swatch = el('span', { class: 'swatch', style: `background:${tag.color}` });
      pop.append(el('label', { class: 'pop-row' }, cb, swatch, el('span', { text: tag.name })));
    }
    pop.append(el('button', { class: 'linkbtn', type: 'button', text: 'Gérer les tags…', onclick: () => { pop.hidden = true; openTagManager(); } }));
  }
  positionPop(pop, anchor);
}

function positionPop(pop, anchor) {
  pop.hidden = false;
  const r = anchor.getBoundingClientRect();
  const w = pop.offsetWidth;
  pop.style.top = (r.bottom + 4) + 'px';
  pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
}

/* ---------- filtre par tags ---------- */
function renderTagFilter() {
  const wrap = $('#tag-filter');
  wrap.innerHTML = '';
  if (STATE.tags.length === 0) { wrap.append(el('span', { class: 'muted', text: '—' })); return; }
  for (const tag of STATE.tags) {
    const on = view.filterTags.has(tag.id);
    wrap.append(el('button', {
      class: 'filter-chip' + (on ? ' on' : ''), type: 'button',
      style: on ? `background:${tag.color};border-color:${tag.color}` : `border-color:${tag.color}`,
      text: tag.name,
      onclick: () => {
        if (on) view.filterTags.delete(tag.id); else view.filterTags.add(tag.id);
        saveView(); render();
      },
    }));
  }
  if (view.filterTags.size) {
    wrap.append(el('button', {
      class: 'linkbtn clear', type: 'button', text: 'tout', title: 'Effacer le filtre',
      onclick: () => { view.filterTags.clear(); saveView(); render(); },
    }));
  }
}

/* ---------- groupes masqués ---------- */
function renderHiddenMenu() {
  const hiddenRoots = STATE.tasks.filter((t) => t.parent_id == null && t.hidden);
  $('#hidden-count').textContent = hiddenRoots.length;
  $('#hidden-btn').parentElement.style.display = hiddenRoots.length ? '' : 'none';

  const pop = $('#hidden-pop');
  pop.innerHTML = '';
  pop.hidden = true;
  for (const t of hiddenRoots) {
    pop.append(el('div', { class: 'pop-row' },
      el('span', { class: 'ellipsis', text: t.title }),
      el('button', {
        class: 'linkbtn', type: 'button', text: 'afficher',
        onclick: async () => { try { await api('task.hide', { id: t.id, hidden: false }); await loadState(); } catch (e) { toast(e.message); } },
      }),
    ));
  }
  if (hiddenRoots.length) {
    pop.append(el('button', {
      class: 'linkbtn strong', type: 'button', text: 'Tout afficher',
      onclick: async () => { try { await api('task.showAllHidden'); await loadState(); } catch (e) { toast(e.message); } },
    }));
  }
}

/* ---------- gestionnaire de tags ---------- */
function openTagManager() {
  renderTagEditor();
  $('#tag-dialog').showModal();
}
function renderTagEditor() {
  const ul = $('#tag-editor');
  ul.innerHTML = '';
  if (!STATE.tags.length) ul.append(el('li', { class: 'muted', text: "Aucun tag pour l'instant." }));
  for (const tag of STATE.tags) {
    const color = el('input', { type: 'color', value: tag.color, title: 'Couleur' });
    color.addEventListener('change', () => updateTag(tag.id, { color: color.value }));
    const name = el('input', { type: 'text', value: tag.name, maxlength: 80 });
    name.addEventListener('change', () => { const v = name.value.trim(); if (v) updateTag(tag.id, { name: v }); });
    name.addEventListener('keydown', (e) => { if (e.key === 'Enter') name.blur(); });
    const del = el('button', {
      class: 'act danger', type: 'button', title: 'Supprimer le tag', text: '🗑',
      onclick: () => deleteTag(tag),
    });
    ul.append(el('li', { class: 'tag-edit-row' }, color, name, del));
  }
}
async function updateTag(id, fields) {
  try { await api('tag.update', { id, ...fields }); await loadState(); renderTagEditor(); }
  catch (e) { toast(e.message); }
}
async function deleteTag(tag) {
  if (!confirm(`Supprimer le tag « ${tag.name} » ? Il sera retiré de toutes les tâches.`)) return;
  try {
    view.filterTags.delete(tag.id); saveView();
    await api('tag.delete', { id: tag.id });
    await loadState(); renderTagEditor();
  } catch (e) { toast(e.message); }
}

/* ---------- toast ---------- */
let toastTimer = null;
function toast(msg) {
  const t = $('#toast');
  t.textContent = msg;
  t.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.hidden = true; }, 3500);
}

/* ---------- initialisation ---------- */
function wireStaticHandlers() {
  $('#add-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = $('#add-input');
    const val = input.value.trim();
    if (!val) return;
    try { await api('task.add', { title: val }); input.value = ''; await loadState(); input.focus(); }
    catch (err) { toast(err.message); }
  });

  $('#depth').value = view.depth;
  $('#depth').addEventListener('change', (e) => { view.depth = e.target.value; saveView(); render(); });

  $('#manage-tags').addEventListener('click', openTagManager);

  $('#tag-add-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = $('#new-tag-name').value.trim();
    const color = $('#new-tag-color').value;
    if (!name) return;
    try { await api('tag.add', { name, color }); $('#new-tag-name').value = ''; await loadState(); renderTagEditor(); }
    catch (err) { toast(err.message); }
  });

  $('#hidden-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    const p = $('#hidden-pop');
    if (p.hidden) positionPop(p, e.currentTarget); else p.hidden = true;
  });

  // fermer les popovers en cliquant ailleurs
  document.addEventListener('click', (e) => {
    for (const sel of ['#task-tag-pop', '#hidden-pop']) {
      const pop = $(sel);
      if (!pop || pop.hidden) continue;
      if (!pop.contains(e.target)) pop.hidden = true;
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  wireStaticHandlers();
  loadState().catch((e) => toast(e.message));
});
