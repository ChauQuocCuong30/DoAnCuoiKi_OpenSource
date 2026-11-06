(() => {
 'use strict';
 const $=id=>document.getElementById(id),base=document.body.dataset.base,csrf=document.body.dataset.csrf;
 let answers={},step=0,busy=false,learningUrl=base+'/learning',signedIn=false;
 const reasons=[['free_time','🧠','Tận dụng thời gian rảnh'],['connect','🤝','Kết nối với mọi người'],['career','💼','Phát triển sự nghiệp'],['fun','🎉','Giải trí'],['study','📖','Hỗ trợ việc học'],['travel','✈','Đi du lịch'],['other','…','Mục tiêu khác']];
 const levels=[['A1','Mới bắt đầu'],['A2','Biết một vài từ thông dụng'],['B1','Có thể giao tiếp cơ bản'],['B2','Có thể trao đổi về nhiều chủ đề'],['C1','Có thể sử dụng trong học tập và công việc'],['C2','Sử dụng linh hoạt trong ngữ cảnh phức tạp']];
 const definitions=[
  {title:'Chào bạn! Cùng xây dựng thói quen học từ vựng nhé.',note:'Trả lời vài câu hỏi để chọn mục tiêu học phù hợp. Bạn có thể học thử trước khi tạo hồ sơ.'},
  {title:'Tại sao bạn học tiếng Anh?',key:'reason',items:reasons,grid:true},
  {title:'Bạn thấy trình độ tiếng Anh của mình thế nào?',key:'level',items:levels.map(([v,t])=>[v,v,t]),note:'Đây là mức tự đánh giá. Lựa chọn này không tự cộng XP hoặc mở khóa bài học.'},
  {title:'Bạn muốn dành bao nhiêu thời gian mỗi ngày?',key:'minutes',items:[[5,'5 phút / ngày','Mục tiêu 10 XP'],[10,'10 phút / ngày','Mục tiêu 20 XP'],[15,'15 phút / ngày','Mục tiêu 30 XP'],[20,'20 phút / ngày','Mục tiêu 40 XP']],note:'Số phút là mục tiêu thói quen. Chuỗi ngày học được tính khi đạt mục tiêu XP đã chọn.'},
  {title:'Cùng tìm điểm bắt đầu phù hợp nhé!',key:'start_mode',items:[['basic','📘','Bắt đầu từ bài cơ bản'],['placement','🧭','Thử bài kiểm tra trình độ demo']],note:'Bài demo gợi ý A1–B1. Lộ trình học thật vẫn dùng kho từ và quy tắc mở khóa của tài khoản.'},
  {title:'Sẵn sàng cho bài học đầu tiên!',note:'Nghe tiếng Anh, chọn từ và kiểm tra đáp án. Kết quả bài học được chấm ở máy chủ.'},
  {title:'Bạn muốn duy trì mục tiêu trong bao nhiêu ngày?',key:'streak',items:[[7,'7 ngày','Khởi đầu'],[14,'14 ngày','Giữ nhịp'],[30,'30 ngày','Bền bỉ'],[50,'50 ngày','Thử thách']],note:'Đây là mục tiêu bạn đặt ra, không phải số ngày streak đã đạt.'},
  {title:'Đã đến lúc lưu hành trình học của bạn!',note:'Tạo hồ sơ hoặc đăng nhập để lưu kết quả học thử và mục tiêu. Khảo sát không tự tạo điểm hoặc thành tích.'}
 ];
 async function api(body){const response=await fetch(base+'/api/v1/onboarding',{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});const data=await response.json();if(!response.ok)throw Error(data.error?.message||'Chưa lưu được khảo sát.');return data.data;}
 function link(label,path){const a=document.createElement('a');a.className='dq-survey-choice';a.textContent=label;a.href=path;return a;}
 function render(){
  const def=definitions[step];$('ds-title').textContent=def.title;$('ds-note').textContent=def.note||'';$('ds-options').replaceChildren();$('ds-options').className='dq-survey-options'+(def.grid?' is-grid':'');
  $('ds-progress').value=step;$('ds-step-label').textContent='Bước '+(step+1)+' / '+definitions.length;$('ds-back').disabled=step===0||busy;$('ds-next').hidden=step===7;
  $('ds-next').textContent=step===5?'Bắt đầu bài học':step===4?'Tiếp tục':'Tiếp tục';
  for(const [value,icon,label] of def.items||[]){const b=document.createElement('button');b.type='button';b.className='dq-survey-choice';b.setAttribute('aria-pressed',String(answers[def.key]===value));const tag=document.createElement('span');tag.className='dq-choice-icon';tag.textContent=icon;const text=document.createElement('strong');text.textContent=label;b.append(tag,text);b.onclick=()=>{if(busy)return;answers[def.key]=value;render();};$('ds-options').append(b);}
  if(step===7){$('ds-options').append(link(signedIn?'Về lộ trình học':'Tạo hồ sơ',base+(signedIn?'/learning':'/register?next=guest')),link(signedIn?'Xem hồ sơ':'Đăng nhập và lưu kết quả',base+(signedIn?'/profile':'/login?next=guest')),link('Lúc khác',base+'/learning'));}
  $('ds-next').disabled=busy||!!(def.key&&answers[def.key]===undefined);$('ds-title').focus({preventScroll:true});
 }
 async function save(next){const data=await api({...answers,step:next,...(next===7?{finished:true}:{})});answers=data.answers;learningUrl=data.learning_url;signedIn=data.signed_in;}
 $('ds-next').onclick=async()=>{
  if(busy)return;busy=true;$('ds-error').textContent='';render();
  try{
   if(step===5){await save(5);location.assign(learningUrl);return;}
   const next=step+1;await save(next);
   if(step===4&&answers.start_mode==='placement'){location.assign(base+'/placement?onboarding=1');return;}
   step=next;
  }catch(e){$('ds-error').textContent=e.message;}finally{busy=false;render();}
 };
 $('ds-back').onclick=async()=>{if(busy||step===0)return;busy=true;try{await save(step-1);step--;}catch(e){$('ds-error').textContent=e.message;}finally{busy=false;render();}};
 api().then(data=>{answers=data.answers||{};learningUrl=data.learning_url;signedIn=data.signed_in;step=Number.isInteger(answers.step)?answers.step:0;const requested=new URLSearchParams(location.search).get('step');if(requested==='streak'&&['reason','level','minutes','start_mode'].every(k=>answers[k]!==undefined))step=6;if(requested==='ready')step=5;render();}).catch(error=>{$('ds-title').textContent='Chưa mở được khảo sát';$('ds-error').textContent=error.message;});
})();
