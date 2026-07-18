(function(){
  var A = window.ALPHA || {};
  var letters = A.letters || [];
  var variants = A.variants || {};
  var rtl = A.dir === 'rtl';
  var root = document.getElementById('alpha');
  var btn = document.getElementById('toggleAll');

  function chunk(arr, n){ var out = []; for (var i = 0; i < arr.length; i += n) out.push(arr.slice(i, i + n)); return out; }

  // Mesure la police réellement rendue et cale les lignes (base + haut) sur les vraies lettres.
  function measureFont(){
    try {
      var probe = document.createElement('span'); probe.className = 'glyph';
      probe.style.position = 'absolute'; probe.style.visibility = 'hidden'; probe.style.left = '-9999px';
      probe.textContent = rtl ? 'ח' : 'H';
      root.appendChild(probe);
      var cs = getComputedStyle(probe);
      var F = parseFloat(cs.fontSize) || 40;
      var ctx = document.createElement('canvas').getContext('2d');
      ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + F + 'px ' + cs.fontFamily;
      ctx.textBaseline = 'alphabetic';
      var m = ctx.measureText(rtl ? 'ח' : 'H');
      root.removeChild(probe);
      var cap = m.actualBoundingBoxAscent, asc = m.fontBoundingBoxAscent, desc = m.fontBoundingBoxDescent;
      if (!cap || !asc || !desc) return;
      var capR = cap / F, ascR = asc / F, descR = desc / F;
      var G = 0.26;                                   // marge sous la ligne de base (jambages/cédille)
      var baseYr = G + (1 - ascR + descR) / 2;         // ligne de base
      var alpha = document.getElementById('alpha');
      alpha.style.setProperty('--glyphBottom', G.toFixed(4));
      alpha.style.setProperty('--baseYr', baseYr.toFixed(4));
      alpha.style.setProperty('--capHr', capR.toFixed(4));
    } catch (e) {}
  }
  measureFont();

  // Mesure la ligne de base de chaque police d'écriture (script / cursive / manuscrit)
  // et la cale sur celle des capitales via --shift-<style> (les lignes, elles, ne bougent pas).
  function styleShifts(){
    if (!(A.styles || []).length || !document.fonts || !document.fonts.load) return;
    try {
      function probe(cls, txt){
        var s = document.createElement('span'); s.className = cls;
        s.style.cssText = 'position:absolute;visibility:hidden;left:-9999px;display:block;';
        s.textContent = txt; root.appendChild(s); return s;
      }
      var upTxt = rtl ? 'ח' : 'H', loTxt = rtl ? 'א' : 'a';
      var upEl = probe('glyph', upTxt);
      var items = A.styles.map(function(st){ return { key: st.key, el: probe('low style-' + st.key, loTxt) }; });
      var loads = items.map(function(it){
        var cs = getComputedStyle(it.el);
        return document.fonts.load(cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily).catch(function(){});
      });
      Promise.all(loads).then(function(){
        var ctx = document.createElement('canvas').getContext('2d');
        function baseR(el, txt){
          // hauteur de la ligne de base au-dessus du bas de la boîte (line-height:1), en ratio de la police
          var cs = getComputedStyle(el);
          var f = parseFloat(cs.fontSize) || 40;
          ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + f + 'px ' + cs.fontFamily;
          var m = ctx.measureText(txt);
          if (!m.fontBoundingBoxAscent || !m.fontBoundingBoxDescent) return null;
          return (1 - m.fontBoundingBoxAscent / f + m.fontBoundingBoxDescent / f) / 2;
        }
        var bUp = baseR(upEl, upTxt);
        items.forEach(function(it){
          var b = baseR(it.el, loTxt);
          if (bUp != null && b != null) root.style.setProperty('--shift-' + it.key, (b - bUp).toFixed(4));
          root.removeChild(it.el);
        });
        root.removeChild(upEl);
      });
    } catch (e) {}
  }
  styleShifts();

  var rowObjs = [];

  chunk(letters, 4).forEach(function(group){
    var row = document.createElement('div'); row.className = 'row';
    var cells = document.createElement('div'); cells.className = 'cells'; if (rtl) cells.setAttribute('dir', 'rtl');
    var sec = document.createElement('div'); sec.className = 'sec'; if (rtl) sec.setAttribute('dir', 'rtl');
    var inner = document.createElement('div'); inner.className = 'sec-inner'; sec.appendChild(inner);

    var obj = { group: group, sec: sec, inner: inner, active: [], cells: {} };

    group.forEach(function(ch, idx){
      var d = document.createElement('div'); d.className = 'letter';
      var gl = document.createElement('span'); gl.className = 'glyphs';
      var up = document.createElement('span'); up.className = 'glyph'; up.textContent = ch; gl.appendChild(up);
      (A.styles || []).forEach(function(st){
        var f = A.forms && A.forms[st.key] && A.forms[st.key][ch];
        if (f){ var lo = document.createElement('span'); lo.className = 'low style-' + st.key; lo.textContent = f; gl.appendChild(lo); }
      });
      d.appendChild(gl);
      if (variants[ch] && variants[ch].length){
        d.classList.add('has-variants');
        var m = document.createElement('span'); m.className = 'mark'; m.textContent = '▾'; d.appendChild(m);
        d.addEventListener('click', function(){ toggle(obj, ch, idx, d); });
      }
      obj.cells[idx] = d;
      cells.appendChild(d);
    });

    row.appendChild(cells); row.appendChild(sec);
    root.appendChild(row);
    rowObjs.push(obj);
  });

  function vcell(ch, cls){
    var c = document.createElement('span'); c.className = 'vcell';
    var s = document.createElement('span'); s.className = 'vg ' + cls; s.textContent = ch; c.appendChild(s);
    return c;
  }
  function renderSec(obj){
    obj.inner.innerHTML = '';
    obj.active.sort(function(a, b){ return a.idx - b.idx; });
    obj.active.forEach(function(it){
      var g = document.createElement('span'); g.className = 'vgroup';
      g.appendChild(vcell(it.ch, 'vbase'));
      variants[it.ch].forEach(function(v){
        var isObj = (v && typeof v === 'object');
        var val = isObj ? v.v : v;
        var sofit = isObj && v.sofit;
        g.appendChild(vcell(val, 'vletter' + (sofit ? ' sofit' : '')));
      });
      obj.inner.appendChild(g);
    });
    obj.sec.classList.toggle('show', obj.active.length > 0);
  }

  function toggle(obj, ch, idx, d){
    var i = -1;
    for (var k = 0; k < obj.active.length; k++) if (obj.active[k].ch === ch) { i = k; break; }
    if (i >= 0){ obj.active.splice(i, 1); d.classList.remove('active'); }
    else { obj.active.push({ ch: ch, idx: idx }); d.classList.add('active'); }
    renderSec(obj);
  }

  // barre de filtres (pastilles à bascule)
  if (A.styles && A.styles.length){
    var bar = document.createElement('div'); bar.className = 'filters';
    A.styles.forEach(function(st){
      var b = document.createElement('button'); b.type = 'button'; b.className = 'chip'; b.setAttribute('aria-pressed', 'false');
      b.appendChild(document.createTextNode(st.label));
      if (st.he){ var h = document.createElement('span'); h.className = 'he'; h.setAttribute('dir', 'rtl'); h.textContent = ' ' + st.he; b.appendChild(h); }
      b.addEventListener('click', function(){ var on = root.classList.toggle('on-' + st.key); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      bar.appendChild(b);
    });
    root.parentNode.insertBefore(bar, root);
  }

  var allOpen = false;
  if (btn){
    btn.addEventListener('click', function(){
      allOpen = !allOpen;
      rowObjs.forEach(function(obj){
        obj.active = [];
        obj.group.forEach(function(ch, idx){
          var d = obj.cells[idx];
          if (variants[ch] && variants[ch].length){
            if (allOpen){ obj.active.push({ ch: ch, idx: idx }); d.classList.add('active'); }
            else { d.classList.remove('active'); }
          }
        });
        renderSec(obj);
      });
      btn.textContent = allOpen ? 'Masquer tout' : 'Version complète';
    });
  }
})();
