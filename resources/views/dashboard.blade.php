@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="top">
  <h1>Dashboard</h1>
  @if($crossings->count() > 1)
  <form method="GET" action="{{ route('dashboard') }}" class="f" style="min-width:200px">
    <select name="crossing" onchange="this.form.submit()" aria-label="Pilih pos">
      <option value="">Semua pos</option>
      @foreach($crossings as $c)<option value="{{ $c->id }}" @selected($selected === $c->id)>{{ $c->code }}</option>@endforeach
    </select>
  </form>
  @endif
  <button class="btn ghost" id="snd" type="button">Suara alarm: mati</button>
</div>

<div class="abn" id="abn" role="alert"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.01"/></svg><div><div>Kereta terdeteksi, menunggu respons petugas</div><small id="abt"></small></div><big id="abc">0</big></div>

<section class="kpis" aria-label="Ringkasan hari ini">
  <div class="kpi"><b id="k1">0</b><span>Deteksi kereta hari ini</span></div>
  <div class="kpi"><b id="k2">0</b><span>Tanpa respons petugas</span></div>
  <div class="kpi"><b id="k3">0</b><span>Rintangan terdeteksi</span></div>
  <div class="kpi blue"><b id="k4">0%</b><span>Palang ditutup manual</span></div>
</section>

<section class="g g2">
  <div class="card">
    <div class="ch"><h2>Mode penutupan palang</h2><span class="pill">Hari ini</span></div>
    <div class="donut"><svg viewBox="0 0 120 120" id="dn"></svg><div><small>Total penutupan</small><b id="dt">0</b></div></div>
    <div class="dl"><div><b id="dm">0</b><span>Manual</span></div><div><b id="da">0</b><span>Otomatis</span></div></div>
  </div>
  <div class="card">
    <div class="ch"><h2>Deteksi per jam</h2><span class="pill">00.00 sampai 23.00</span></div>
    <div class="lg"><span><i style="background:var(--yellow)"></i>Hari ini</span><span><i style="background:var(--blue)"></i>Rata-rata 7 hari</span></div>
    <svg id="area" viewBox="0 0 560 210" style="width:100%;height:auto" role="img" aria-label="Grafik deteksi per jam hari ini dibanding rata-rata 7 hari"></svg>
  </div>
</section>

<section class="g g3">
  <div class="card">
    <div class="ch"><h2>Telemetri sensor getaran</h2><span class="pill" id="lvu">Live</span></div>
    <div class="live-label" id="ll">-</div>
    <div class="muted" style="font-size:12px;margin-top:2px">Keyakinan model TinyML</div>
    <div class="pbar"><i id="lb" style="width:0"></i></div>
    <div class="mini">
      <div><b id="lbat">-</b><span>Baterai Node 1</span></div>
      <div><b id="lrs">-</b><span>RSSI LoRa</span></div>
      <div><b id="lds">-</b><span>Jarak HC-SR04</span></div>
    </div>
  </div>
  <div class="card">
    <div class="ch"><h2>Respons petugas, 7 hari</h2><span class="pill">Mingguan</span></div>
    <div class="lg"><span><i style="background:var(--yellow)"></i>Merespons</span><span><i style="background:var(--blue)"></i>Tanpa respons</span></div>
    <div class="bars" id="bars"><div class="yl" id="yl"></div></div>
    <div class="xl" id="xl"></div>
  </div>
</section>

<section class="g g3">
  <div class="card"><div class="ch"><h2>Perangkat</h2><span class="pill">Heartbeat</span></div><div id="devs"></div></div>
  <div class="card"><div class="ch"><h2>Kesehatan sensor</h2></div><div id="sens"></div></div>
</section>

<section class="card">
  <div class="ch"><h2>Kejadian terbaru</h2><a class="btn sm ghost" href="{{ route('events.index') }}">Lihat semua</a></div>
  <div class="tw"><table><thead><tr><th>Waktu</th><th>ID</th><th>Kejadian</th><th>Mode</th><th>Probabilitas</th><th>Respons petugas</th><th>Rintangan</th></tr></thead><tbody id="rows"></tbody></table></div>
</section>
@endsection

@section('right')
<div class="up"><h3>Kejadian perlu ditinjau</h3><div id="ue"></div></div>
@endsection

