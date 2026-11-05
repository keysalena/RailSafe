<aside class="rightbar">
    <div class="profile">
        <div class="avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
        <div><b>{{ auth()->user()->name }}</b><small>{{ ucfirst(auth()->user()->role) }}</small></div>
        <div class="bell" title="Alarm aktif">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <span class="dot" id="bellDot"></span>
        </div>
    </div>

    <div>
        <div class="cal-head">
            <span id="calTitle"></span>
            <span><button id="calPrev" type="button">‹</button> <button id="calNext" type="button">›</button></span>
        </div>
        <div class="cal" id="cal"></div>
    </div>

    <div>
        <h4>Alert Terbaru</h4>
        <div id="recentAlerts"><div class="empty">Belum ada alert.</div></div>
    </div>
</aside>
