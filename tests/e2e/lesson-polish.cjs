const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const base=process.env.TEST_BASE_URL?.replace(/\/$/,'');if(!base)throw Error('Set TEST_BASE_URL to an isolated migrated server');
const fixture=JSON.parse(fs.readFileSync(process.env.MEDIA_FIXTURE_FILE||path.join(os.tmpdir(),'doulingo-media-fixture.json'),'utf8'));

async function settle(page){await page.evaluate(()=>Promise.allSettled(document.getAnimations().filter(a=>a.effect?.getTiming().iterations!==Infinity).map(a=>a.finished)));}
async function contrast(page,selector){
 await settle(page);
 return page.evaluate(selector=>{
  const lum=rgb=>{const a=rgb.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});return a[0]*.2126+a[1]*.7152+a[2]*.0722;};
  return [...document.querySelectorAll(selector)].filter(e=>e.getClientRects().length&&!e.classList.contains('is-used')).map(e=>{
   const color=getComputedStyle(e).color.match(/[\d.]+/g).map(Number);let node=e,bg;
   while(node){const candidate=getComputedStyle(node).backgroundColor.match(/[\d.]+/g)?.map(Number);if(candidate&&(candidate.length<4||candidate[3]>=.99)){bg=candidate;break;}node=node.parentElement;}
   const a=lum(color),b=lum(bg||[255,255,255]);return[e.textContent.trim(),(Math.max(a,b)+.05)/(Math.min(a,b)+.05)];
  });
 },selector);
}
async function footerContrast(page,label){
 for(const mode of ['light','dark']){
  await page.evaluate(mode=>document.documentElement.dataset.theme=mode,mode);
  for(const [text,ratio]of await contrast(page,'.dq-next-actions strong,.dq-next-actions p,#lu-next-question'))assert(ratio>=4.5,label+' '+mode+' '+text+' '+ratio);
  if(process.env.ADMIN_SCREENSHOT_DIR){fs.mkdirSync(process.env.ADMIN_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/'+label+'-'+mode+'.png',fullPage:true});}
 }
}

(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'no-preference'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await context.addInitScript(()=>{
   window.__tones=[];window.__peak=0;
   const Native=window.AudioContext||window.webkitAudioContext;
   if(Native)window.AudioContext=class extends Native{
    constructor(...args){super(...args);this.meter=this.createAnalyser();this.meter.connect(this.destination);
     const sample=()=>{if(this.state==='closed')return;const data=new Float32Array(this.meter.fftSize);this.meter.getFloatTimeDomainData(data);for(const value of data)window.__peak=Math.max(window.__peak,Math.abs(value));requestAnimationFrame(sample);};sample();}
    createOscillator(){const node=super.createOscillator(),original=node.frequency.setValueAtTime.bind(node.frequency);node.frequency.setValueAtTime=(value,at)=>{window.__tones.push(value);return original(value,at);};return node;}
    createGain(){const node=super.createGain(),connect=node.connect.bind(node);node.connect=(target,...args)=>connect(target===this.destination?this.meter:target,...args);return node;}
   };
  });
  await context.request.post(base+'/api/v1/auth/login',{data:{email:fixture.email,password:fixture.password}});
  await page.goto(base+'/learning/lesson/'+fixture.lesson+'?focus=practice');
  await page.getByText('Âm thanh chưa sẵn sàng. Bạn có thể làm câu tương đương bằng chữ.',{exact:true}).waitFor();
  await page.locator('#lu-controls input[type=text]').fill('incorrect answer');
  let release,received;
  const held=new Promise(resolve=>release=resolve),arrived=new Promise(resolve=>received=resolve);
  await page.route('**/api/v1/learning/sessions/*/answers',async route=>{received();await held;await route.continue();},{times:1});
  const first=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));
  await page.locator('#lu-submit').click();await arrived;
  assert.deepEqual(await page.evaluate(()=>window.__tones),[],'No verdict sound before server grading');
  release();const wrong=(await(await first).json()).data;assert.equal(wrong.is_correct,false);
  await page.waitForFunction(()=>window.__tones.length===2);assert((await page.evaluate(()=>window.__tones)).every(x=>x<300));
  await page.waitForFunction(()=>window.__peak>.001);assert.match(await page.locator('.dq-next-actions').textContent(),/Chưa đúng/);await footerContrast(page,'feedback-wrong');
  await page.locator('#lu-next-question').click();
  await page.waitForFunction(id=>document.getElementById('lu-question').dataset.questionId===String(id)&&!document.getElementById('lu-question').hasAttribute('aria-busy'),wrong.session.question.id);
  await page.getByText('Âm thanh chưa sẵn sàng. Bạn có thể làm câu tương đương bằng chữ.',{exact:true}).waitFor();
  await page.locator('input[value=choice-0]').check();assert.equal((await page.evaluate(()=>window.__tones)).length,2,'Selecting an answer does not play a verdict');
  const keys=[];
  await page.route('**/api/v1/learning/sessions/*/answers',async route=>{keys.push(route.request().postDataJSON().request_key);await route.abort('failed');},{times:1});
  await page.locator('#lu-submit').click();await page.getByRole('button',{name:'Gửi lại',exact:true}).waitFor();
  assert.equal((await page.evaluate(()=>window.__tones)).length,2,'A network error is silent');
  const retry=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));
  page.once('request',request=>{if(request.url().endsWith('/answers'))keys.push(request.postDataJSON().request_key);});
  await page.locator('#lu-submit').click();const correct=(await(await retry).json()).data;assert.equal(correct.is_correct,true);
  await page.waitForFunction(()=>window.__tones.length===5);assert((await page.evaluate(()=>window.__tones.slice(2))).every(x=>x>600));assert.equal(keys[0],keys[1],'Retry keeps the same submission key');
  assert.equal(correct.session.current_hp,2);await footerContrast(page,'feedback-correct');
  const state=correct.session;assert.equal(state.status,'failed','One of two main answers is below the unchanged pass threshold');
  assert.equal(state.answered_main,2);assert.equal(state.current_hp,2);assert.equal(await page.locator('#lu-result').isVisible(),false,'Last answer keeps its feedback until Continue');
  await page.locator('#lu-next-question').click();await page.locator('#lu-result').waitFor({state:'visible'});
  assert.equal((await page.evaluate(()=>window.__tones)).length,5,'Each of the two graded responses sounds once');
  assert.deepEqual(errors,[]);console.log('PASS: actual audible WebAudio waveform, verdict after server response only, silent network failure, one sound on retry, progress/hearts and final-feedback flow');

  // Use the real player markup/CSS with only its controller paused for isolated presentation fixtures.
  const visual=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'no-preference'});
  await visual.addInitScript(()=>{
   window.__spoken=[];Object.defineProperty(window,'speechSynthesis',{value:{getVoices:()=>[{lang:'en-US',name:'Test'}],cancel(){},resume(){},speak:u=>window.__spoken.push({text:u.text,rate:u.rate})}});
   window.SpeechSynthesisUtterance=class{constructor(text){this.text=text;}};
  });
  const vp=await visual.newPage();vp.on('pageerror',e=>errors.push(e.message));
  await vp.route('**/assets/js/learning-ui.js?*',route=>route.fulfill({contentType:'text/javascript',body:''}));
  await vp.goto(base+'/learning/lesson/1?focus=practice');
  await vp.waitForFunction(()=>!!window.DLQuestionUI);
  const mount=async(question,delay=0)=>vp.evaluate(({question,base,delay})=>{
   window.ui?.destroy();window.__spoken=[];document.getElementById('lu-prompt').textContent=question.content.prompt||'';
   document.getElementById('lu-form').hidden=false;document.getElementById('lu-answer-feedback').hidden=true;
   window.ui=window.DLQuestionUI.mount({question,host:document.getElementById('lu-controls'),base,autoReadDelay:delay});
  },{question,base,delay});
  const tokens=['I','can','find','words.'].map((text,i)=>({id:'t'+i,text}));
  const english={type:'token_order',content:{prompt:'Tôi có thể tìm từ.',prompt_lang:'vi',answer_lang:'en',tokens}};
  for(const mode of ['light','dark']){
   await vp.evaluate(mode=>{document.documentElement.dataset.theme=mode;},mode);await mount(english);
   for(let i=0;i<3;i++)await vp.locator('.token-bank button').nth(i).click();
   await vp.locator('.token-line button').nth(1).click();await vp.locator('.token-bank button').nth(1).click();
   assert.deepEqual(await vp.evaluate(()=>window.ui.read().token_ids),['t0','t2','t1']);assert.deepEqual(await vp.evaluate(()=>window.__spoken.map(x=>x.text)),['I','can','find','can']);
   for(const [label,ratio]of await contrast(vp,'.token-line button,.token-bank button,#lu-submit'))assert(ratio>=4.5,mode+': '+label+' contrast '+ratio);
   await vp.evaluate(()=>document.getElementById('lu-gate').showModal());
   for(const [label,ratio]of await contrast(vp,'#lu-gate h2,#lu-gate p,#lu-gate a,#lu-gate button'))assert(ratio>=4.5,mode+' dialog '+label+' contrast '+ratio);
   if(process.env.ADMIN_SCREENSHOT_DIR)await vp.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/dialog-'+mode+'.png',fullPage:true});
   await vp.evaluate(()=>document.getElementById('lu-gate').close());
   assert(await vp.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   if(process.env.ADMIN_SCREENSHOT_DIR){fs.mkdirSync(process.env.ADMIN_SCREENSHOT_DIR,{recursive:true});await vp.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/tokens-'+mode+'.png',fullPage:true});}
  }
  await mount({type:'fill_blank',content:{prompt:'I ____ new words.',prompt_lang:'en',answer_lang:'en',prompt_audio:'/assets/media/recording.mp3'}});
  await vp.locator('#lu-controls input').fill('learn');assert.deepEqual(await vp.evaluate(()=>window.__spoken),[]);assert(await vp.locator('#lu-question-sound').isHidden());assert.equal(await vp.locator('#lu-question-slow').count(),0);
  await mount({type:'translate_text',content:{prompt:'Write the English word.',prompt_lang:'en',answer_lang:'en'}});assert.deepEqual(await vp.evaluate(()=>window.__spoken),[]);assert(await vp.locator('#lu-question-sound').isHidden());
  const vietnamese={type:'token_order',content:{prompt:'I can find words.',prompt_lang:'en',answer_lang:'vi',tokens:['Tôi','tìm','từ'].map((text,i)=>({id:'v'+i,text}))}};
  await mount(vietnamese,120);await vp.waitForFunction(()=>window.__spoken.length===1);await vp.locator('.token-bank button').first().click();assert.equal((await vp.evaluate(()=>window.__spoken)).length,1);assert.equal(await vp.locator('#lu-question-slow').count(),0);await vp.locator('#lu-question-sound').click();assert.equal((await vp.evaluate(()=>window.__spoken.at(-1))).rate,1);
  await mount(vietnamese,300);await mount({type:'fill_blank',content:{prompt:'I ____ words.',prompt_lang:'en',answer_lang:'en'}});await vp.waitForTimeout(350);assert.deepEqual(await vp.evaluate(()=>window.__spoken),[],'Leaving a question cancels scheduled speech');
  const reward=await vp.evaluate(()=>{
   const root=document.getElementById('lu-result-streak'),result=document.getElementById('lu-result');
   const cues=[];window.DLFeedbackSound.play=(...args)=>cues.push(args);
   window.DLRewards.streak(root,{current:2,previous:1,key:'next-day'});
   const increased=root.classList.contains('is-increasing')&&root.querySelector('.lr-number-old').textContent==='1'&&root.querySelector('strong').textContent==='2'&&!!root.querySelector('.lr-flame svg');
   window.DLRewards.streak(root,{current:2,previous:2,key:'same-day'});
   const sameDay=!root.classList.contains('is-increasing')&&!root.querySelector('.lr-number-old');
   window.DLRewards.streak(root,{current:2,key:'reload'});
   const reload=!root.classList.contains('is-increasing');
   window.DLRewards.outcome(result,false,{key:'failed',fresh:true});
   const failure=result.classList.contains('is-fail')&&cues.length===0&&!document.querySelector('.lr-fireworks');
   window.DLRewards.outcome(result,true,{key:'passed',fresh:true});
   window.DLRewards.outcome(result,true,{key:'passed',fresh:true});
   return {increased,sameDay,reload,failure,cues:cues.length,bursts:document.querySelectorAll('.lr-fireworks').length};
  });
  assert.deepEqual(reward,{increased:true,sameDay:true,reload:true,failure:true,cues:1,bursts:1});
  await vp.emulateMedia({reducedMotion:'reduce'});
  assert(await vp.evaluate(()=>{
   document.querySelectorAll('.lr-fireworks').forEach(el=>el.remove());
   window.DLRewards.streak(document.getElementById('lu-result-streak'),{current:3,previous:2,key:'reduced'});
   window.DLRewards.celebrate('reduced');
   return !document.querySelector('.lr-fireworks')&&!document.querySelector('.lr-number-old')&&getComputedStyle(document.querySelector('.lr-flame svg')).animationName==='none';
  }),'Reduced motion keeps the final streak without fireworks or number animation');
  console.log('PASS: server-provided streak 1→2, same-day/reloaded totals, failed result, one celebration and reduced motion');
  await mount(english);await vp.locator('.token-bank button').first().click();assert.equal(await vp.locator('.token-line button').evaluate(e=>e.getAnimations().length),0);
  assert.deepEqual(errors,[]);console.log('PASS: token/guest-dialog contrast >=4.5:1 in both themes, stable token order, silent keyboard questions, timed sentence/replay speech and removed slow button, cancellation, mobile and reduced motion');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1});
