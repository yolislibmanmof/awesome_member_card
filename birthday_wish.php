<?php
$S = amc_settings();
$name = isset($_GET['name']) ? trim((string)$_GET['name']) : 'Anggota';
$name = htmlspecialchars($name);
$libraryName = htmlspecialchars($S['library_name']);
$cardSubtitle = htmlspecialchars($S['card_subtitle']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Selamat Ulang Tahun, <?= $name ?>!</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
html,body{min-height:100%;}
body{font-family:'Segoe UI',sans-serif;background:#12081f;min-height:100vh;display:flex;overflow-y:auto;overflow-x:hidden;padding:30px 16px 46px;color:#fff;}
/* ---- Latar aurora bergerak ---- */
.aurora{position:fixed;inset:0;z-index:0;overflow:hidden;}
.aurora i{position:absolute;width:60vmax;height:60vmax;border-radius:50%;filter:blur(70px);opacity:.5;mix-blend-mode:screen;}
.aurora i:nth-child(1){background:#7c3aed;top:-20vmax;left:-15vmax;animation:drift1 16s ease-in-out infinite alternate;}
.aurora i:nth-child(2){background:#ec4899;bottom:-25vmax;right:-15vmax;animation:drift2 19s ease-in-out infinite alternate;}
.aurora i:nth-child(3){background:#0ea5e9;top:30%;left:45%;width:40vmax;height:40vmax;animation:drift3 22s ease-in-out infinite alternate;}
@keyframes drift1{to{transform:translate(12vmax,8vmax) scale(1.15)}}
@keyframes drift2{to{transform:translate(-10vmax,-9vmax) scale(1.1)}}
@keyframes drift3{to{transform:translate(-14vmax,6vmax) scale(.9)}}
#fx{position:fixed;inset:0;z-index:1;pointer-events:none;}
/* ---- Balon realistis ---- */
.balloon{position:fixed;bottom:-190px;z-index:1;width:76px;height:96px;border-radius:50% 50% 48% 52%/58% 58% 42% 42%;
 background:radial-gradient(circle at 30% 24%,rgba(255,255,255,.95) 0%,rgba(255,255,255,.28) 16%,var(--c) 48%,var(--cd) 100%);
 box-shadow:inset -8px -12px 22px rgba(0,0,0,.28), inset 6px 8px 14px rgba(255,255,255,.25);
 animation:rise var(--t) linear var(--dl) forwards;pointer-events:none;}
.balloon::before{content:'';position:absolute;bottom:-9px;left:50%;transform:translateX(-50%);border:7px solid transparent;border-top:11px solid var(--cd);}
.balloon::after{content:'';position:absolute;bottom:-120px;left:50%;width:2px;height:118px;transform:translateX(-50%);
 background:linear-gradient(rgba(255,255,255,.75),rgba(255,255,255,.08));border-radius:2px;}
.balloon b{position:absolute;inset:0;animation:sway 3.4s ease-in-out infinite alternate;border-radius:inherit;}
@keyframes rise{to{transform:translateY(-145vh)}}
@keyframes sway{from{transform:rotate(-4deg) translateX(-6px)}to{transform:rotate(4deg) translateX(6px)}}
/* ---- Kartu kaca: margin:auto agar TIDAK pernah terpotong ---- */
.card{position:relative;z-index:2;margin:auto;width:min(640px,94vw);border-radius:28px;padding:34px 26px 30px;text-align:center;
 background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
 box-shadow:0 30px 80px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.35);
 opacity:0;transform:translateY(70px) scale(.92);animation:cardin 1s .15s cubic-bezier(.2,1.25,.35,1) forwards;}
@keyframes cardin{to{opacity:1;transform:none}}
/* ---- Bendera pesta ---- */
.bunting{display:flex;justify-content:center;gap:3px;margin-bottom:10px;}
.bunting i{width:24px;height:32px;clip-path:polygon(0 0,100% 0,50% 100%);transform-origin:top center;animation:swing 2.6s ease-in-out infinite alternate;}
@keyframes swing{from{transform:rotate(-6deg)}to{transform:rotate(6deg)}}
/* ---- Judul ---- */
.title{font-size:clamp(28px,5.6vw,50px);font-weight:900;letter-spacing:1px;line-height:1.15;margin-bottom:4px;}
.title span{display:inline-block;opacity:0;transform:translateY(34px) rotateX(95deg);
 background:linear-gradient(90deg,#f9a8d4,#fbbf24,#93c5fd,#f9a8d4);background-size:300% 100%;
 -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
 filter:drop-shadow(0 4px 18px rgba(236,72,153,.45));}
.title.on span{animation:word .8s cubic-bezier(.2,1.4,.35,1) forwards, hue 7s linear infinite;}
.title.on span:nth-child(2){animation-delay:.18s,0s;} .title.on span:nth-child(3){animation-delay:.36s,0s;}
@keyframes word{to{opacity:1;transform:none}}
@keyframes hue{to{background-position:300% 0}}
/* ---- Kue 3 tingkat ---- */
.cakewrap{height:200px;position:relative;margin:4px auto 2px;width:250px;}
.cake{position:absolute;inset:0;transform:translateY(60px) scale(.55);opacity:0;transition:all 1s cubic-bezier(.2,1.3,.35,1);cursor:pointer;}
.cake.on{transform:none;opacity:1;}
.plate{position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:250px;height:16px;border-radius:50%;
 background:radial-gradient(ellipse at 50% 30%,#f8fafc,#94a3b8 70%,#64748b);box-shadow:0 8px 18px rgba(0,0,0,.45);}
.tier{position:absolute;left:50%;transform:translateX(-50%);border-radius:12px;box-shadow:inset 0 -10px 0 rgba(0,0,0,.16), inset 0 6px 0 rgba(255,255,255,.22), 0 6px 16px rgba(0,0,0,.3);}
.tier::before{content:'';position:absolute;top:-4px;left:0;right:0;height:12px;border-radius:12px 12px 0 0;background:rgba(255,255,255,.92);}
.tier::after{content:'';position:absolute;top:6px;left:0;right:0;height:16px;background-image:radial-gradient(circle at 12px 0,rgba(255,255,255,.92) 8px,transparent 9px);background-size:26px 18px;background-repeat:repeat-x;}
.t1{bottom:14px;width:230px;height:62px;background:linear-gradient(#f472b6,#be185d);}
.t2{bottom:70px;width:176px;height:52px;background:linear-gradient(#fbbf24,#b45309);}
.t3{bottom:116px;width:122px;height:44px;background:linear-gradient(#60a5fa,#1d4ed8);}
.candle{position:absolute;bottom:158px;width:9px;height:34px;border-radius:3px;
 background:repeating-linear-gradient(45deg,#fff 0 5px,#f472b6 5px 10px);box-shadow:0 2px 6px rgba(0,0,0,.3);}
.candle:nth-child(5){left:88px;} .candle:nth-child(6){left:108px;height:40px;}
.candle:nth-child(7){left:128px;} .candle:nth-child(8){left:148px;height:38px;} .candle:nth-child(9){left:168px;height:32px;}
.flame{position:absolute;top:-17px;left:50%;width:13px;height:19px;transform:translateX(-50%) scale(0);transform-origin:bottom center;
 background:radial-gradient(circle at 50% 72%,#fff 0%,#fde047 38%,#fb923c 68%,rgba(251,146,60,0) 100%);
 border-radius:50% 50% 50% 50%/62% 62% 38% 38%;filter:blur(.3px);}
.flame::after{content:'';position:absolute;inset:-14px;background:radial-gradient(circle,rgba(253,224,71,.4),transparent 70%);border-radius:50%;}
.cake.lit .flame{animation:ignite .45s cubic-bezier(.2,1.6,.4,1) forwards, flick .16s .45s infinite alternate;}
.cake.lit .candle:nth-child(6) .flame{animation-delay:.15s,.6s;} .cake.lit .candle:nth-child(7) .flame{animation-delay:.3s,.75s;}
.cake.lit .candle:nth-child(8) .flame{animation-delay:.45s,.9s;} .cake.lit .candle:nth-child(9) .flame{animation-delay:.6s,1.05s;}
@keyframes ignite{to{transform:translateX(-50%) scale(1)}}
@keyframes flick{from{transform:translateX(-50%) scale(1) rotate(-2deg)}to{transform:translateX(-52%) scale(1.12,1.22) rotate(2deg)}}
.cake.out .flame{animation:snuff .5s forwards !important;}
@keyframes snuff{40%{transform:translateX(-50%) scale(1.3,.6);opacity:.8}100%{transform:translateX(-50%) scale(0);opacity:0}}
.smoke{position:absolute;top:-26px;left:50%;width:8px;height:8px;border-radius:50%;background:rgba(226,232,240,.7);opacity:0;}
.cake.out .smoke{animation:puff 1.6s ease-out forwards;}
.cake.out .candle:nth-child(6) .smoke{animation-delay:.1s;} .cake.out .candle:nth-child(7) .smoke{animation-delay:.2s;}
.cake.out .candle:nth-child(8) .smoke{animation-delay:.3s;} .cake.out .candle:nth-child(9) .smoke{animation-delay:.4s;}
@keyframes puff{0%{opacity:.8;transform:translate(-50%,0) scale(.6)}100%{opacity:0;transform:translate(-30%,-46px) scale(2.6)}}
/* ---- Keterangan tiup lilin: satu baris rapi DI BAWAH kue ---- */
.cakehint{margin:0 auto 10px;font-size:11px;letter-spacing:.6px;color:rgba(255,255,255,.72);opacity:0;transition:opacity .8s;max-width:90%;}
.cakehint.on{opacity:1;}
/* ---- Nama: pergantian baris hanya antar kata ---- */
.name{font-size:clamp(20px,4.8vw,38px);font-weight:800;letter-spacing:1.5px;margin:2px 0 12px;min-height:1.2em;
 text-shadow:0 0 24px rgba(251,191,36,.55), 0 4px 14px rgba(0,0,0,.4);}
.name .word{display:inline-block;white-space:nowrap;}
.name span.l{display:inline-block;opacity:0;transform:translateY(26px) scale(.6);}
.name.on span.l{animation:letter .65s cubic-bezier(.2,1.7,.4,1) forwards;animation-delay:calc(var(--i)*.05s);
 background:linear-gradient(180deg,#fff,#fde047 60%,#f59e0b);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.name span.sp{display:inline-block;width:.45em;}
@keyframes letter{to{opacity:1;transform:none}}
/* ---- Pesan ---- */
.msg p{opacity:0;transform:translateY(14px);font-size:14.5px;line-height:1.75;color:rgba(255,255,255,.92);margin-bottom:8px;}
.msg.on p{animation:fadeup .9s forwards;}
.msg.on p:nth-child(1){animation-delay:.1s}.msg.on p:nth-child(2){animation-delay:.35s}.msg.on p:nth-child(3){animation-delay:.6s}
.msg.on p:nth-child(4){animation-delay:.85s}.msg.on p:nth-child(5){animation-delay:1.1s}
@keyframes fadeup{to{opacity:1;transform:none}}
.from{margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.22);font-size:13px;color:rgba(255,255,255,.75);opacity:0;}
.from.on{animation:fadeup 1s .4s forwards;}
.from strong{color:#fde047;}
/* ---- Tombol ---- */
.giftbtn{margin-top:14px;padding:13px 30px;border:none;border-radius:999px;cursor:pointer;font-size:15px;font-weight:800;color:#1e1b4b;
 background:linear-gradient(135deg,#fde047,#f472b6 60%,#fb923c);box-shadow:0 10px 30px rgba(244,114,182,.45);position:relative;overflow:hidden;opacity:0;}
.giftbtn.on{animation:fadeup .8s .8s forwards;}
.giftbtn::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.75),transparent);animation:shine 2.6s linear infinite;}
@keyframes shine{to{left:130%}}
.giftbtn:hover{transform:translateY(-2px) scale(1.03);}
.musicbtn{position:fixed;top:18px;right:18px;z-index:50;width:54px;height:54px;border-radius:50%;border:1px solid rgba(255,255,255,.4);cursor:pointer;font-size:22px;
 background:rgba(255,255,255,.14);backdrop-filter:blur(10px);box-shadow:0 8px 24px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;transition:transform .2s;}
.musicbtn:hover{transform:scale(1.1);}
.musicbtn.playing{animation:mpulse 1.1s ease-in-out infinite;background:linear-gradient(135deg,#ec4899,#f59e0b);}
@keyframes mpulse{0%,100%{box-shadow:0 0 0 0 rgba(236,72,153,.55)}50%{box-shadow:0 0 0 16px rgba(236,72,153,0)}}
.musichint{position:fixed;top:26px;right:84px;z-index:50;background:rgba(15,23,42,.85);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:12px;padding:8px 12px;border-radius:10px;animation:hintfade 7s forwards;}
@keyframes hintfade{0%,80%{opacity:1}100%{opacity:0;visibility:hidden}}
/* ---- Kejutan tiupan lilin ---- */
.wishpop{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0;transition:opacity .5s;}
.wishpop.show{opacity:1;}
.wishpop div{background:rgba(17,24,39,.88);border:1px solid rgba(253,224,71,.5);backdrop-filter:blur(12px);padding:26px 34px;border-radius:20px;
 font-size:clamp(16px,3.4vw,22px);font-weight:700;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5);transform:scale(.7);transition:transform .5s cubic-bezier(.2,1.6,.4,1);}
.wishpop.show div{transform:scale(1);}
@media (prefers-reduced-motion: reduce){
  *{animation-duration:.01s !important;animation-iteration-count:1 !important;transition-duration:.01s !important;}
}
</style>
</head>
<body>
<div class="aurora"><i></i><i></i><i></i></div>
<canvas id="fx"></canvas>

<div class="balloon" style="--c:#ec4899;--cd:#9d174d;--t:13s;--dl:.4s;left:6%;"><b></b></div>
<div class="balloon" style="--c:#f59e0b;--cd:#92400e;--t:16s;--dl:1.6s;left:22%;"><b></b></div>
<div class="balloon" style="--c:#3b82f6;--cd:#1e40af;--t:14s;--dl:3s;left:74%;"><b></b></div>
<div class="balloon" style="--c:#10b981;--cd:#065f46;--t:17s;--dl:4.4s;left:88%;"><b></b></div>
<div class="balloon" style="--c:#8b5cf6;--cd:#5b21b6;--t:15s;--dl:6s;left:48%;"><b></b></div>

<button class="musicbtn" id="musicBtn" title="Putar / hentikan musik" onclick="toggleMusic()">🎵</button>
<div class="musichint">Ketuk 🎵 untuk memutar lagu Selamat Ulang Tahun</div>

<div class="card">
    <div class="bunting" id="bunting"></div>
    <h1 class="title" id="title"><span>Selamat&nbsp;</span><span>Ulang&nbsp;</span><span>Tahun!</span></h1>

    <div class="cakewrap">
        <div class="cake" id="cake" title="Klik untuk meniup lilin">
            <div class="plate"></div>
            <div class="tier t1"></div>
            <div class="tier t2"></div>
            <div class="tier t3"></div>
            <div class="candle"><div class="flame"></div><div class="smoke"></div></div>
            <div class="candle"><div class="flame"></div><div class="smoke"></div></div>
            <div class="candle"><div class="flame"></div><div class="smoke"></div></div>
            <div class="candle"><div class="flame"></div><div class="smoke"></div></div>
            <div class="candle"><div class="flame"></div><div class="smoke"></div></div>
        </div>
    </div>
    <div class="cakehint" id="cakehint">✦ klik kue untuk meniup lilin &amp; mengucapkan doa ✦</div>

    <h2 class="name" id="name" data-name="<?= $name ?>"><?= $name ?></h2>

    <div class="msg" id="msg">
        <p>Di hari yang istimewa ini, kami dari <strong><?= $libraryName ?></strong> mengucapkan:</p>
        <p><strong>Selamat Ulang Tahun!</strong> 🎊</p>
        <p>Semoga panjang umur, sehat selalu, dan dilimpahi keberkahan dalam setiap langkah.</p>
        <p>Semoga seluruh cita-cita dan harapan tercapai, serta senantiasa dalam lindungan-Nya.</p>
        <p>Terima kasih telah menjadi bagian dari keluarga besar perpustakaan kami! 📚✨</p>
    </div>

    <div class="from" id="from">
        Dengan hormat,<br><strong><?= $libraryName ?></strong><br><?= $cardSubtitle ?>
    </div>

    <button class="giftbtn" id="giftBtn" onclick="megaBurst()">🎁 Buka Kejutan</button>
</div>

<div class="wishpop" id="wishpop"><div>🌟 Tiupanmu membawa doa...<br>Semoga semua harapan terkabul! 🌟</div></div>

<script>
var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ==== Bendera pesta ==== */
(function(){
    var cols = ['#ec4899','#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444','#fde047'];
    var b = document.getElementById('bunting');
    for (var i = 0; i < 11; i++) {
        var el = document.createElement('i');
        el.style.background = cols[i % cols.length];
        el.style.animationDelay = (i * .12) + 's';
        b.appendChild(el);
    }
})();

/* ==== Mesin efek canvas: confetti fisika + kembang api ==== */
var cv = document.getElementById('fx'), cx = cv.getContext('2d'), W, H, pieces = [], rockets = [], sparks = [];
var PAL = ['#ec4899','#f59e0b','#3b82f6','#10b981','#8b5cf6','#fde047','#ef4444','#22d3ee'];
function resize(){ W = cv.width = innerWidth; H = cv.height = innerHeight; }
resize(); addEventListener('resize', resize);
function confetti(x, y, n, spread){
    for (var i = 0; i < n; i++) {
        var a = -Math.PI/2 + (Math.random() - .5) * (spread || 1.6);
        var sp = 6 + Math.random() * 9;
        pieces.push({x:x, y:y, vx:Math.cos(a)*sp, vy:Math.sin(a)*sp, w:5+Math.random()*7, h:8+Math.random()*8,
            r:Math.random()*Math.PI, vr:(Math.random()-.5)*.3, c:PAL[i%PAL.length], fl:Math.random()*6.28, life:1});
    }
}
function rocket(){
    rockets.push({x:W*(.15+Math.random()*.7), y:H, vy:-(9+Math.random()*4), ty:H*(.18+Math.random()*.3), c:PAL[Math.floor(Math.random()*PAL.length)]});
}
function explode(r){
    for (var i = 0; i < 56; i++) {
        var a = Math.random()*6.283, sp = 1.5+Math.random()*5;
        sparks.push({x:r.x, y:r.y, vx:Math.cos(a)*sp, vy:Math.sin(a)*sp, life:1, c:r.c});
    }
}
function tick(){
    cx.clearRect(0,0,W,H);
    var i, p;
    for (i = pieces.length-1; i >= 0; i--) {
        p = pieces[i];
        p.vy += .16; p.vx *= .985; p.vy *= .985; p.x += p.vx + Math.sin((p.fl += .09))*.8; p.y += p.vy; p.r += p.vr;
        if (p.y > H + 40) { pieces.splice(i,1); continue; }
        cx.save(); cx.translate(p.x,p.y); cx.rotate(p.r); cx.fillStyle = p.c;
        cx.fillRect(-p.w/2, -p.h/2, p.w, p.h * (0.6 + Math.abs(Math.sin(p.fl)) * 0.4));
        cx.restore();
    }
    for (i = rockets.length-1; i >= 0; i--) {
        p = rockets[i]; p.y += p.vy; p.vy *= .985;
        cx.fillStyle = 'rgba(255,255,255,.85)'; cx.fillRect(p.x-1, p.y, 2, 10);
        if (p.y <= p.ty) { explode(p); rockets.splice(i,1); }
    }
    for (i = sparks.length-1; i >= 0; i--) {
        p = sparks[i]; p.vy += .05; p.x += p.vx; p.y += p.vy; p.vx *= .985; p.vy *= .985; p.life -= .012;
        if (p.life <= 0) { sparks.splice(i,1); continue; }
        cx.globalAlpha = Math.max(p.life, 0);
        cx.shadowBlur = 12; cx.shadowColor = p.c; cx.fillStyle = p.c;
        cx.beginPath(); cx.arc(p.x, p.y, 2.2, 0, 6.283); cx.fill();
        cx.shadowBlur = 0; cx.globalAlpha = 1;
    }
    requestAnimationFrame(tick);
}
if (!REDUCED) { tick(); setInterval(function(){ if (document.hasFocus()) rocket(); }, 2600); }

/* ==== Confetti saat klik di mana pun ==== */
document.addEventListener('click', function(e){
    if (e.target.closest('button')) return;
    confetti(e.clientX, e.clientY, 36, 2.4);
});

/* ==== Koreografi pembukaan ==== */
function burstCannons(){ confetti(W*.12, H*.85, 70, 1.1); confetti(W*.88, H*.85, 70, 1.1); }
setTimeout(function(){ document.getElementById('title').classList.add('on'); }, 400);
setTimeout(function(){ document.getElementById('cake').classList.add('on'); }, 1100);
setTimeout(function(){
    document.getElementById('cake').classList.add('lit');
    document.getElementById('cakehint').classList.add('on');
}, 2000);
setTimeout(function(){
    var el = document.getElementById('name'), txt = el.getAttribute('data-name');
    el.textContent = '';
    var idx = 0;
    txt.split(' ').forEach(function(word, wi, arr){
        var w = document.createElement('span');
        w.className = 'word';
        for (var i = 0; i < word.length; i++) {
            var s = document.createElement('span');
            s.className = 'l';
            s.textContent = word[i];
            s.style.setProperty('--i', idx++);
            w.appendChild(s);
        }
        el.appendChild(w);
        if (wi < arr.length - 1) {
            var sp = document.createElement('span');
            sp.className = 'sp';
            el.appendChild(sp);
        }
    });
    el.classList.add('on');
}, 2600);
setTimeout(function(){ document.getElementById('msg').classList.add('on'); }, 3300);
setTimeout(function(){ document.getElementById('from').classList.add('on'); document.getElementById('giftBtn').classList.add('on'); }, 3900);
setTimeout(burstCannons, 2100);

/* ==== Tiup lilin ==== */
var blown = false;
document.getElementById('cake').addEventListener('click', function(){
    if (blown) return;
    blown = true;
    this.classList.remove('lit'); this.classList.add('out');
    document.getElementById('cakehint').classList.remove('on');
    setTimeout(function(){
        document.getElementById('wishpop').classList.add('show');
        burstCannons(); rocket(); rocket();
        confetti(W/2, H*.4, 90, 2.6);
    }, 550);
    setTimeout(function(){ document.getElementById('wishpop').classList.remove('show'); }, 5200);
});

/* ==== Kejutan tombol hadiah ==== */
function megaBurst(){
    burstCannons(); rocket(); rocket(); rocket();
    confetti(W/2, H*.3, 120, 3);
}

/* ==== Musik music-box + bass (Web Audio, tanpa berkas) ==== */
var AC = null, playing = false, loopTimer = null;
function fq(m){ return 440 * Math.pow(2, (m - 69) / 12); }
var MELODY = [
    [67,.75],[67,.25],[69,1],[67,1],[72,1],[71,2],
    [67,.75],[67,.25],[69,1],[67,1],[74,1],[72,2],
    [67,.75],[67,.25],[79,1],[76,1],[72,1],[71,1],[69,2],
    [77,.75],[77,.25],[76,1],[72,1],[74,1],[72,2]
];
var BASS = [[48,3],[43,3],[48,3],[43,3],[48,3],[48,3],[41,3],[48,4]];
function bell(ac, t, midi, dur, vol){
    var o = ac.createOscillator(), o2 = ac.createOscillator(), g = ac.createGain(), g2 = ac.createGain();
    o.type = 'sine'; o.frequency.value = fq(midi);
    o2.type = 'sine'; o2.frequency.value = fq(midi) * 2;
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol, t + .012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur * .95);
    g2.gain.setValueAtTime(0.0001, t);
    g2.gain.exponentialRampToValueAtTime(vol * .22, t + .01);
    g2.gain.exponentialRampToValueAtTime(0.0001, t + dur * .5);
    o.connect(g); o2.connect(g2); g.connect(ac.destination); g2.connect(ac.destination);
    o.start(t); o.stop(t + dur); o2.start(t); o2.stop(t + dur);
}
function bassNote(ac, t, midi, dur){
    var o = ac.createOscillator(), g = ac.createGain();
    o.type = 'triangle'; o.frequency.value = fq(midi);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(.14, t + .05);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur * .9);
    o.connect(g); g.connect(ac.destination);
    o.start(t); o.stop(t + dur);
}
function playSong(){
    AC = new (window.AudioContext || window.webkitAudioContext)();
    var beat = .52, t = AC.currentTime + .15, i;
    for (i = 0; i < MELODY.length; i++) { bell(AC, t, MELODY[i][0], MELODY[i][1] * beat * 1.6, .3); t += MELODY[i][1] * beat; }
    var tb = AC.currentTime + .15;
    for (i = 0; i < BASS.length; i++) { bassNote(AC, tb, BASS[i][0], BASS[i][1] * beat); tb += BASS[i][1] * beat; }
    var total = (t - AC.currentTime + .6) * 1000;
    loopTimer = setTimeout(function(){ if (playing) { try { AC.close(); } catch(e){} playSong(); } }, total);
}
function stopSong(){
    playing = false; clearTimeout(loopTimer);
    if (AC) { try { AC.close(); } catch(e){} AC = null; }
    var b = document.getElementById('musicBtn');
    b.classList.remove('playing'); b.textContent = '🎵';
}
function toggleMusic(){
    if (playing) { stopSong(); return; }
    playing = true;
    var b = document.getElementById('musicBtn');
    b.classList.add('playing'); b.textContent = '🔊';
    playSong();
}
</script>
</body>
</html>