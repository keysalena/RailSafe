@extends('layouts.app')

@section('title', 'Real-Time Monitoring')
@section('page_title', 'Real-Time Monitoring')

@section('content')
    <div class="alarm" id="alarm">
        <div><b>⚠ PERINGATAN: KERETA TERDETEKSI MENDEKAT (ALERT_KERETA)</b><div id="alarmInfo" style="font-size:13px;margin-top:4px"></div></div>
        <button id="ackBtn">Konfirmasi &amp; Matikan Alarm</button>
    </div>

    <div class="kpis">
        <div class="kpi"><div class="v" id="kClass">-</div><div class="l">Status Getaran Rel</div></div>
        <div class="kpi"><div class="v" id="kConf">-</div><div class="l">Confidence Score</div></div>
        <div class="kpi"><div class="v" id="kBat">-</div><div class="l">Baterai Node 1 (2S2P)</div></div>
        <div class="kpi b"><div class="v" id="kRssi">-</div><div class="l">LoRa RSSI</div></div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Live Status Telemetri Sensor (TinyML)</h3>
            <div class="chips">
                <div class="chip" data-c="idle">Idle</div>
                <div class="chip" data-c="truk">Truk</div>
                <div class="chip kereta" data-c="kereta">Kereta</div>
            </div>
            <svg class="trend" viewBox="0 0 400 90" preserveAspectRatio="none"><polyline id="trend" fill="none" stroke="#0b63f6" stroke-width="2.5"/></svg>
            <div class="mut">Tren confidence 20 pembacaan terakhir · <span id="lastSeen">-</span></div>
        </div>

        <div class="card">
            <h3>Kesehatan Hardware</h3>
            <div class="row"><span>Daya baterai</span><b id="hBat">-</b></div><div class="bar"><div id="bBat"></div></div>
            <div class="row"><span>Koneksi LoRa (RSSI)</span><b id="hRssi">-</b></div><div class="bar"><div id="bRssi"></div></div>
            <div class="row"><span>Jarak HC-SR04</span><b id="hDist">-</b></div>
        </div>

        <div class="card full">
            <h3>Panel Kontrol Palang &amp; Manual Override</h3>
            <div class="row" style="align-items:center">
                <div>
                    <div class="big" id="gState" style="text-transform:uppercase">-</div>
                    <div class="mut">Mode: <span id="gMode">-</span> · <span id="gPending"></span></div>
                </div>
                <div class="mut" style="text-align:right">Perintah dikirim ke Node 2.<br>Gunakan saat kondisi darurat / fail-safe.</div>
            </div>
            <div class="gate">
                <button class="open" id="btnOpen">Buka Palang</button>
                <button class="close" id="btnClose">Tutup Palang</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@verbatim
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const $ = id => document.getElementById(id);
const hdr = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf};
let currentAlert = null, sirenTimer = null, ctx = null;

function siren(on){
  if(on && !sirenTimer){
    try{ ctx = ctx || new (window.AudioContext||window.webkitAudioContext)(); }catch(e){ return; }
    let hi = false;
    sirenTimer = setInterval(()=>{
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.type='square'; o.frequency.value = (hi=!hi) ? 960 : 700; g.gain.value = .15;
      o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime+.4);
    }, 450);
  } else if(!on && sirenTimer){ clearInterval(sirenTimer); sirenTimer=null; }
}
// Browser memblokir audio sebelum ada interaksi -> aktifkan pada klik pertama
document.addEventListener('click', ()=>{ try{ ctx = ctx || new (window.AudioContext||window.webkitAudioContext)(); ctx.resume(); }catch(e){} }, {once:true});

