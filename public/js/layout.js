(function(){
  var $ = function(id){return document.getElementById(id)};
  var clock = $('clock');
  if(clock){ var tick=function(){ var d=new Date(); clock.innerHTML='<b>'+d.toLocaleTimeString('id-ID')+'</b> · '+d.toLocaleDateString('id-ID',{weekday:'short',day:'numeric',month:'short',year:'numeric'}); }; tick(); setInterval(tick,1000); }

  var cal=$('cal'); if(!cal) return;
  var view=new Date(); view.setDate(1);
  function draw(){
    var y=view.getFullYear(), m=view.getMonth(), today=new Date();
    $('calTitle').textContent=view.toLocaleDateString('id-ID',{month:'long',year:'numeric'});
    var html=['Min','Sen','Sel','Rab','Kam','Jum','Sab'].map(function(n){return '<span class="dn">'+n+'</span>'}).join('');
    var first=new Date(y,m,1).getDay(), days=new Date(y,m+1,0).getDate(), prev=new Date(y,m,0).getDate();
    for(var i=first-1;i>=0;i--) html+='<span class="mu">'+(prev-i)+'</span>';
    for(var d=1;d<=days;d++){
      var t=(d===today.getDate()&&m===today.getMonth()&&y===today.getFullYear());
      html+='<span class="'+(t?'today':'')+'">'+d+'</span>';
    }
    cal.innerHTML=html;
  }
  $('calPrev').onclick=function(){view.setMonth(view.getMonth()-1);draw()};
  $('calNext').onclick=function(){view.setMonth(view.getMonth()+1);draw()};
  draw();
})();
