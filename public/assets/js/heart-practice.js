(() => {
 'use strict';
 const $=id=>document.getElementById(id),root=$('hp-app');if(!root)return;
 const base=root.dataset.base,csrf=root.dataset.csrf;let state=null,summary=null,busy=false,pending=null,clockOffset=0;
 const text=(id,value)=>{$(id).textContent=value??'';};
 async function api(path,body){
  const response=await fetch(base+'/api/v1/learning/hearts'+path,{method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body===undefined?{}:{'Content-Type':'application/json','X-CSRF-Token':csrf})},...(body===undefined?{}:{body:JSON.stringify(body)})});
  const data=await response.json();if(!response.ok){if(response.status===401)location.href=base+'/login?next=learning';throw Error(data.error?.message||'Chưa thực hiện được yêu cầu.');}return data.data;
 }
 function hearts(data){
  text('hp-hearts','♥ '+data.current_hp+'/'+data.max_hp+' tim');summary={...summary,...data};timer();
 }
 function timer(){
  if(!summary)return;if(summary.current_hp>=summary.max_hp){text('hp-timer','Tim đã đầy');return;}
  if(!summary.next_hp_at){text('hp-timer','Mỗi 30 phút hồi 1 tim');return;}
  const due=Date.parse(summary.next_hp_at.replace(' ','T')+'Z'),left=Math.max(0,Math.ceil((due-Date.now()-clockOffset)/1000));
  text('hp-timer',left?'Tim tiếp theo sau '+Math.floor(left/60)+':'+String(left%60).padStart(2,'0'):'Đang cập nhật tim…');
 }
 function render(){
  if(!state)return;hearts(state);
  $('hp-overview').hidden=true;$('hp-form').hidden=state.status!=='in_progress';$('hp-result').hidden=state.status!=='completed';
  if(state.status==='completed'){text('hp-result-title',state.hearts_added?'Đã hồi +1 tim!':'Hoàn thành ôn tập. Tim của bạn đã đầy.');return;}
  text('hp-count',state.answered_main+'/'+state.total_questions+' từ đã ôn đúng');$('hp-progress').max=state.total_questions;$('hp-progress').value=state.answered_main;
  text('hp-instruction',state.question.content.instruction);text('hp-prompt',state.question.content.prompt);$('hp-answer').value='';$('hp-answer').focus();
 }
 async function refresh(show=true){
  const data=await api('');clockOffset=Date.parse(data.server_now)-Date.now();summary=data;hearts(data);
  if(!show)return;
  state=data.active_session;pending=null;text('hp-message','');
  if(state){render();return;}
  $('hp-overview').hidden=false;$('hp-form').hidden=true;$('hp-result').hidden=true;
  $('hp-words').replaceChildren(...data.mistakes.map(w=>{const li=document.createElement('li'),name=document.createElement('strong'),meaning=document.createElement('div');name.textContent=w.english+' · '+w.cefr_level;meaning.textContent=w.vietnamese;li.append(name,meaning);return li;}));
  $('hp-start').disabled=data.mistakes.length===0;
  if(data.mistakes.length===0)text('hp-message','Không còn từ sai cần ôn. Tiếp tục học để ghi nhớ thêm từ mới.');
 }
 async function lock(work){if(busy)return;busy=true;$('hp-start').disabled=$('hp-submit').disabled=true;try{await work();}catch(error){text('hp-message',error.message);}finally{busy=false;$('hp-submit').disabled=false;$('hp-start').disabled=!summary?.mistakes?.length;}}
 $('hp-start').addEventListener('click',()=>lock(async()=>{state=await api('/start',{});text('hp-message','');render();}));
 $('hp-form').addEventListener('submit',event=>{event.preventDefault();lock(async()=>{
  pending??={question_id:state.question.id,request_key:crypto.randomUUID(),answer:{text:$('hp-answer').value}};
  const result=await api('/'+state.id+'/answers',pending);pending=null;state=result.session;
  if(result.is_correct){text('hp-message',state.status==='completed'?'Đã lưu kết quả.':'Chính xác!');render();}else{text('hp-message','Chưa đúng. Thử lại nhé, bạn không bị trừ tim.');hearts(state);$('hp-answer').select();}
 });});
 $('hp-more').addEventListener('click',()=>lock(()=>refresh()));
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!busy)refresh(!state||state.status!=='in_progress').catch(error=>text('hp-message',error.message));});
 setInterval(timer,1000);setInterval(()=>{if(!busy&&document.visibilityState!=='hidden')refresh(false).catch(()=>{});},30000);
 refresh().catch(error=>text('hp-message',error.message));
})();
