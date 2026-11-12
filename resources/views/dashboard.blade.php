@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="tb">
  <h1>Dashboard</h1>

  <div class="tb-clock-wrap">
    <button class="live-clock" id="cal-btn" type="button" aria-expanded="false" aria-controls="cal-popup" title="Klik untuk membuka kalender">
      <span class="live-clock-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
      <span class="live-clock-time" id="clock-time">--:--:-- WIB</span>
      <!-- <span class="live-clock-sep" aria-hidden="true"></span> -->
      <span class="live-clock-date" id="clock-date">--</span>
    </button>
    <div class="tb-pop" id="cal-popup" role="dialog" aria-label="Kalender">
      <div class="tb-ch">
        <button id="tbprev" type="button" aria-label="Bulan sebelumnya">&lsaquo;</button>
        <b id="tbcm"></b>
        <button id="tbnext" type="button" aria-label="Bulan berikutnya">&rsaquo;</button>
      </div>
      <div class="cg" id="tbcg"></div>
    </div>
  </div>

  <div class="tb-actions">
    @if($crossings->count() > 1)
    <form method="GET" action="{{ route('dashboard') }}">
      <select name="crossing" class="tb-sel" onchange="this.form.submit()" aria-label="Pilih pos">
        <option value="">Semua pos</option>
        @foreach($crossings as $c)<option value="{{ $c->id }}" @selected($selected === $c->id)>{{ $c->code }}</option>@endforeach
      </select>
    </form>
    @endif

    <button class="tb-alarm" id="snd" type="button" aria-pressed="false" title="Klik untuk menyalakan atau mematikan bunyi alarm saat palang menutup">
      <svg class="off-i" viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>
      <svg class="on-i" viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/></svg>
      <span>Suara alarm</span><span class="st" id="snd-st">Mati</span>
    </button>
  </div>
</div>

<div class="am" id="am" role="alertdialog" aria-modal="true" aria-labelledby="am-title" aria-describedby="am-text">
  <div class="am-card warn" id="am-card">
    <div class="am-ic" id="am-ic"></div>
    <h2 id="am-title"></h2>
    <p id="am-text"></p>
    <div class="am-ring" id="am-ring">
      <svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="54" class="bg"/><circle cx="60" cy="60" r="54" class="fg" id="am-arc"/></svg>
      <div><b id="am-num">0</b><span>detik</span></div>
    </div>
    <div class="am-meta" id="am-meta"></div>
    <button class="btn ghost" id="am-close" type="button">Sembunyikan</button>
  </div>
</div>

<section class="kpis" aria-label="Ringkasan hari ini">
  <div class="kpi"><b id="k1">0</b><span>Penutupan palang hari ini</span></div>
  <div class="kpi"><b id="k2">0</b><span>Otomatis (sistem TinyML)</span></div>
  <div class="kpi"><b id="k3">0</b><span>Manual (petugas)</span></div>
  <div class="kpi blue"><b id="k4">-</b><span>Keyakinan rata-rata TinyML</span></div>
</section>

<section class="g g2">
  <div class="card donut-card">
    <div class="ch"><h2>Pemicu penutupan palang</h2><span class="pill">Hari ini</span></div>
    <div class="donut">
      <svg viewBox="0 0 160 160" id="dn"></svg>
      <div class="donut-core">
        <b class="donut-val" id="dt">0</b>
        <span class="donut-sub">Total penutupan</span>
      </div>
    </div>
    <div class="dl">
      <div class="dl-item">
        <div class="dl-label"><i style="background:var(--blue)"></i><span>Manual</span></div>
        <b id="dm">0</b>
      </div>
      <div class="dl-item">
        <div class="dl-label"><i style="background:var(--yellow)"></i><span>Otomatis</span></div>
        <b id="da">0</b>
      </div>
    </div>
  </div>
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between">
    <div>
      <div class="ch"><h2>Penutupan per jam</h2><span class="pill">00.00 sampai 23.00</span></div>
      <div class="lg"><span><i style="background:var(--yellow)"></i>Hari ini</span><span><i style="background:var(--blue)"></i>Rata-rata 7 hari</span></div>
    </div>
    <svg id="area" viewBox="0 0 560 210" style="width:100%;height:auto" role="img" aria-label="Grafik penutupan palang per jam hari ini dibanding rata-rata 7 hari"></svg>
  </div>