function render(d){
  const t = d.telemetry, h = d.hardware;
  document.querySelectorAll('.chip').forEach(c=>c.classList.toggle('on', !!t && c.dataset.c===t.vibration_class));
  $('kClass').textContent = t ? t.vibration_class : '-';
  $('kConf').textContent = t ? t.confidence.toFixed(1)+'%' : '-';
  $('lastSeen').textContent = t ? 'update '+new Date(t.recorded_at).toLocaleTimeString('id-ID') : 'belum ada data';

  if(h){
    $('kBat').textContent = h.battery_percent!=null ? h.battery_percent+'%' : '-';
    $('hBat').textContent = h.battery_percent!=null ? h.battery_percent+'%'+(h.battery_voltage?' ('+h.battery_voltage+' V)':'') : '-';
    $('bBat').style.width = (h.battery_percent||0)+'%';
    $('kRssi').textContent = h.lora_rssi!=null ? h.lora_rssi+' dBm' : '-';
    $('hRssi').textContent = h.lora_rssi!=null ? h.lora_rssi+' dBm ('+h.lora_quality+')' : '-';
    $('bRssi').style.width = h.lora_rssi!=null ? Math.max(0,Math.min(100,(h.lora_rssi+130)*100/60))+'%' : '0';
    $('hDist').textContent = h.distance_cm!=null ? h.distance_cm+' cm' : '-';
  }

  const pts = d.trend.map((p,i,a)=> (a.length>1? i*400/(a.length-1):0)+','+(85-p.confidence*0.8));
  $('trend').setAttribute('points', pts.join(' '));

  // Navbar: status node
  const pill = (n,x)=> x ? `<span class="pill ${x.online?'ok':'bad'}">${n} ${x.online?'online':'offline'}</span>` : '';
  $('nodes').innerHTML = pill('Node 1', d.devices.node1)+' '+pill('Node 2', d.devices.node2);

  $('gState').textContent = d.gate.state; $('gMode').textContent = d.gate.mode;
  $('gPending').textContent = d.gate.pending ? 'menunggu eksekusi perangkat…' : '';

  // Alarm (banner + sirine + lonceng di right bar)
  currentAlert = d.alert;
  $('alarm').classList.toggle('show', !!d.alert);
  $('bellDot').classList.toggle('show', !!d.alert);
  if(d.alert){ $('alarmInfo').textContent = 'Event '+d.alert.event_id+' · '+new Date(d.alert.triggered_at).toLocaleTimeString('id-ID')+(d.alert.confidence?' · confidence '+d.alert.confidence+'%':''); }
  siren(!!d.alert);

  // Right bar: alert terbaru
  $('recentAlerts').innerHTML = d.recent_alerts.length ? d.recent_alerts.map(a=>`
    <div class="act">
      <div class="t"><span class="tag">${new Date(a.triggered_at).toLocaleDateString('id-ID',{day:'numeric',month:'short'})} · ${new Date(a.triggered_at).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})}</span>
      <span class="pill ${a.status==='active'?'bad':'ok'}">${a.status==='active'?'aktif':'selesai'}</span></div>
      <div><b>Kereta terdeteksi</b></div><div class="s">${a.event_id}${a.confidence?' · '+a.confidence+'%':''}</div>
    </div>`).join('') : '<div class="empty">Belum ada alert.</div>';
}

async function poll(){
  try{
    const r = await fetch('/api/dashboard/live',{headers:hdr,credentials:'same-origin'});
    if(r.status===401||r.status===419) return location.reload();
    render(await r.json());
  }catch(e){ $('nodes').innerHTML='<span class="pill warn">Koneksi server terputus</span>'; }
}

$('ackBtn').onclick = async ()=>{
  if(!currentAlert) return;
  await fetch('/api/dashboard/alerts/'+currentAlert.id+'/acknowledge',{method:'POST',headers:hdr,credentials:'same-origin'});
  siren(false); poll();
};
async function gate(cmd){
  const label = cmd==='close' ? 'MENUTUP' : 'MEMBUKA';
  if(!confirm('Yakin ingin '+label+' palang secara manual?')) return;
  [$('btnOpen'),$('btnClose')].forEach(b=>b.disabled=true);
  await fetch('/api/dashboard/gate/command',{method:'POST',headers:hdr,credentials:'same-origin',body:JSON.stringify({command:cmd})});
  [$('btnOpen'),$('btnClose')].forEach(b=>b.disabled=false); poll();
}
$('btnOpen').onclick=()=>gate('open'); $('btnClose').onclick=()=>gate('close');

poll(); setInterval(poll, 2000);
</script>
@endverbatim
@endpush
