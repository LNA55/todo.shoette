/* Coffre santé (vault) — front sur-mesure (list = sante).
   Lit l'API partagée /tasks/api.php (doc.list / doc.update / lab.list) et les images via media.php. */
(function () {
  'use strict';

  var LIST  = window.VAULT_LIST || 'sante';
  var API   = '/tasks/api.php';
  var MEDIA = '/tasks/media.php';

  // Vont dans le tableau « ordonnances & prescriptions » : tout ce qui est prescrit/ordonné
  // (médicament, appareillage, demande d'analyses). Les « resultat » sont les résultats → galerie + tableau d'analyses.
  var PRESC_TYPES  = ['ordonnance', 'prescription', 'appareillage', 'demande', 'analyses'];
  var TYPE_LABELS  = {
    ordonnance: 'Ordonnance', prescription: 'Prescription', appareillage: 'Appareillage',
    demande: 'Demande d’analyse', analyses: 'Demande d’analyse',
    resultat: 'Résultats', analyse: 'Analyse',
    'compte-rendu': 'Compte-rendu', autre: 'Autre'
  };
  var STATUS_LABELS = { todo: 'To Do', in_progress: 'In progress', done: 'Done' };
  var MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

  function $(sel, root) { return (root || document).querySelector(sel); }
  function el(tag, cls, txt) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (txt != null) { e.textContent = txt; }
    return e;
  }

  function api(action, opts) {
    opts = opts || {};
    var url = API + '?action=' + encodeURIComponent(action) + '&list=' + encodeURIComponent(LIST);
    var init = { method: opts.body ? 'POST' : 'GET', headers: {} };
    if (opts.body) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }
    return fetch(url, init).then(function (r) {
      return r.text().then(function (t) {
        var j = {};
        try { j = t ? JSON.parse(t) : {}; } catch (e) { /* non-JSON */ }
        if (!r.ok) { throw new Error(j.error || ('HTTP ' + r.status)); }
        return j;
      });
    });
  }

  var toastTimer;
  function toast(msg) {
    var t = $('#toast'); if (!t) { return; }
    t.textContent = msg; t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.hidden = true; }, 1800);
  }
  function debounce(fn, ms) {
    var t;
    return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
  }

  function parseDate(s) {
    if (!s) { return null; }
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
    return m ? { y: +m[1], mo: +m[2], d: +m[3] } : null;
  }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function fmtLong(s)  { var p = parseDate(s); return p ? (p.d + ' ' + MONTHS[p.mo - 1] + ' ' + p.y) : '—'; }
  function fmtShort(s) { var p = parseDate(s); return p ? (pad(p.d) + '/' + pad(p.mo) + '/' + String(p.y).slice(2)) : '—'; }
  // En-têtes de colonnes du tableau d'analyses : date complète JJ/MM/AAAA quand on l'a.
  // Certaines colonnes n'ont qu'une année connue → libellé dédié (pas de faux jour/mois).
  var DATE_LABELS = { '2001-01-01': '2001' };
  function fmtCol(s)   { if (DATE_LABELS[s]) { return DATE_LABELS[s]; } var p = parseDate(s); return p ? (pad(p.d) + '/' + pad(p.mo) + '/' + p.y) : (s || '—'); }
  function typeLabel(t) { return TYPE_LABELS[(t || '').toLowerCase()] || ''; }

  /* ---------- bloc 1 : prescriptions ---------- */
  function statusSelect(doc) {
    var cur = doc.status || 'todo';
    var sel = el('select', 'status-select s-' + cur);
    sel.setAttribute('aria-label', 'Statut');
    ['todo', 'in_progress', 'done'].forEach(function (s) {
      var o = el('option', null, STATUS_LABELS[s]); o.value = s;
      if (cur === s) { o.selected = true; }
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      sel.className = 'status-select s-' + sel.value;
      api('doc.update', { body: { id: doc.id, status: sel.value } })
        .then(function () { doc.status = sel.value; toast('Statut enregistré'); })
        .catch(function (e) { toast('Erreur : ' + e.message); });
    });
    return sel;
  }

  function renderPrescriptions(docs) {
    var wrap = $('#rx-wrap'); wrap.innerHTML = '';
    var rx = docs.filter(function (d) { return PRESC_TYPES.indexOf((d.doc_type || '').toLowerCase()) !== -1; });
    if (!rx.length) {
      wrap.appendChild(el('div', 'empty', 'Aucune ordonnance enregistrée pour l’instant.'));
      return;
    }
    var head = el('div', 'rx-head');
    ['Date', 'Nom', 'Statut', 'Commentaire'].forEach(function (t) { head.appendChild(el('span', null, t)); });
    wrap.appendChild(head);

    rx.forEach(function (d) {
      var row = el('div', 'rx-row');
      row.appendChild(el('div', 'rx-date', fmtLong(d.emission_date)));
      row.appendChild(el('div', 'rx-name', d.title || '(sans nom)'));

      var stCell = el('div');
      stCell.appendChild(statusSelect(d));
      row.appendChild(stCell);

      var input = el('input', 'rx-comment');
      input.type = 'text';
      input.placeholder = 'Commentaire…';
      input.value = d.user_comment || '';
      var save = debounce(function () {
        api('doc.update', { body: { id: d.id, user_comment: input.value } })
          .then(function () { toast('Commentaire enregistré'); })
          .catch(function (e) { toast('Erreur : ' + e.message); });
      }, 700);
      input.addEventListener('input', save);
      row.appendChild(input);

      wrap.appendChild(row);
    });
  }

  /* ---------- bloc 2 : galerie + visionneuse ---------- */
  function renderGallery(docs) {
    var wrap = $('#thumbs'); wrap.innerHTML = '';
    if (!docs.length) { wrap.appendChild(el('div', 'empty', 'Aucun document.')); return; }
    docs.forEach(function (d) {
      var b = el('button', 'thumb'); b.type = 'button';
      var box = el('div', 'img');
      var im = el('img'); im.loading = 'lazy'; im.alt = d.title || 'Document';
      im.src = MEDIA + '?list=' + encodeURIComponent(LIST) + '&id=' + d.id + '&thumb=1';
      im.addEventListener('error', function () { im.remove(); });
      box.appendChild(im); b.appendChild(box);

      var cap = el('span', 'cap', fmtShort(d.emission_date));
      var tp = typeLabel(d.doc_type);
      if (tp) { cap.appendChild(el('span', 'tp', tp)); }
      b.appendChild(cap);

      b.addEventListener('click', function () { openViewer(d, b); });
      wrap.appendChild(b);
    });
  }

  function openViewer(d, btn) {
    var prev = document.querySelector('.thumb.on'); if (prev) { prev.classList.remove('on'); }
    if (btn) { btn.classList.add('on'); }
    var v = $('#viewer'); v.hidden = false;
    var src = MEDIA + '?list=' + encodeURIComponent(LIST) + '&id=' + d.id;
    $('#viewer-image').src = src;
    $('#viewer-link').href = src;
    $('#viewer-name').textContent = d.title || 'Document';
    $('#viewer-date').textContent = fmtLong(d.emission_date);
    var tp = $('#viewer-type');
    var label = typeLabel(d.doc_type);
    tp.textContent = label;
    tp.className = label ? ('v-type pill t-' + (d.doc_type || 'other').toLowerCase()) : 'v-type';
    $('#viewer-translation').textContent = d.translation || '';
    v.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ---------- bloc 3 : analyses dans le temps ---------- */
  function renderLab(data) {
    var wrap = $('#lab-wrap'); wrap.innerHTML = '';
    var metrics = data.metrics || [];
    var dates = data.dates || [];
    if (!metrics.length) {
      wrap.appendChild(el('div', 'empty', 'Aucune analyse enregistrée pour l’instant.'));
      return;
    }
    var scroll = el('div', 'lab-scroll');
    var table = el('table', 'lab');

    var thead = el('thead'), htr = el('tr');
    htr.appendChild(el('th', null, 'Valeur'));
    htr.appendChild(el('th', null, 'Référence'));
    dates.forEach(function (dt) { htr.appendChild(el('th', null, fmtCol(dt))); });
    htr.appendChild(el('th', null, 'Tendance & dernière valeur'));
    thead.appendChild(htr); table.appendChild(thead);

    var tbody = el('tbody'), lastRubric = null;
    metrics.forEach(function (m) {
      var rub = m.rubric || '';
      if (rub !== lastRubric) {
        lastRubric = rub;
        if (rub) {
          var rtr = el('tr', 'rubric');
          var rtd = el('td', null, rub); rtd.colSpan = dates.length + 3;
          rtr.appendChild(rtd); tbody.appendChild(rtr);
        }
      }
      var tr = el('tr');
      tr.appendChild(el('td', 'val-name', m.name + (m.unit ? ' (' + m.unit + ')' : '')));
      tr.appendChild(el('td', (m.ref_source === 'claude' ? 'ref-mine' : 'ref-doc'), m.ref_text || '—'));
      var vals = m.values || {};
      dates.forEach(function (dt, i) {
        tr.appendChild(el('td', (i === dates.length - 1 ? 'v-last' : null), (vals[dt] != null ? vals[dt] : '·')));
      });
      tr.appendChild(el('td', 'trend', m.trend_note || ''));
      tbody.appendChild(tr);
    });
    table.appendChild(tbody); scroll.appendChild(table); wrap.appendChild(scroll);
  }

  /* ---------- bloc 4 : note d'ensemble ---------- */
  function renderNote(note) {
    var wrap = $('#note'); wrap.innerHTML = '';
    if (!note || !note.trim()) {
      wrap.appendChild(el('div', 'empty', 'Pas encore d’analyse d’ensemble.'));
      return;
    }
    note.split(/\n{2,}/).forEach(function (para) {
      var txt = para.trim(); if (!txt) { return; }
      wrap.appendChild(el('p', null, txt));
    });
  }

  /* ---------- chargement ---------- */
  function loadDocs() {
    return api('doc.list').then(function (j) {
      var docs = j.documents || [];
      renderPrescriptions(docs);
      renderGallery(docs);
    }).catch(function (e) {
      $('#rx-wrap').innerHTML = '';
      $('#rx-wrap').appendChild(el('div', 'empty', 'Erreur de chargement : ' + e.message));
    });
  }
  function loadLab() {
    return api('lab.list').then(function (j) {
      renderLab(j);
      renderNote(j.analysis_note);
    }).catch(function (e) {
      $('#lab-wrap').innerHTML = '';
      $('#lab-wrap').appendChild(el('div', 'empty', 'Erreur de chargement : ' + e.message));
      $('#note').innerHTML = '';
    });
  }

  loadDocs();
  loadLab();
})();