</section>

<section class="g g3">
  <div class="card">
    <div class="ch"><h2>Status palang</h2><span class="pill">Perkiraan dari log</span></div>
    <div class="live-label" id="ll">-</div>
    <div class="muted" style="font-size:12px;margin-top:2px" id="lsub">&nbsp;</div>
    <div class="muted" style="font-size:12px;margin-top:14px">Keyakinan deteksi kereta terakhir</div>
    <div class="pbar"><i id="lb" style="width:0"></i></div>
    <div class="mini">
      <div><b id="lrs">-</b><span>RSSI LoRa</span></div>
      <div><b id="lds">-</b><span>Jarak HC-SR04</span></div>
      <div><b id="lbat">-</b><span>Baterai Node 1</span></div>
    </div>
  </div>
  <div class="card">
    <div class="ch"><h2>Penutupan 7 hari</h2><span class="pill">Mingguan</span></div>
    <div class="lg"><span><i style="background:var(--yellow)"></i>Otomatis</span><span><i style="background:var(--blue)"></i>Manual</span></div>
    <div class="bars" id="bars"><div class="yl" id="yl"></div></div>
    <div class="xl" id="xl"></div>
  </div>
</section>

<section class="g g3">
  <div class="card"><div class="ch"><h2>Perangkat</h2><span class="pill">Terakhir lapor</span></div><div id="devs"></div></div>
  <div class="card"><div class="ch"><h2>Status Sensor</h2></div><div id="sens"></div></div>
</section>

<section class="card">
  <div class="ch"><h2>Log kejadian terbaru</h2><a class="btn sm ghost" href="{{ route('events.index') }}">Lihat semua</a></div>
  <div class="tw"><table><thead><tr><th>Waktu</th><th>Pemicu</th><th>Probabilitas</th><th>Status palang</th><th>Catatan</th></tr></thead><tbody id="rows"></tbody></table></div>
</section>
@endsection

@push('scripts')
<style>
/* ===== navbar dashboard: judul kiri, jam di tengah (klik = kalender), aksi kanan ===== */
.tb{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:18px}
.tb h1{font-size:22px;font-weight:600}
.tb-actions{justify-self:end;display:flex;align-items:center;gap:10px;flex-wrap:wrap}

