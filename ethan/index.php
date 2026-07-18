<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="robots" content="noindex, nofollow">
<title>Ethan</title>
<style>
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{margin:0;min-height:100%;}
  body{
    font-family:"Varela Round",ui-rounded,"SF Pro Rounded",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    background:radial-gradient(120% 120% at 50% 0%,#3a2160 0%,#241645 55%,#140d28 100%);
    background-attachment:fixed;
    color:#fff;user-select:none;-webkit-user-select:none;
    overflow-x:hidden;-webkit-overflow-scrolling:touch;
  }
  #fw{position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;}
  #hearts{position:fixed;inset:0;z-index:1;pointer-events:none;overflow:hidden;}
  main{position:relative;z-index:2;min-height:100vh;display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:clamp(28px,6vh,60px);padding:clamp(20px,5vw,48px);}

  h1{margin:0;font-size:clamp(3rem,16vw,8rem);font-weight:800;letter-spacing:.01em;
    background:linear-gradient(90deg,#ff5d8f,#ffb03a,#4fd1ff,#8a5bff);
    -webkit-background-clip:text;background-clip:text;color:transparent;
    text-shadow:0 6px 30px rgba(0,0,0,.35);animation:bob 2.6s ease-in-out infinite;}
  @keyframes bob{0%,100%{transform:translateY(0) rotate(-1deg);}50%{transform:translateY(-10px) rotate(1deg);}}

  .cards{display:flex;flex-wrap:wrap;gap:clamp(20px,5vw,48px);justify-content:center;}
  .card{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:clamp(10px,1.8vh,18px);text-decoration:none;color:#2b2b2b;
    width:clamp(180px,40vw,260px);min-height:clamp(180px,40vw,260px);padding:clamp(14px,3vw,22px);
    background:rgba(255,255,255,.95);border-radius:36px;
    box-shadow:0 16px 40px rgba(0,0,0,.35),0 0 0 6px rgba(255,255,255,.25);
    transition:transform .15s ease,box-shadow .15s ease;cursor:pointer;
  }
  .card:hover,.card:active{transform:scale(1.06) rotate(-1deg);
    box-shadow:0 22px 52px rgba(0,0,0,.45),0 0 0 8px rgba(255,255,255,.4);}
  .card .lbl{font-size:clamp(1.25rem,4vw,1.9rem);font-weight:800;text-align:center;line-height:1.1;}
  .card .heb{font-size:clamp(1rem,3vw,1.4rem);font-weight:700;color:#6b4bd6;direction:rtl;line-height:1.15;}
  .card .emoji{font-size:clamp(3.4rem,12vw,6rem);line-height:1;}

  /* palette d'icône couleurs */
  .palette{display:flex;gap:6px;}
  .palette span{width:clamp(20px,5vw,30px);height:clamp(20px,5vw,30px);border-radius:50%;
    box-shadow:0 0 0 3px #fff,0 4px 8px rgba(0,0,0,.2);}

  /* pokéball dessinée */
  .pokeball{position:relative;width:clamp(84px,22vw,130px);height:clamp(84px,22vw,130px);
    border-radius:50%;background:linear-gradient(#ff3b3b 0 50%,#fff 50% 100%);
    border:5px solid #222;box-shadow:0 6px 14px rgba(0,0,0,.25);overflow:hidden;}
  .pokeball::before{content:"";position:absolute;top:calc(50% - 4px);left:0;width:100%;height:8px;background:#222;}
  .pokeball::after{content:"";position:absolute;top:50%;left:50%;width:32%;height:32%;
    transform:translate(-50%,-50%);border-radius:50%;background:#fff;border:5px solid #222;}

  .heart{position:fixed;bottom:-48px;z-index:1;pointer-events:none;
    animation-name:rise;animation-timing-function:linear;animation-fill-mode:forwards;
    will-change:transform,opacity;}
  @keyframes rise{
    0%{transform:translateY(0) translateX(0) rotate(0);opacity:0;}
    12%{opacity:1;}
    50%{transform:translateY(-55vh) translateX(var(--sway)) rotate(16deg);}
    88%{opacity:1;}
    100%{transform:translateY(-118vh) translateX(calc(var(--sway) * -1)) rotate(-16deg);opacity:0;}
  }
</style>
</head>
<body>
<canvas id="fw"></canvas>
<div id="hearts"></div>
<main>
  <h1>Ethan</h1>
  <div class="cards">
    <a class="card" href="/ethan/planning">
      <div class="emoji">📅</div>
      <div class="lbl">Planning</div>
      <div class="heb" dir="rtl">לוּחַ זְמַנִּים</div>
    </a>
    <a class="card" href="/ethan/lettres">
      <div class="emoji">🔤</div>
      <div class="lbl">Lettres</div>
      <div class="heb" dir="rtl">אוֹתִיּוֹת</div>
    </a>
    <a class="card" href="/ethan/fables">
      <div class="emoji">🦊</div>
      <div class="lbl">Fables de La Fontaine</div>
      <div class="heb" dir="rtl">מִשְׁלֵי לָה פוֹנְטֵן</div>
    </a>
    <a class="card" href="/ethan/vocabulary">
      <div class="emoji">📚</div>
      <div class="lbl">Vocabulaire</div>
      <div class="heb" dir="rtl">אוֹצַר מִלִּים</div>
    </a>
    <a class="card" href="/ethan/QuizColors/">
      <div class="palette">
        <span style="background:#E63329"></span><span style="background:#FFD400"></span>
        <span style="background:#2A6FDB"></span><span style="background:#3CB043"></span>
      </div>
      <div class="lbl">QuizColors</div>
      <div class="heb" dir="rtl">חִידוֹן צְבָעִים</div>
    </a>
    <a class="card" href="/ethan/pokemon">
      <div class="pokeball"></div>
      <div class="lbl">Pokémon</div>
      <div class="heb" dir="rtl">פּוֹקֵמוֹן</div>
    </a>
    <a class="card" href="/ethan/videos">
      <div class="emoji">🎬</div>
      <div class="lbl">Vidéos</div>
      <div class="heb" dir="rtl">סְרָטוֹנִים</div>
    </a>
    <a class="card" href="/ethan/alimentation">
      <div class="emoji">🥗</div>
      <div class="lbl">Alimentation</div>
      <div class="heb" dir="rtl">תְּזוּנָה</div>
    </a>
  </div>
</main>

<script>
/* ---------- feux d'artifice ---------- */
(function(){
  const canvas=document.getElementById('fw'), ctx=canvas.getContext('2d');
  const dpr=Math.min(window.devicePixelRatio||1,2);
  function resize(){canvas.width=Math.floor(innerWidth*dpr);canvas.height=Math.floor(innerHeight*dpr);}
  resize(); addEventListener('resize',resize);
  const colors=['#ff5d8f','#ffd400','#4fd1ff','#3CB043','#ff8a3a','#b06bff','#ff3b6b','#7CFC8A','#ffffff'];
  let parts=[],frame=0;
  function burst(x,y){
    const col=colors[Math.floor(Math.random()*colors.length)];
    const n=60+Math.floor(Math.random()*50);
    for(let k=0;k<n;k++){
      const a=Math.random()*Math.PI*2, sp=(1.2+Math.random()*4.6)*dpr;
      parts.push({x:x,y:y,vx:Math.cos(a)*sp,vy:Math.sin(a)*sp,life:1,col:col,r:(1.6+Math.random()*2)*dpr});
    }
  }
  function tick(){
    frame++;
    ctx.clearRect(0,0,canvas.width,canvas.height);
    if(frame%20===0){ burst((0.12+Math.random()*0.76)*canvas.width,(0.12+Math.random()*0.5)*canvas.height); }
    for(const p of parts){
      p.vx*=0.985; p.vy=p.vy*0.985+0.05*dpr; p.x+=p.vx; p.y+=p.vy; p.life-=0.011;
      if(p.life<=0)continue;
      ctx.globalAlpha=Math.max(p.life,0);
      ctx.fillStyle=p.col; ctx.shadowColor=p.col; ctx.shadowBlur=12*dpr;
      ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2); ctx.fill();
    }
    ctx.globalAlpha=1; ctx.shadowBlur=0;
    parts=parts.filter(p=>p.life>0);
    requestAnimationFrame(tick);
  }
  burst(canvas.width*0.3,canvas.height*0.3);
  burst(canvas.width*0.7,canvas.height*0.28);
  requestAnimationFrame(tick);
})();

/* ---------- cœurs qui montent (après quelques secondes) ---------- */
(function(){
  const box=document.getElementById('hearts');
  const emojis=['❤️','💛','💚','💙','💜','🧡','🩷','💗','💖'];
  function spawn(){
    const h=document.createElement('span');
    h.className='heart';
    h.textContent=emojis[Math.floor(Math.random()*emojis.length)];
    h.style.left=(Math.random()*98)+'vw';
    h.style.fontSize=(20+Math.random()*34)+'px';
    const dur=5+Math.random()*4;
    h.style.animationDuration=dur+'s';
    h.style.setProperty('--sway',(Math.random()*70-35)+'px');
    box.appendChild(h);
    setTimeout(function(){h.remove();}, dur*1000+300);
  }
  setTimeout(function(){
    for(let i=0;i<14;i++){ setTimeout(spawn, i*120); }   // première volée
    setInterval(spawn, 260);                              // puis en continu
  }, 3500);
})();
</script>
</body>
</html>