@push('scripts')
<script>
const SUMMARY_URL=@json(route('dashboard.summary', array_filter(['crossing' => $selected])));
const $=id=>document.getElementById(id);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const LB={TRAIN_INBOUND:['Menunggu respons','t-warn'],MANUAL_OK:['Palang ditutup petugas','t-ok'],FAIL_SAFE_AUTOGATE:['Fail-safe palang otomatis','t-warn'],CRITICAL_OBSTACLE:['Rintangan, palang ditahan','t-bad']};
const ML={IDLE:['Idle','var(--muted)'],TRUK:['Truk','var(--yellow)'],KERETA:['Kereta','var(--red)']};
const DS={ACTIVE:['Aktif','t-ok'],DISCONNECTED:['Terputus','t-bad'],FAULT:['Gangguan','t-warn'],UNKNOWN:['Belum diketahui','t-mute']};
const SN={MPU6050:'MPU6050 (getaran rel)',LORA:'Modul LoRa Ra-02',ULTRASONIC_1:'Ultrasonik HC-SR04 #1',ULTRASONIC_2:'Ultrasonik HC-SR04 #2',SERVO:'Servo palang'};
let soundOn=false,ac=null;

$('snd').onclick=()=>{soundOn=!soundOn;$('snd').textContent='Suara alarm: '+(soundOn?'hidup':'mati');if(soundOn)beep()};
function beep(){if(!soundOn)return;try{ac=ac||new (window.AudioContext||window.webkitAudioContext)();const o=ac.createOscillator(),g=ac.createGain();o.type='square';o.frequency.value=880;g.gain.value=.08;o.connect(g);g.connect(ac.destination);o.start();o.stop(ac.currentTime+.25)}catch(e){}}

function smooth(p){let d='M'+p[0][0]+','+p[0][1];for(let i=0;i<p.length-1;i++){const p0=p[i-1]||p[i],p1=p[i],p2=p[i+1],p3=p[i+2]||p2;d+=' C'+(p1[0]+(p2[0]-p0[0])/6)+','+(p1[1]+(p2[1]-p0[1])/6)+' '+(p2[0]-(p3[0]-p1[0])/6)+','+(p2[1]-(p3[1]-p1[1])/6)+' '+p2[0]+','+p2[1]}return d}

function drawDonut(m,a){const r=44,c=2*Math.PI*r,t=m+a;
 $('dn').innerHTML=t?`<circle cx="60" cy="60" r="${r}" fill="none" stroke="var(--yellow)" stroke-width="22"/><circle cx="60" cy="60" r="${r}" fill="none" stroke="var(--blue)" stroke-width="22" stroke-dasharray="${m/t*c} ${c-m/t*c}"/>`:`<circle cx="60" cy="60" r="${r}" fill="none" stroke="var(--line)" stroke-width="22"/>`;
 $('dt').textContent=t;$('dm').innerHTML=m+' <small class="muted"> manual</small>';$('da').innerHTML=a+' <small class="muted"> otomatis</small>';}

function drawArea(h,nowH){
 const W=560,H=210,L=34,B=24,T=8,mx=Math.max(3,Math.ceil(Math.max(...h.today,...h.avg))),step=Math.ceil(mx/4);
 const x=i=>L+i*(W-L-10)/23,y=v=>T+(H-T-B)*(1-v/mx);let s='';
 for(let v=0;v<=mx;v+=step)s+=`<line x1="${L}" x2="${W-6}" y1="${y(v)}" y2="${y(v)}" stroke="var(--line)"/><text x="${L-8}" y="${y(v)+4}" text-anchor="end" font-size="10.5" fill="var(--muted)">${v}</text>`;
 const pa=h.avg.map((v,i)=>[x(i),y(v)]),pt=h.today.slice(0,nowH+1).map((v,i)=>[x(i),y(v)]);
 if(pt.length>1)s+=`<path d="${smooth(pt)} L${x(pt.length-1)},${y(0)} L${x(0)},${y(0)} Z" fill="var(--yellow)" opacity=".28"/><path d="${smooth(pt)}" fill="none" stroke="var(--yellow)" stroke-width="2.5"/>`;
 s+=`<path d="${smooth(pa)}" fill="none" stroke="var(--blue)" stroke-width="2.5"/>`;
 for(let i=0;i<24;i+=3)s+=`<text x="${x(i)}" y="${H-6}" text-anchor="middle" font-size="10.5" fill="var(--muted)">${String(i).padStart(2,'0')}</text>`;
 $('area').innerHTML=s}

function drawBars(w){const mx=Math.max(4,...w.map(d=>d.total));
 $('yl').innerHTML=[mx,Math.round(mx*2/3),Math.round(mx/3),0].map(v=>`<span>${v}</span>`).join('');
 document.querySelectorAll('#bars .col').forEach(e=>e.remove());
 $('bars').insertAdjacentHTML('beforeend',w.map(d=>`<div class="col" style="height:${d.total/mx*100}%" title="${esc(d.label)}: ${d.total} deteksi, ${d.no_response} tanpa respons">${d.total?`<i style="height:${d.no_response/d.total*100}%;background:var(--blue)"></i><i style="height:${(d.total-d.no_response)/d.total*100}%;background:var(--yellow)"></i>`:''}</div>`).join(''));
 $('xl').innerHTML=w.map(d=>`<span>${esc(d.label)}</span>`).join('')}