/* pil jam dan tanggal, satu baris memanjang; bisa diklik untuk membuka kalender */
.tb-clock-wrap{position:relative;justify-self:center}
.tb .live-clock{display:flex;align-items:center;gap:12px;padding:10px 18px;background:var(--card);border:1px solid transparent;border-radius:14px;color:var(--ink);font:inherit;cursor:pointer;box-shadow:0 2px 10px rgba(26,35,66,.07);transition:border-color .15s,box-shadow .15s}
.tb .live-clock:hover{border-color:var(--blue);box-shadow:0 4px 14px rgba(17,99,240,.16)}
.tb .live-clock:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.tb .live-clock[aria-expanded="true"]{border-color:var(--blue)}
.tb .live-clock-icon{display:grid;place-items:center;flex:none}
.tb .live-clock-icon svg{width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.tb .live-clock-time{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:14px;font-weight:700;white-space:nowrap}
.tb .live-clock-sep{width:1px;height:20px;background:var(--line);flex:none}
.tb .live-clock-date{font-size:13px;font-weight:500;color:var(--muted);white-space:nowrap}

/* kalender muncul di bawah pil jam */
.tb-pop{display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);z-index:50;width:272px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:0 12px 34px rgba(26,35,66,.22)}
.tb-pop.on{display:block}
.tb-ch{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.tb-ch b{font-size:14px;font-weight:600}
.tb-ch button{width:28px;height:28px;border:0;border-radius:8px;background:var(--panel);color:var(--ink);cursor:pointer;font-size:16px;line-height:1}

.tb-sel,.tb-alarm{height:44px;border-radius:12px;font:inherit}
.tb-sel:focus-visible,.tb-alarm:focus-visible{outline:2px solid var(--blue);outline-offset:2px}

/* pilih pos */
.tb-sel{padding:0 36px 0 14px;background-color:var(--card);border:1px solid var(--line);color:var(--ink);cursor:pointer;-webkit-appearance:none;appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237b86a2' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 12px center;background-size:14px}

/* tombol suara alarm */
.tb-alarm{display:inline-flex;align-items:center;gap:9px;padding:0 12px 0 14px;border:1.5px solid var(--blue);background:transparent;color:var(--blue);font-weight:600;font-size:13px;cursor:pointer;transition:background .15s,color .15s,box-shadow .15s}
.tb-alarm:hover{background:var(--blue2)}
.tb-alarm:active{transform:translateY(1px)}
.tb-alarm svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.tb-alarm .on-i{display:none}
.tb-alarm .st{font-size:11px;font-weight:600;padding:2px 9px;border-radius:99px;background:var(--blue2);color:var(--blue)}
.tb-alarm[aria-pressed="true"]{background:var(--blue);color:#fff;box-shadow:0 4px 14px rgba(17,99,240,.35)}
.tb-alarm[aria-pressed="true"]:hover{background:var(--blue)}
.tb-alarm[aria-pressed="true"] .st{background:rgba(255,255,255,.22);color:#fff}
.tb-alarm[aria-pressed="true"] .on-i{display:block}
.tb-alarm[aria-pressed="true"] .off-i{display:none}

/* layar sedang: jam turun ke baris sendiri, tetap di tengah */
@media (max-width:1200px){
  .tb{grid-template-columns:1fr auto;grid-template-areas:"title actions" "clock clock"}
  .tb h1{grid-area:title}
  .tb-actions{grid-area:actions}
  .tb-clock-wrap{grid-area:clock}
}
/* HP */
@media (max-width:640px){
  .tb{grid-template-columns:1fr;grid-template-areas:"title" "clock" "actions"}
  .tb-actions{justify-self:stretch}
  .tb .live-clock{padding:9px 14px;gap:10px}
  .tb .live-clock-date{font-size:12px}
  .tb-pop{width:min(272px,calc(100vw - 48px))}
}
@media (prefers-reduced-motion:reduce){.tb .live-clock,.tb-alarm{transition:none}}

/* ===== peringatan di tengah layar ===== */
.am{display:none;position:fixed;inset:0;z-index:100;align-items:center;justify-content:center;padding:20px;background:rgba(8,18,50,.58);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)}
.am.on{display:flex}
.am-card{width:min(440px,100%);background:var(--card);color:var(--ink);border-radius:24px;padding:28px 24px 22px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.35);border-top:7px solid var(--yellow)}
.am-card.bad{border-top-color:var(--red)}
.am-card.info{border-top-color:var(--blue)}
.am-ic{width:64px;height:64px;border-radius:50%;margin:0 auto 12px;display:grid;place-items:center;background:var(--yellow);color:#1a2342}
.am-card.bad .am-ic{background:var(--red);color:#fff;animation:amp 1s infinite}
.am-card.info .am-ic{background:var(--blue);color:#fff}
.am-ic svg{width:32px;height:32px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
@keyframes amp{50%{transform:scale(1.08)}}
.am-card h2{font-size:22px;font-weight:700;margin-bottom:6px}
.am-card p{color:var(--muted);font-size:13.5px;line-height:1.55;margin:0 auto 14px;max-width:340px}
.am-ring{position:relative;width:150px;height:150px;margin:0 auto 12px}
.am-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.am-ring circle{fill:none;stroke-width:9}
.am-ring .bg{stroke:var(--line)}
.am-ring .fg{stroke:var(--yellow);stroke-linecap:round;stroke-dasharray:339.3;stroke-dashoffset:0;transition:stroke-dashoffset .9s linear}
.am-ring div{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.1}
.am-ring b{font-size:46px;font-weight:700;font-variant-numeric:tabular-nums}
.am-ring span{font-size:12px;color:var(--muted)}
.am-meta{font-size:12.5px;color:var(--muted);margin-bottom:14px;min-height:18px}
@media (prefers-reduced-motion:reduce){.am-card.bad .am-ic{animation:none}.am-ring .fg{transition:none}}
</style>
<script>
const SUMMARY_URL=@json(route('dashboard.summary', array_filter(['crossing' => $selected])));
const $=id=>document.getElementById(id);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const DS={ACTIVE:['Aktif','t-ok'],DISCONNECTED:['Terputus','t-bad'],FAULT:['Gangguan','t-warn'],UNKNOWN:['Belum diketahui','t-mute']};
const SN={MPU6050:'MPU6050 (getaran rel)',LORA:'Modul LoRa Ra-02',ULTRASONIC_1:'Ultrasonik HC-SR04 #1',ULTRASONIC_2:'Ultrasonik HC-SR04 #2',SERVO:'Servo palang'};
let soundOn=false,ac=null;

$('snd').onclick=()=>{soundOn=!soundOn;$('snd').setAttribute('aria-pressed',soundOn);$('snd-st').textContent=soundOn?'Hidup':'Mati';if(soundOn)beep()};
function beep(){if(!soundOn)return;try{ac=ac||new (window.AudioContext||window.webkitAudioContext)();const o=ac.createOscillator(),g=ac.createGain();o.type='square';o.frequency.value=880;g.gain.value=.08;o.connect(g);g.connect(ac.destination);o.start();o.stop(ac.currentTime+.25)}catch(e){}}

function smooth(p){let d='M'+p[0][0]+','+p[0][1];for(let i=0;i<p.length-1;i++){const p0=p[i-1]||p[i],p1=p[i],p2=p[i+1],p3=p[i+2]||p2;d+=' C'+(p1[0]+(p2[0]-p0[0])/6)+','+(p1[1]+(p2[1]-p0[1])/6)+' '+(p2[0]-(p3[0]-p1[0])/6)+','+(p2[1]-(p3[1]-p1[1])/6)+' '+p2[0]+','+p2[1]}return d}

function drawDonut(m,a){const r=60,c=2*Math.PI*r,t=m+a,sw=18;
 $('dn').innerHTML=t?`<circle cx="80" cy="80" r="${r}" fill="none" stroke="var(--yellow)" stroke-width="${sw}"/><circle cx="80" cy="80" r="${r}" fill="none" stroke="var(--blue)" stroke-width="${sw}" stroke-dasharray="${(m/t*c).toFixed(2)} ${(c-m/t*c).toFixed(2)}"/>`:`<circle cx="80" cy="80" r="${r}" fill="none" stroke="var(--line)" stroke-width="${sw}" opacity=".4"/>`;
 $('dt').textContent=t;$('dm').textContent=m;$('da').textContent=a;}

function drawArea(h,nowH){
 const W=560,H=210,L=34,B=24,T=8,mx=Math.max(3,Math.ceil(Math.max(...h.today,...h.avg))),step=Math.ceil(mx/4);
 const x=i=>L+i*(W-L-10)/23,y=v=>T+(H-T-B)*(1-v/mx);let s='';
 for(let v=0;v<=mx;v+=step)s+=`<line x1="${L}" x2="${W-6}" y1="${y(v)}" y2="${y(v)}" stroke="var(--line)"/><text x="${L-8}" y="${y(v)+4}" text-anchor="end" font-size="10.5" fill="var(--muted)">${v}</text>`;
 const pa=h.avg.map((v,i)=>[x(i),y(v)]),pt=h.today.slice(0,nowH+1).map((v,i)=>[x(i),y(v)]);
 if(pt.length>1)s+=`<path d="${smooth(pt)} L${x(pt.length-1)},${y(0)} L${x(0)},${y(0)} Z" fill="var(--yellow)" opacity=".28"/><path d="${smooth(pt)}" fill="none" stroke="var(--yellow)" stroke-width="2.5"/>`;
 s+=`<path d="${smooth(pa)}" fill="none" stroke="var(--blue)" stroke-width="2.5"/>`;
 for(let i=0;i<24;i+=3)s+=`<text x="${x(i)}" y="${H-6}" text-anchor="middle" font-size="10.5" fill="var(--muted)">${String(i).padStart(2,'0')}</text>`;
 $('area').innerHTML=s}

function drawBars(w){const mx=Math.max(4,...w.map(d=>d.auto+d.manual));
 $('yl').innerHTML=[mx,Math.round(mx*2/3),Math.round(mx/3),0].map(v=>`<span>${v}</span>`).join('');
 document.querySelectorAll('#bars .col').forEach(e=>e.remove());
 $('bars').insertAdjacentHTML('beforeend',w.map(d=>{const t=d.auto+d.manual;return `<div class="col" style="height:${t/mx*100}%" title="${esc(d.label)}: ${d.auto} otomatis, ${d.manual} manual">${t?`<i style="height:${d.manual/t*100}%;background:var(--blue)"></i><i style="height:${d.auto/t*100}%;background:var(--yellow)"></i>`:''}</div>`}).join(''));
 $('xl').innerHTML=w.map(d=>`<span>${esc(d.label)}</span>`).join('')}

function drawGate(g,l){
 const st=g.state,closed=st==='CLOSED';
 const L={OPEN:['Palang terbuka','var(--green)','Tidak ada penutupan aktif'],
 PENDING:['Menunggu petugas','#d98a06','Kereta terdeteksi, sisa '+g.remaining+' dtk untuk menutup palang'],
 HELD:['Rel terhalang','var(--red)','Palang ditahan terbuka sampai rel kosong'],
 CLOSED:['Palang tertutup','var(--red)',g.mode==='AUTO'?'Ditutup otomatis, menunggu Node 1 memastikan kereta sudah lewat':'Ditutup manual oleh petugas']}[st];
 $('ll').textContent=L[0];$('ll').style.color=L[1];$('lsub').textContent=L[2];
 $('lb').style.width=(g.last_confidence||0)+'%';
 $('lrs').textContent=l.rssi!=null?l.rssi+' dBm':'-';$('lds').textContent=l.distance_cm!=null?Math.round(l.distance_cm)+' cm':'-';$('lbat').textContent=l.battery!=null?l.battery+'%':'-'}

/* ===== peringatan di tengah layar ===== */
const ICON={train:'<svg viewBox="0 0 24 24"><rect x="6" y="3" width="12" height="14" rx="3"/><path d="M6 11h12M9 21l2-4M15 21l-2-4"/><circle cx="9.5" cy="14" r=".6"/><circle cx="14.5" cy="14" r=".6"/></svg>',
 block:'<svg viewBox="0 0 24 24"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.01"/></svg>',
 ok:'<svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>'};
const dismissed=new Set();let curKey=null,alertTimer=null;

function hideAlert(){
 clearTimeout(alertTimer);
 alertTimer=null;
 if(curKey)dismissed.add(curKey);
 $('am').classList.remove('on');
}

$('am-close').onclick=hideAlert;
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('am').classList.contains('on'))hideAlert()});

function drawAlert(a){
 const m=$('am');
 if(!a){clearTimeout(alertTimer);alertTimer=null;m.classList.remove('on');curKey=null;return}
 const key=a.type+a.id;
 if(dismissed.has(key)){m.classList.remove('on');return}
 const card=$('am-card'),ring=$('am-ring');
 if(a.type==='COUNTDOWN'){
  card.className='am-card warn';$('am-ic').innerHTML=ICON.train;$('am-title').textContent='Kereta terdeteksi!';
  $('am-text').textContent=a.remaining>0?'Tutup palang sekarang dengan tombol di pos. Jika tidak ada respons, palang menutup otomatis saat waktu habis.':'Waktu habis. Sistem menutup palang otomatis.';
  ring.style.display='';$('am-num').textContent=a.remaining;$('am-arc').style.strokeDashoffset=(339.3*(1-Math.max(0,a.remaining)/Math.max(1,a.total))).toFixed(1);
  $('am-meta').textContent=a.confidence!=null?'Keyakinan TinyML '+a.confidence+'%':'';beep();
 }else if(a.type==='OBSTACLE'){
  card.className='am-card bad';$('am-ic').innerHTML=ICON.block;$('am-title').textContent='Rel terhalang!';
  $('am-text').textContent='Ada kendaraan atau benda di area rel. Palang ditahan terbuka dan sirine darurat menyala. Segera kosongkan rel.';
  ring.style.display='none';$('am-meta').textContent='Terdeteksi '+a.age+' detik lalu. Peringatan hilang otomatis setelah rel kosong dan palang menutup.';beep();
 }else{
  card.className='am-card info';$('am-ic').innerHTML=ICON.ok;ring.style.display='none';
  if(a.mode==='MANUAL'){$('am-title').textContent='Palang ditutup petugas';$('am-text').textContent=a.response_ms!=null?'Petugas menutup palang dalam '+(a.response_ms/1000).toFixed(1)+' detik.':'Palang ditutup dengan tombol di pos.'}
  else{$('am-title').textContent='Palang ditutup otomatis';$('am-text').textContent=a.guard==='NO_RESPONSE'?'Petugas tidak merespons dalam '+a.total+' detik, sistem menutup palang.':'Kereta terdeteksi, palang ditutup otomatis.'}
  $('am-meta').textContent='';
 }
 m.classList.add('on');
 if(curKey!==key){
  curKey=key;
  clearTimeout(alertTimer);
  alertTimer=null;
  $('am-close').focus();
  // Modal informasi (palang ditutup) otomatis hilang setelah 4 detik
  if(a.type!=='COUNTDOWN'&&a.type!=='OBSTACLE'){
   alertTimer=setTimeout(hideAlert,4000);
  }
 }
 // Jika hitung mundur sudah 0 detik, otomatis hilangkan setelah 3 detik
 if(a.type==='COUNTDOWN'&&a.remaining<=0&&!alertTimer){
  alertTimer=setTimeout(hideAlert,3000);
 }
}

function drawDevs(a){$('devs').innerHTML=a.length?a.map(d=>{const s=DS[d.status];const extra=d.ml_label?` · TinyML ${esc(d.ml_label)} ${Number(d.ml_confidence||0).toFixed(1)}%`:d.distance_cm!=null?` · jarak ${Number(d.distance_cm).toFixed(0)} cm`:'';return `<div class="item"><div class="ic ${d.type==='GATE_CONTROLLER'?'y':''}"><svg viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 18v2M16 18v2"/></svg></div><div><b>${esc(d.name||d.code)}</b><small>${esc(d.code)} · ${esc(d.crossing)}${d.battery!=null?' · baterai '+d.battery+'%':''}${extra}</small></div><div class="r"><span class="tag ${s[1]}">${s[0]}</span>${d.seen_s!=null?`<small>lapor ${d.seen_s} dtk lalu</small>`:''}</div></div>`}).join(''):'<p class="muted">Belum ada perangkat.</p>'}

function drawSens(a){$('sens').innerHTML=a.length?a.map(s=>{let t=['Aktif','t-ok'];if(s.health==='FAULT')t=['Gangguan','t-bad'];else if(s.health==='UNKNOWN')t=['Belum diketahui','t-mute'];else if(s.state==='STANDBY')t=['Siaga','t-mute'];else if(s.state==='OFF')t=['Mati','t-mute'];
 return `<div class="item"><div><b>${esc(SN[s.type]||s.type)}</b><small>${esc(s.detail||s.device)}</small></div><div class="r"><span class="tag ${t[1]}">${t[0]}</span></div></div>`}).join(''):'<p class="muted">Belum ada laporan sensor. Laporan dikirim lewat /api/heartbeat.</p>'}

function drawRows(a){$('rows').innerHTML=a.length?a.map(e=>`<tr onclick="location.href='${esc(e.url)}'"><td>${esc(e.date)} ${esc(e.time)}</td><td>${e.trigger==='AUTOMATIC'?'Otomatis':'Manual'}</td><td>${e.confidence!=null?e.confidence+'%':'-'}</td><td><span class="tag ${esc(e.tone)}">${esc(e.status)}</span></td><td>${esc(e.notes||'')}</td></tr>`).join(''):'<tr><td colspan="5">Belum ada log.</td></tr>'}

function render(d){
 $('k1').textContent=d.kpi.total;$('k2').textContent=d.kpi.auto;$('k3').textContent=d.kpi.manual;$('k4').textContent=d.kpi.avg_conf!=null?d.kpi.avg_conf+'%':'-';
 drawDonut(d.kpi.manual,d.kpi.auto);drawArea(d.hourly,d.now_hour);drawBars(d.weekly);drawGate(d.gate,d.live);drawAlert(d.alert);drawDevs(d.devices);drawSens(d.sensors);drawRows(d.recent)}

async function load(){let next=3000;
 try{const r=await fetch(SUMMARY_URL,{headers:{'Accept':'application/json'},credentials:'same-origin'});
  if(r.status===401||r.status===419){location.href='{{ route('login') }}';return}
  const d=await r.json();render(d);if(d.alert||d.gate.state!=='OPEN')next=1000}catch(e){next=5000}
 setTimeout(load,next)}

/* jam dan tanggal selalu dalam WIB, apa pun zona waktu perangkat */
const TZ={timeZone:'Asia/Jakarta'};
const jkt=()=>new Date(new Date().toLocaleString('en-US',TZ));
(function updateClock(){
  const now=new Date();
  $('clock-time').textContent=now.toLocaleTimeString('id-ID',{...TZ,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false})+' WIB';
  $('clock-date').textContent=now.toLocaleDateString('id-ID',{...TZ,weekday:'short',day:'numeric',month:'short',year:'numeric'});
  setTimeout(updateClock,1000);
})();

(function initCalendar(){
  const pop=$('cal-popup'),btn=$('cal-btn'),today=jkt();
  let y=today.getFullYear(),m=today.getMonth();

  const setOpen=o=>{pop.classList.toggle('on',o);btn.setAttribute('aria-expanded',o)};
  btn.onclick=e=>{e.stopPropagation();setOpen(!pop.classList.contains('on'))};
  document.addEventListener('click',e=>{if(!btn.contains(e.target)&&!pop.contains(e.target))setOpen(false)});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});

  function draw(){
    $('tbcm').textContent=new Date(y,m,1).toLocaleDateString('id-ID',{month:'long',year:'numeric'});
    const first=(new Date(y,m,1).getDay()+6)%7,days=new Date(y,m+1,0).getDate(),prev=new Date(y,m,0).getDate();
    let h=['Sen','Sel','Rab','Kam','Jum','Sab','Min'].map(d=>`<span class="h">${d}</span>`).join('');
    for(let i=first-1;i>=0;i--)h+=`<span class="o">${prev-i}</span>`;
    for(let d=1;d<=days;d++)h+=`<span class="${d===today.getDate()&&m===today.getMonth()&&y===today.getFullYear()?'today':''}">${d}</span>`;
    $('tbcg').innerHTML=h;
  }
  $('tbprev').onclick=e=>{e.stopPropagation();m--;if(m<0){m=11;y--}draw()};
  $('tbnext').onclick=e=>{e.stopPropagation();m++;if(m>11){m=0;y++}draw()};
  draw();
})();
load();
</script>
@endpush