const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base=process.env.TEST_BASE_URL?.replace(/\/+$/,'');
if(!base)throw Error('Set TEST_BASE_URL on a migrated disposable test database.');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const admin=await browser.newContext(),learner=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  for(const [context,role] of[[admin,'admin'],[learner,'user']]){
   const r=await context.request.post(base+'/api/v1/auth/login',{data:{email:'books-'+role+'@example.test',password:'books-test-password-123'}});assert.equal(r.status(),200,await r.text());
  }
  const ap=await admin.newPage();const name='Chủ đề đồng bộ '+Date.now();
  await ap.goto(base+'/admin/topics');await ap.locator('#topic-name').fill(name);await ap.locator('#topic-form button[type=submit]').click();await ap.waitForURL('**/admin/topics?edit=*');
  const topic=new URL(ap.url()).searchParams.get('edit');assert(topic);
  const boot=(await (await admin.request.get(base+'/api/v1/admin/content/bootstrap')).json()).data;
  const headers={'X-CSRF-Token':boot.csrf_token};
  const preview=await admin.request.post(base+'/api/v1/admin/content/samples/preview',{headers,data:{sample_key:'drinks',topic_id:topic,duplicate_mode:'skip'}});assert.equal(preview.status(),200,await preview.text());
  const token=(await preview.json()).data.preview_token;
  const commit=await admin.request.post(base+'/api/v1/admin/content/csv/commit',{headers,data:{preview_token:token}});assert.equal(commit.status(),201,await commit.text());assert.equal((await commit.json()).data.imported,20);
  const words=(await (await admin.request.get(base+'/api/v1/admin/content/words?parent_id='+topic)).json()).data.items;
  const byEnglish=new Map(words.map(w=>[w.english,w.vietnamese])),byVietnamese=new Map(words.map(w=>[w.vietnamese,w.english]));
  const details=await Promise.all(words.map(async w=>(await (await admin.request.get(base+'/api/v1/admin/content/words/'+w.id)).json()).data));
  const page=await learner.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/');const card=page.locator('.ld-topic-card').filter({hasText:name});await card.waitFor();assert.match(await card.textContent(),/20 từ · 5 mức/);assert((await card.getAttribute('href')).endsWith('?topic='+topic));
  await card.click();assert.equal(await page.locator('[data-word-index]').count(),20,'Flashcards show all imported words');
  await page.goto(base+'/learning/practice?topic='+topic);assert.equal(await page.locator('.ls-card').count(),5);
  const launch=page.locator('.ls-card').first().getByRole('link',{name:/Vào luyện tập/});
  const sessionResponse=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/sessions'));
  await launch.click();const r=await sessionResponse;assert.equal(r.status(),200,await r.text());let state=(await r.json()).data;
  const lesson=state.lesson_id;let seen=0;
  while(state.status==='in_progress'){
   const q=state.question;let answer;
   assert(!JSON.stringify(q).includes('correct_option_ids'),'Public response hides answers');
   await page.locator('#lu-form').waitFor({state:'visible'});
   if(q.type==='choose_meaning'){
    const meaning=byEnglish.get(q.content.prompt)||byVietnamese.get(q.content.prompt);assert(meaning,'Question comes from this admin book');
    const option=q.content.options.find(o=>o.text===meaning);assert(option);await page.locator('#lu-controls input[value="'+option.id+'"]').check();
   }else if(q.type==='match_pairs'){
    const right=q.content.right;
    for(const left of q.content.left){const match=right.find(item=>item.text===byEnglish.get(left.text));assert(match);await page.locator('#lu-controls select').nth(q.content.left.indexOf(left)).selectOption(match.id);}
   }else if(q.type==='token_order'){
    const word=details.find(w=>(q.content.answer_lang==='vi'?w.example:w.example_translation)===q.content.prompt);assert(word);
    for(const token of (q.content.answer_lang==='vi'?word.example_translation:word.example).split(/\s+/)) await page.locator('.token-bank button:enabled').filter({hasText:new RegExp('^'+token.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'$')}).first().click();
   }else if(['translate_text','fill_blank'].includes(q.type)){
    const meaning=q.type==='fill_blank'?q.content.instruction.replace('Điền từ tiếng Anh: ',''):q.content.prompt;
    const answer=byVietnamese.get(meaning);assert(answer);await page.locator('#lu-controls input[type=text]').fill(answer);
   }else throw Error('Unexpected first-level question '+q.type);
   const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));
   await page.locator('#lu-submit').click();const result=await response;assert.equal(result.status(),200,await result.text());const data=(await result.json()).data;assert.equal(data.is_correct,true);state=data.session;seen++;
   await page.locator('#lu-next-question').waitFor({state:'visible'});await page.locator('#lu-next-question').click();
   if(state.status==='in_progress')await page.waitForFunction(id=>document.getElementById('lu-question').dataset.questionId===String(id)&&!document.getElementById('lu-question').hasAttribute('aria-busy'),state.question.id);
  }
  assert.equal(seen,11);assert.equal(state.status,'completed');await page.locator('#lu-result').waitFor({state:'visible'});
  await page.goto(base+'/learning/practice?topic='+topic);assert.equal(await page.locator('.ls-card').nth(1).getByRole('link',{name:/Vào luyện tập/}).count(),1,'Next generated level unlocks');
  await page.goto(base+'/learning/progress');assert.match(await page.locator('body').textContent(),new RegExp(name));
  if(process.env.ADMIN_SCREENSHOT_DIR){fs.mkdirSync(process.env.ADMIN_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/shared-progress.png',fullPage:true});await page.goto(base+'/');await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/shared-home.png',fullPage:true});}
  await page.setViewportSize({width:390,height:844});await page.goto(base+'/learning/explore?topic='+topic);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile flashcards fit');
  await page.goto(base+'/');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile home fits');
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS: admin creates topic/imports sample → actual home cards/20 flashcards/5 levels → full random quiz/matching → progress/unlock, desktop/mobile');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
