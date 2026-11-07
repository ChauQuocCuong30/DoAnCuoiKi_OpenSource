/* Shared English playback. Recorded media may be added later without changing grading. */
(() => {
 'use strict';
 let version=0,audio=null,utterance=null,base='',status=()=>{};
 const synth=window.speechSynthesis;
 const clean=value=>String(value??'').normalize('NFC').trim();
 function stop(){version++;if(audio){audio.pause();audio.removeAttribute('src');audio.load();audio=null;}synth?.cancel();utterance=null;}
 function url(value){
  if(typeof value!=='string'||/[\x00-\x20<>"'\\]/.test(value))return null;
  if(value.startsWith('/')&&!value.startsWith('//'))return base&&!value.startsWith(base+'/')?base+value:value;
  try{return new URL(value).protocol==='https:'?value:null;}catch{return null;}
 }
 function recorded(text,kind='word'){
  const entry=(window.DOULINGO_MEDIA||{})[clean(text).toLowerCase()];
  return typeof entry==='string'?entry:entry?.[kind]||null;
 }
 function speak(text,{lang='en',src=null,kind='word',rate=1}={}){
  stop();rate=Math.max(.55,Math.min(1.25,Number(rate)||1));text=clean(text);if(lang!=='en'||!text||document.hidden)return;
  const ticket=version,source=url(src||recorded(text,kind));let fallbackUsed=false;
  const fallback=()=>{
   if(fallbackUsed||ticket!==version||document.hidden)return;fallbackUsed=true;
   audio?.pause();
   if(!synth||!window.SpeechSynthesisUtterance){status('Chưa có giọng đọc trên thiết bị. Bạn có thể thêm file âm thanh.');return;}
   const voices=synth.getVoices().filter(v=>/^en(?:[-_]|$)/i.test(v.lang));
   const voice=voices.find(v=>/^en[-_]US$/i.test(v.lang))||voices[0];
   if(!voice&&synth.getVoices().length){status('Thiết bị chưa có giọng tiếng Anh. Hãy dùng file âm thanh hoặc cài giọng tiếng Anh.');return;}
   utterance=new SpeechSynthesisUtterance(text);utterance.lang=voice?.lang||'en-US';if(voice)utterance.voice=voice;
   utterance.rate=rate;
   utterance.onerror=e=>{if(ticket===version&&!['canceled','interrupted'].includes(e.error))status(e.error==='not-allowed'?'Nhấn nút loa để bật âm thanh.':'Chưa phát được âm thanh. Bạn có thể nhấn nút loa để thử lại.');};
   try{synth.resume();synth.speak(utterance);}catch{status('Nhấn nút loa để bật âm thanh.');}
  };
  if(!source){fallback();return;}
  audio=new Audio(source);audio.preload='auto';audio.playbackRate=rate;audio.addEventListener('error',fallback,{once:true});
  audio.play().catch(error=>{if(ticket!==version)return;if(error.name==='NotAllowedError')status('Nhấn nút loa để bật âm thanh.');else if(error.name!=='AbortError')fallback();});
 }
 window.DLAudio={configure(options={}){base=String(options.base||'').replace(/\/$/,'');status=options.onStatus||(()=>{});},speak,stop,recorded,url};
 document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});window.addEventListener('pagehide',stop);
})();
