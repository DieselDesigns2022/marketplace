'use strict';
(() => {
 const root=document.getElementById('live-giveaway'),status=document.getElementById('drawing-status'),stage=document.getElementById('drawing-stage');
 const canvas=document.getElementById('final-wheel'),ctx=canvas.getContext('2d'),prize=document.getElementById('drawing-prize'),result=document.getElementById('drawing-result'),list=document.getElementById('drawing-winners');
 const modal=document.getElementById('winner-popup'),remove=document.getElementById('winner-remove'),close=document.getElementById('winner-close');
 // Sampled from the installed Creative Moth logo: blue, violet, cyan, navy.
 const ink='#011a4b',paper='#ffffff',colors=['#2060fb','#7148f9','#19c2e4'];
 let entries=[],signature='',angle=0,spinning=false,lastFrame=0,settle=null,latest=null,activeId=null,announced=new Set(),busy=false,spinDeadline=0,spinId=null;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
 function paint(){
  ctx.clearRect(0,0,900,900);ctx.save();ctx.translate(450,450);ctx.rotate(angle);
  const n=entries.length,step=n?2*Math.PI/n:2*Math.PI;
  if(!n){ctx.fillStyle=paper;ctx.beginPath();ctx.arc(0,0,440,0,Math.PI*2);ctx.fill();}
  for(let i=0;i<n;i++){
   const start=-Math.PI/2+i*step;ctx.beginPath();ctx.moveTo(0,0);ctx.arc(0,0,440,start,start+step);ctx.closePath();ctx.fillStyle=colors[i%colors.length];ctx.fill();ctx.strokeStyle=ink;ctx.lineWidth=1;ctx.stroke();
   ctx.save();ctx.rotate(start+step/2);ctx.fillStyle=i%3===2?ink:'#ffffff';ctx.textAlign='right';ctx.textBaseline='middle';ctx.font='bold '+Math.max(10,Math.min(30,500/n))+'px sans-serif';
   const label=entries[i].name+' · '+entries[i].key;ctx.fillText(label,415,0,285);ctx.restore();
  }
  ctx.restore();ctx.beginPath();ctx.arc(450,450,100,0,Math.PI*2);ctx.fillStyle=ink;ctx.fill();ctx.fillStyle='#ffffff';ctx.textAlign='center';ctx.font='bold 27px sans-serif';ctx.fillText('Creative',450,442);ctx.fillText('Moth',450,477);
 }
 function announce(){
  if(!latest||latest.remaining>0||latest.removed||announced.has(latest.id))return;
  announced.add(latest.id);document.getElementById('winner-popup-title').textContent=latest.winner+' Won!';
  document.getElementById('winner-popup-prize').textContent=latest.prize;
  document.getElementById('winner-popup-prompt').textContent=root.dataset.removeUrl?'Remove entry? Only this winning ticket will be removed. Their other entries stay.':'Congratulations to the winner!';
  document.getElementById('winner-popup-error').textContent='';if(remove){remove.hidden=!root.dataset.removeUrl;remove.disabled=false;}
  if(!modal.open)modal.showModal();
 }
 function frame(time){
  const delta=lastFrame?Math.min(time-lastFrame,100):0;lastFrame=time;
  if(settle){const progress=Math.min(1,(time-settle.start)/settle.duration);angle=settle.from+(settle.to-settle.from)*(1-Math.pow(1-progress,3));if(progress===1){settle=null;announce();}}
  else if(spinning&&!reduced&&time<spinDeadline)angle+=delta*0.007;
  paint();requestAnimationFrame(frame);
 }
 function land(){
  const index=entries.findIndex(e=>e.key===latest.ticket);if(index<0){announce();return;}
  const tau=Math.PI*2,target=(-(index+.5)*tau/entries.length%tau+tau)%tau,current=(angle%tau+tau)%tau;
  const distance=(target-current+tau)%tau;
  const duration=Math.max(0,Math.min(1000,spinDeadline-performance.now()));
  if(reduced||duration===0){angle+=distance;announce();}else settle={from:angle,to:angle+tau+distance,start:performance.now(),duration};
 }
 close.addEventListener('click',()=>modal.close());
 document.getElementById('show-winner').addEventListener('click',()=>{if(latest){announced.delete(latest.id);announce();}});
 remove?.addEventListener('click',async()=>{
  if(!latest||busy)return;busy=true;remove.disabled=true;
  try{
   const body=new FormData();body.set('_csrf',root.dataset.csrf);body.set('draw_id',String(latest.id));
   const response=await fetch(root.dataset.removeUrl,{method:'POST',body,headers:{Accept:'application/json'}});const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.error||'Entry removal failed. Please retry.');
   modal.close();result.textContent='Winning entry removed. Remaining entries can win again.';
  }catch(error){document.getElementById('winner-popup-error').textContent=error.message;}finally{busy=false;remove.disabled=false;}
 });
 async function update(){
  try{
   const response=await fetch(root.dataset.stateUrl,{cache:'no-store',signal:AbortSignal.timeout(5000)});if(!response.ok)throw new Error('Unavailable');const state=await response.json();
   stage.hidden=!state.live;
   if(!state.live){status.textContent='The live drawing is not on right now.';spinning=false;settle=null;if(modal.open)modal.close();return;}
   status.textContent='Live now — results update automatically.';
   latest=state.draws[0]||null;
   if(latest&&spinId!==latest.id){spinId=latest.id;spinDeadline=performance.now()+3000;settle=null;activeId=null;}
   const next=JSON.stringify(state.wheel||[]);
   if(next!==signature){signature=next;entries=state.wheel||[];const ul=document.getElementById('wheel-entry-list');ul.replaceChildren();for(const entry of entries){const li=document.createElement('li');li.textContent=entry.name+' · '+entry.key;ul.append(li);}document.getElementById('wheel-entry-count').textContent=entries.length+' entries on the wheel';}
   const pending=Boolean(latest&&latest.remaining>0);spinning=pending;document.getElementById('show-winner').hidden=!latest||pending||latest.removed;
   prize.textContent=latest?latest.prize:'Waiting for the next prize';
   result.textContent=latest?(pending?'Drawing from '+latest.entries+' entries…':latest.winner+' Won!'+(latest.removed?' Winning entry removed.':'')):'Choose a prize to begin.';
   if(latest&&!pending&&!latest.removed&&activeId!==latest.id){activeId=latest.id;spinning=false;land();}
   if(latest?.removed){settle=null;if(modal.open)modal.close();}
   list.replaceChildren();for(const draw of state.draws.filter(d=>d.remaining===0)){const article=document.createElement('article'),h=document.createElement('h3'),p=document.createElement('p');h.textContent=draw.winner+' won '+draw.prize;p.textContent=draw.giveaway+' · Ticket '+draw.ticket;article.append(h,p);list.append(article);}
  }catch(error){status.textContent='Connection interrupted. Reconnecting automatically…';spinning=false;}
  finally{setTimeout(update,latest&&latest.remaining>0?250:750);}
 }
 requestAnimationFrame(frame);update();
})();