function drawLive(l){const m=ML[l.ml_label];
 $('ll').textContent=m?m[0]:'Tidak ada data';$('ll').style.color=m?m[1]:'var(--muted)';
 $('lb').style.width=(l.ml_confidence||0)+'%';$('lvu').textContent=m?'Live':'Offline';
 $('lbat').textContent=l.battery!=null?l.battery+'%':'-';$('lrs').textContent=l.rssi!=null?l.rssi+' dBm':'-';$('lds').textContent=l.distance_cm!=null?Math.round(l.distance_cm)+' cm':'-'}

function drawDevs(a){$('devs').innerHTML=a.length?a.map(d=>{const s=DS[d.status];return `<div class="item"><div class="ic ${d.type==='GATE_CONTROLLER'?'y':''}"><svg viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 18v2M16 18v2"/></svg></div><div><b>${esc(d.name||d.code)}</b><small>${esc(d.code)} · ${esc(d.crossing)}${d.battery!=null?' · baterai '+d.battery+'%':''}</small></div><div class="r"><span class="tag ${s[1]}">${s[0]}</span>${d.seen_s!=null?`<small>lapor ${d.seen_s} dtk lalu</small>`:''}</div></div>`}).join(''):'<p class="muted">Belum ada perangkat.</p>'}

function drawSens(a){$('sens').innerHTML=a.length?a.map(s=>{let t=['Sehat','t-ok'];if(s.health==='FAULT')t=['Gangguan','t-bad'];else if(s.health==='UNKNOWN')t=['Belum diketahui','t-mute'];else if(s.state==='STANDBY')t=['Siaga','t-mute'];else if(s.state==='OFF')t=['Mati','t-mute'];
 return `<div class="item"><div><b>${esc(SN[s.type]||s.type)}</b><small>${esc(s.detail||s.device)}</small></div><div class="r"><span class="tag ${t[1]}">${t[0]}</span></div></div>`}).join(''):'<p class="muted">Belum ada laporan sensor.</p>'}

function drawRows(a){$('rows').innerHTML=a.length?a.map(e=>{const l=LB[e.status]||LB.TRAIN_INBOUND;
 const resp=!e.guard?'-':e.guard==='CONFIRMED'?'Tombol ditekan'+(e.response_ms!=null?' ('+(e.response_ms/1000).toFixed(1)+' dtk)':''):'Tidak ada respons';
 return `<tr onclick="location.href='${esc(e.url)}'"><td>${esc(e.date)} ${esc(e.time)}</td><td class="mono">${esc(e.id)}</td><td><span class="tag ${l[1]}">${l[0]}</span></td><td>${e.mode?(e.mode==='MANUAL'?'Manual':'Otomatis'):'-'}</td><td>${e.confidence!=null?e.confidence+'%':'-'}</td><td>${resp}</td><td>${e.obstacle===true?'<span class="tag t-bad">Terdeteksi</span>':e.obstacle===false?'<span class="tag t-mute">Bebas</span>':'-'}</td></tr>`}).join(''):'<tr><td colspan="7">Belum ada kejadian.</td></tr>'}

function drawReview(a){$('ue').innerHTML=a.length?a.map(e=>`<a class="ue" style="display:block;text-decoration:none;color:inherit" href="${esc(e.url)}"><span class="d">${esc(e.date)}</span><span class="tm">${esc(e.time)} WIB</span><b>${(LB[e.status]||LB.TRAIN_INBOUND)[0]}</b><small>${esc(e.id)} · tambahkan catatan evaluasi</small></a>`).join(''):'<p class="muted">Tidak ada kejadian tanpa respons.</p>'}

function render(d){
 $('k1').textContent=d.kpi.total;$('k2').textContent=d.kpi.no_response;$('k3').textContent=d.kpi.obstacle;$('k4').textContent=d.kpi.manual_pct+'%';
 drawDonut(d.kpi.manual,d.kpi.auto);drawArea(d.hourly,d.now_hour);drawBars(d.weekly);drawLive(d.live);drawDevs(d.devices);drawSens(d.sensors);drawRows(d.recent);drawReview(d.review);
 const a=d.alert;$('abn').classList.toggle('on',!!a);
 if(a){$('abc').textContent=a.remaining;$('abt').textContent=a.event_id+(a.confidence!=null?' · keyakinan '+a.confidence+'%':'')+' · palang menutup otomatis jika tidak ada respons';beep()}}

async function load(){let next=3000;
 try{const r=await fetch(SUMMARY_URL,{headers:{'Accept':'application/json'},credentials:'same-origin'});
  if(r.status===401||r.status===419){location.href='{{ route('login') }}';return}
  const d=await r.json();render(d);if(d.alert)next=1000}catch(e){next=5000}
 setTimeout(load,next)}
load();
</script>
@endpush
