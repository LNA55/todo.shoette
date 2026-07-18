(function(){
  var S=window.SLIDES||[], D=window.DECK||{};
  document.documentElement.style.setProperty('--accent', D.accent||'#7b5bff');
  var stage=document.getElementById('stage');
  var HEB=/[֐-׿]/;
  function esc(x){return (x==null?'':String(x)).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  function cell(tag,txt){var d=HEB.test(txt)?' dir="rtl"':'';return '<'+tag+d+'>'+esc(txt)+'</'+tag+'>';}
  function heBlock(h){return '<div class="hepair"><div class="he" dir="rtl">'+esc(h.he)+'</div>'+(h.phon?'<div class="phon">'+esc(h.phon)+'</div>':'')+'</div>';}
  var HERO='<svg class="hero" viewBox="0 0 240 272" xmlns="http://www.w3.org/2000/svg">'+
    '<ellipse cx="120" cy="250" rx="70" ry="10" fill="rgba(0,0,0,.25)"/>'+
    '<g class="stage"><rect x="66" y="232" width="108" height="30" rx="4" fill="#7a5a2e"/>'+
    '<rect x="58" y="223" width="124" height="12" rx="3" fill="#9c7a3f"/>'+
    '<text x="120" y="253" text-anchor="middle" font-size="16" fill="#fff" font-weight="800">1</text></g>'+
    '<g class="fig">'+
    '<rect x="108" y="196" width="10" height="36" rx="4" fill="#28324e"/>'+
    '<rect x="122" y="196" width="10" height="36" rx="4" fill="#28324e"/>'+
    '<rect x="100" y="150" width="40" height="54" rx="14" fill="#3a4d8f"/>'+
    '<rect x="92" y="154" width="10" height="42" rx="5" fill="#3a4d8f"/>'+
    '<circle cx="97" cy="198" r="6" fill="#f1c9a5"/>'+
    '<g class="rarm"><rect x="138" y="154" width="10" height="42" rx="5" fill="#3a4d8f"/><circle cx="143" cy="198" r="6" fill="#f1c9a5"/></g>'+
    '<circle cx="120" cy="128" r="22" fill="#f1c9a5"/>'+
    '<circle cx="112" cy="126" r="2.6" fill="#333"/><circle cx="128" cy="126" r="2.6" fill="#333"/>'+
    '<path d="M112 135 q8 7 16 0" stroke="#b06a4a" stroke-width="2.6" fill="none" stroke-linecap="round"/>'+
    '<g class="hat"><rect x="98" y="103" width="44" height="7" rx="3" fill="#20242e"/>'+
    '<rect x="106" y="80" width="28" height="26" rx="3" fill="#20242e"/>'+
    '<rect x="106" y="97" width="28" height="5" fill="#c0392b"/></g>'+
    '</g></svg>';
  function render(s){
    if(s.k==='title'){
      return '<div class="slide dark enter"><div class="title-emoji">'+(D.emoji||'📚')+'</div>'+
        '<h1 class="title-h1">'+esc(s.title||D.title||'')+'</h1>'+
        (s.sub?'<div class="title-sub">'+esc(s.sub)+'</div>':'')+'<div class="hint">›</div></div>';
    }
    if(s.k==='congrats'){
      return '<div class="slide dark enter congrats"><canvas class="fw"></canvas>'+HERO+
        '<h1 class="congrats-title" dir="rtl">כָּל הַכָּבוֹד!</h1>'+
        '<div class="congrats-sub">Bravo&nbsp;! · Well done!</div></div>';
    }
    if(s.k==='table'){
      var h='';
      if(s.title) h+='<div class="ttitle">'+esc(s.title)+'</div>';
      var t='<div class="twrap"><table class="vt"><thead><tr>';
      (s.headers||[]).forEach(function(c){t+=cell('th',c);});
      t+='</tr></thead><tbody>';
      (s.rows||[]).forEach(function(r){t+='<tr>'+r.map(function(c){return cell('td',c);}).join('')+'</tr>';});
      t+='</tbody></table></div>';
      h+=t;
      if(s.note) h+='<div class="tnote">'+esc(s.note)+'</div>';
      return '<div class="slide light enter">'+h+'</div>';
    }
    var h='';
    if(s.cat) h+='<div class="cat">'+esc(s.cat)+'</div>';
    if(s.main) h+='<div class="term">'+esc(s.main)+'</div>';
    (s.gloss||[]).forEach(function(g){h+='<div class="gloss">'+esc(g)+'</div>';});
    (s.he||[]).forEach(function(x){h+=heBlock(x);});
    if(D.audio && s.aud){ h+='<button class="say-btn" type="button" aria-label="Écouter">🔊</button>'; }
    (s.notes||[]).forEach(function(n){h+='<div class="note">'+esc(n)+'</div>';});
    if(s.ex&&s.ex.length){
      h+='<div class="examples">'+s.ex.map(function(e){return '<div class="ex">'+
        '<span class="exlabel">'+esc(e.label)+'</span>'+
        (e.fr?'<div class="exfr">'+esc(e.fr)+'</div>':'')+
        (e.en?'<div class="exen">'+esc(e.en)+'</div>':'')+
        (e.he?'<div class="exhe" dir="rtl">'+esc(e.he)+'</div>':'')+
        (e.phon?'<div class="exphon">'+esc(e.phon)+'</div>':'')+'</div>';}).join('')+'</div>';
    }
    return '<div class="slide light enter">'+h+'</div>';
  }
  // feu d'artifice pour la diapo finale
  var fwRAF=null,fwCanvas=null;
  function stopFW(){if(fwRAF){cancelAnimationFrame(fwRAF);fwRAF=null;}fwCanvas=null;}
  function startFW(canvas){
    stopFW();fwCanvas=canvas;var ctx=canvas.getContext('2d');var dpr=Math.min(window.devicePixelRatio||1,2);
    function rs(){canvas.width=Math.floor(canvas.clientWidth*dpr);canvas.height=Math.floor(canvas.clientHeight*dpr);}
    rs();window.addEventListener('resize',rs);
    var col=['#ff5d8f','#ffd400','#4fd1ff','#3CB043','#ff8a3a','#b06bff','#ffffff'];var parts=[],fr=0;
    function burst(x,y){var c=col[Math.floor(Math.random()*col.length)];var n=60+Math.floor(Math.random()*40);
      for(var k=0;k<n;k++){var a=Math.random()*Math.PI*2,sp=(1.2+Math.random()*4.4)*dpr;
        parts.push({x:x,y:y,vx:Math.cos(a)*sp,vy:Math.sin(a)*sp,life:1,c:c,r:(1.6+Math.random()*1.9)*dpr});}}
    function tick(){if(fwCanvas!==canvas){window.removeEventListener('resize',rs);return;}
      fr++;ctx.clearRect(0,0,canvas.width,canvas.height);
      if(fr===4||fr%36===0)burst((0.18+Math.random()*0.64)*canvas.width,(0.15+Math.random()*0.4)*canvas.height);
      for(var i=0;i<parts.length;i++){var p=parts[i];p.vx*=0.985;p.vy=p.vy*0.985+0.05*dpr;p.x+=p.vx;p.y+=p.vy;p.life-=0.012;
        if(p.life<=0)continue;ctx.globalAlpha=Math.max(p.life,0);ctx.fillStyle=p.c;ctx.shadowColor=p.c;ctx.shadowBlur=10*dpr;
        ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fill();}
      ctx.globalAlpha=1;ctx.shadowBlur=0;parts=parts.filter(function(p){return p.life>0;});fwRAF=requestAnimationFrame(tick);}
    burst(canvas.width*0.3,canvas.height*0.3);burst(canvas.width*0.7,canvas.height*0.28);fwRAF=requestAnimationFrame(tick);
  }
  // lecture audio : hébreu -> français -> anglais
  var curAudio=null;
  function stopAudio(){ if(curAudio){ curAudio.onended=null; curAudio.onerror=null; try{curAudio.pause();}catch(e){} curAudio=null; } }
  function playSeq(key){
    stopAudio();
    var base=D.audio||'audio/', langs=['he','fr','en'], k=0;
    var a=new Audio(); curAudio=a;
    function nxt(){ if(a!==curAudio) return; if(k>=langs.length){ curAudio=null; return; } a.src=base+key+'-'+langs[k]+'.m4a'; k++; a.play().catch(function(){ nxt(); }); }
    a.onended=nxt; a.onerror=nxt; nxt();
  }
  var i=0;
  function show(){stopAudio();stage.innerHTML=render(S[i]);var c=document.getElementById('cur');if(c)c.textContent=i+1;
    var sb=stage.querySelector('.say-btn'); if(sb){ sb.addEventListener('click',function(e){e.stopPropagation(); playSeq(S[i].aud);}); }
    if(S[i].k==='congrats'){var cv=stage.querySelector('.fw');if(cv)startFW(cv);}else stopFW();}
  function next(){if(i<S.length-1){i++;show();}}
  function prev(){if(i>0){i--;show();}}
  show();
  document.addEventListener('keydown',function(e){
    if(e.key==='ArrowRight'||e.key===' '||e.key==='Enter'){e.preventDefault();next();}
    else if(e.key==='ArrowLeft'){e.preventDefault();prev();}});
  var app=document.getElementById('app');
  document.querySelector('.nav-prev').addEventListener('click',function(e){e.stopPropagation();prev();});
  document.querySelector('.nav-next').addEventListener('click',function(e){e.stopPropagation();next();});
  var swiped=false;
  app.addEventListener('click',function(){if(swiped){swiped=false;return;}next();});
  var x0=null,y0=null;
  app.addEventListener('touchstart',function(e){x0=e.changedTouches[0].clientX;y0=e.changedTouches[0].clientY;},{passive:true});
  app.addEventListener('touchend',function(e){if(x0==null)return;var dx=e.changedTouches[0].clientX-x0,dy=e.changedTouches[0].clientY-y0;
    if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy)){swiped=true;if(dx<0)next();else prev();setTimeout(function(){swiped=false;},400);}x0=y0=null;},{passive:true});
})();
