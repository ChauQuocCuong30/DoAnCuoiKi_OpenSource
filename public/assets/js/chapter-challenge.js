(() => {
  'use strict';
  const $=id=>document.getElementById(id), base=document.body.dataset.base;
  let attempt=null,ui=null,busy=false,pending=null;
  const error=$('cc-error');
  async function api(path,body) {
    const response=await fetch(base+'/api/v1/learning/'+path,{
      method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',
      headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':document.body.dataset.csrf},
      ...(body===undefined?{}:{body:JSON.stringify(body)})
    });
    const payload=await response.json();
    if(!response.ok){const e=new Error(payload.error?.message||'Chưa thực hiện được yêu cầu.');e.status=response.status;throw e;}
    return payload.data;
  }
  function render() {
    ui?.destroy();ui=null;
    const done=attempt.status!=='in_progress';
    $('cc-intro').hidden=true;$('cc-test').hidden=done;$('cc-result').hidden=!done;
    if(done){
      $('cc-result-title').textContent=attempt.unlocked?'Đã mở toàn bộ chương!':'Chưa đạt lần này';
      $('cc-score').textContent=`Đúng ${attempt.correct_count}/${attempt.total} câu · Yêu cầu ít nhất ${attempt.pass_percent}%.`;
      $('cc-result-note').textContent=attempt.unlocked?'Bạn có thể vào tất cả bài học của chương. Những bài này vẫn chờ bạn học và tích lũy XP.':'Bạn có thể ôn từ và thử lại. Các khóa và tiến độ trước đó được giữ nguyên.';
      $('cc-retry').hidden=attempt.unlocked;return;
    }
    const q=attempt.question;
    $('cc-progress').max=attempt.total;$('cc-progress').value=attempt.answered;
    $('cc-count').textContent=`${q.number}/${attempt.total}`;
    $('lu-instruction').textContent=q.content.instruction;$('lu-prompt').textContent=q.content.prompt||'';
    $('lu-audio').pause();if(q.content.audio)$('lu-audio').src=q.content.audio.startsWith('/')?base+q.content.audio:q.content.audio;else $('lu-audio').removeAttribute('src');
    $('lu-answer-feedback').hidden=true;$('cc-next').hidden=true;$('cc-form').hidden=false;
    $('cc-submit').textContent='Kiểm tra';$('cc-submit').disabled=true;
    ui=window.DLQuestionUI.mount({question:q,host:$('lu-controls'),base,onChange:()=>{$('cc-submit').disabled=busy||!ui?.complete();}});
  }
  async function start() {
    if(busy)return;busy=true;error.textContent='';$('cc-start').disabled=true;$('cc-retry').disabled=true;
    try{
      attempt=await api('chapters/'+document.body.dataset.topic+'/challenge',{});
      const url=new URL(location.href);url.searchParams.set('attempt',attempt.id);history.replaceState(null,'',url);
      pending=null;render();
    }catch(e){error.textContent=e.message;}
    finally{busy=false;$('cc-start').disabled=false;$('cc-retry').disabled=false;}
  }
  $('cc-start').onclick=start;$('cc-retry').onclick=start;
  $('cc-next').onclick=render;
  $('cc-form').onsubmit=async event=>{
    event.preventDefault();if(busy||!ui?.complete())return;
    busy=true;error.textContent='';$('cc-submit').disabled=true;
    pending??={question_number:attempt.question.number,request_key:crypto.randomUUID?.()||Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join(''),answer:ui.read()};
    const frozen=[...$('lu-controls').querySelectorAll('input,button,select')];frozen.forEach(el=>el.disabled=true);
    try{
      const result=await api('chapter-attempts/'+attempt.id+'/answers',pending);
      pending=null;attempt=result.attempt;ui.feedback(result.is_correct,{});
      $('cc-form').hidden=true;$('cc-next').hidden=false;$('cc-next').textContent=attempt.status==='in_progress'?'Câu tiếp theo →':'Xem kết quả →';
    }catch(e){
      error.textContent=e.message;
      if(e.status){pending=null;try{attempt=await api('chapter-attempts/'+attempt.id);render();}catch{}}
      else $('cc-submit').textContent='Gửi lại đáp án';
      $('cc-submit').disabled=false;
    }finally{busy=false;}
  };
  const existing=new URLSearchParams(location.search).get('attempt');
  if(existing&&/^\d+$/.test(existing)){
    busy=true;api('chapter-attempts/'+existing).then(data=>{attempt=data;if(String(data.topic_id)!==document.body.dataset.topic)throw Error('Lượt kiểm tra thuộc chương khác.');render();}).catch(e=>error.textContent=e.message).finally(()=>{busy=false;});
  }
})();
