const {chromium}=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
const base=process.env.TEST_BASE_URL?.replace(/\/+$/,'');if(!base)throw Error('Set TEST_BASE_URL on a migrated test DB.');
const levels=['A1','A2','B1','B2','C1','C2'];
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const admin=await browser.newContext(),learner=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),other=await browser.newContext();
  const errors=[];const page=await learner.newPage();page.on('pageerror',e=>errors.push(e.message));
  const suffix=Date.now(),password='cefr-browser-test-123',email='cefr-browser-'+suffix+'@example.test';
  const register=await learner.request.post(base+'/api/v1/auth/register',{data:{name:'CEFR browser',email,password}});assert.equal(register.status(),201,await register.text());
  const otherEmail='cefr-other-'+suffix+'@example.test';assert.equal((await other.request.post(base+'/api/v1/auth/register',{data:{name:'Other CEFR',email:otherEmail,password}})).status(),201);
  const login=async(context,mail,pass)=>{const r=await context.request.post(base+'/api/v1/auth/login',{data:{email:mail,password:pass}});assert.equal(r.status(),200,await r.text());};
  await login(admin,'books-admin@example.test','books-test-password-123');await login(other,otherEmail,password);
  const ap=await admin.newPage(),name='CEFR đồng bộ '+suffix;
  await ap.goto(base+'/admin/topics');await ap.locator('#topic-name').fill(name);await ap.locator('#topic-form button[type=submit]').click();await ap.waitForURL('**/admin/topics?edit=*');const topic=new URL(ap.url()).searchParams.get('edit');
  await ap.goto(base+'/admin/words?topic_id='+topic);await ap.locator('#book-import:enabled').click();await ap.getByRole('radio',{name:'File CSV của bạn',exact:true}).check();
  let csv='english,vietnamese,part_of_speech,example,example_translation,cefr_level\n';
  const dictionary=[['milk','sữa'],['water','nước'],['tea','trà'],['coffee','cà phê'],['juice','nước ép'],['bread','bánh mì'],['rice','cơm'],['apple','táo']];
  for(const band of levels)for(const [en,vi] of dictionary)csv+=`${en},${vi},noun,I like ${en}.,Tôi thích ${vi}.,${band}\n`;
  await ap.locator('#csv-file').setInputFiles({name:'cefr.csv',mimeType:'text/csv',buffer:Buffer.from(csv)});await ap.locator('#preview-import').click();await ap.getByText('48 từ mới · 0 từ trùng được bỏ qua · 0 lỗi',{exact:true}).waitFor();await ap.locator('#commit-import:enabled').click();await ap.getByText('Đã nhập 48 từ vào '+name+'. Bỏ qua 0 từ trùng.',{exact:true}).last().waitFor();await ap.getByRole('button',{name:'Đóng',exact:true}).click();
  await ap.locator('#word-level').selectOption('C2');await ap.getByText('8 từ phù hợp',{exact:true}).waitFor();assert.equal(await ap.locator('#words tr').count(),8);assert.equal(await ap.locator('#words tr td:nth-child(3)').first().textContent(),'C2');
  await page.goto(base+'/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(password);await page.locator('button[type=submit]').click();await page.waitForURL('**/learning');
  await page.goto(base+'/learning/explore?topic='+topic+'&level=C2');assert.equal(await page.locator('[data-word-index]').count(),8);assert((await page.locator('.ls-word-list').textContent()).includes('C2'));
  await page.goto(base+'/learning/practice?topic='+topic);assert.equal(await page.locator('.ls-card').count(),6);for(const band of levels)assert((await page.locator('.ls-list').textContent()).includes(band));
  const allWords=(await (await admin.request.get(base+'/api/v1/admin/content/words?parent_id='+topic+'&cefr_level=A1')).json()).data.items;
  const words=await Promise.all(allWords.map(async w=>(await (await admin.request.get(base+'/api/v1/admin/content/words/'+w.id)).json()).data));
  const byEn=new Map(words.map(w=>[w.english,w])),byVi=new Map(words.map(w=>[w.vietnamese,w]));const seen=new Set();
  async function submit(state,correct=true){
   const q=state.question;seen.add(q.type);assert(!JSON.stringify(q).includes('correct_option_ids'));
   await page.locator('#lu-form').waitFor({state:'visible'});
   if(q.content.options){
    assert.equal(q.content.options.length,4);assert.equal(await page.locator('#lu-controls input[type=radio]').count(),4);
    const expected=byEn.get(q.content.prompt)?.vietnamese||byVi.get(q.content.prompt)?.english;assert(expected);
    const option=q.content.options.find(o=>correct?o.text===expected:o.text!==expected);assert(option);await page.locator('#lu-controls input[value="'+option.id+'"]').check();
   }else if(q.type==='token_order'){
    const word=words.find(w=>w.example_translation===q.content.prompt);assert(word);let tokens=word.example.split(/\s+/);if(!correct)tokens.reverse();for(const token of tokens)await page.locator('.token-bank button:enabled').filter({hasText:new RegExp('^'+token.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'$')}).first().click();
   }else if(q.type==='match_pairs'){
    for(const [i,left]of q.content.left.entries()){const match=q.content.right.find(r=>r.text===byEn.get(left.text)?.vietnamese);assert(match);await page.locator('#lu-controls select').nth(i).selectOption(match.id);}
   }else {
    const meaning=q.type==='fill_blank'?q.content.instruction.replace('Điền từ tiếng Anh: ',''):q.content.prompt;
    assert(byVi.has(meaning));await page.locator('#lu-controls input[type=text]').fill(correct?byVi.get(meaning).english:'incorrect text');
   }
   const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));await page.locator('#lu-submit:enabled').click();const r=await response;assert.equal(r.status(),200,await r.text());const data=(await r.json()).data;assert.equal(data.is_correct,correct);state=data.session;
   if(state.status==='in_progress'){await page.locator('#lu-next-question').waitFor({state:'visible'});await page.locator('#lu-next-question').click();}
   return state;
  }
  let startResponse=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/sessions'));await page.locator('.ls-card').first().getByRole('link',{name:/Vào luyện tập/}).click();let state=(await (await startResponse).json()).data;const lesson=state.lesson_id,session=state.id;
  state=await submit(state);const question=state.question;
  assert.equal((await learner.request.post(base+'/api/v1/auth/logout')).status(),200);await page.goto(base+'/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(password);await page.locator('button[type=submit]').click();await page.waitForURL('**/learning');
  assert.match(await page.locator('[data-history-session="'+session+'"]').textContent(),/1\/8/);assert((await page.locator('body').textContent()).includes('Đang học 1/8 câu'));
  const stateResponse=page.waitForResponse(r=>r.request().method()==='GET'&&r.url().endsWith('/sessions/'+session));await page.locator('[data-history-session="'+session+'"] a').click();state=(await (await stateResponse).json()).data;assert.deepEqual(state.question,question);assert.equal(state.answered_main,1);
  const forbidden=await other.request.get(base+'/api/v1/learning/sessions/'+session);assert.equal(forbidden.status(),404);
  for(let i=0;i<3;i++)state=await submit(state,false);assert.equal(state.current_hp,0);assert.equal(state.status,'failed');
  await page.goto(base+'/learning/hearts');await page.locator('#hp-start:enabled').waitFor();assert.match(await page.locator('#hp-hearts').textContent(),/0\/3/);assert.equal(await page.locator('#hp-words li').count(),3);await page.locator('#hp-start').click();await page.locator('#hp-form').waitFor({state:'visible'});
  await page.locator('#hp-answer').fill('incorrect');await page.locator('#hp-submit').click();await page.getByText('Chưa đúng. Thử lại nhé, bạn không bị trừ tim.',{exact:true}).waitFor();assert.match(await page.locator('#hp-hearts').textContent(),/0\/3/);
  await learner.request.post(base+'/api/v1/auth/logout');await login(learner,email,password);await page.goto(base+'/learning/hearts');await page.locator('#hp-form').waitFor({state:'visible'});
  for(let i=0;i<3;i++){const meaning=await page.locator('#hp-prompt').textContent();await page.locator('#hp-answer').fill(byVi.get(meaning).english);const response=page.waitForResponse(r=>r.url().includes('/learning/hearts/')&&r.url().endsWith('/answers'));await page.locator('#hp-submit').click();assert.equal((await response).status(),200);if(i<2)await page.waitForFunction(n=>document.getElementById('hp-count').textContent.startsWith(n+'/'),i+1);}
  await page.getByText('Đã hồi +1 tim!',{exact:true}).waitFor();if(process.env.ADMIN_SCREENSHOT_DIR)await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/cefr-hearts.png',fullPage:true});assert.match(await page.locator('#hp-hearts').textContent(),/1\/3/);
  const own=(await (await other.request.get(base+'/api/v1/learning/hearts')).json()).data;assert.equal(own.current_hp,3);assert.equal(own.mistakes.length,0);
  // Complete every CEFR level with the same mixed question families.
  for(let i=0;i<6;i++){
   await page.goto(base+'/learning/practice?topic='+topic);startResponse=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/sessions'));
   await page.locator('.ls-card').nth(i).getByRole('link',{name:/Vào luyện tập|Luyện lại/}).click();const r=await startResponse;assert.equal(r.status(),200,await r.text());state=(await r.json()).data;
   const bandTypes=new Set();while(state.status==='in_progress'){bandTypes.add(state.question.type);state=await submit(state);}
   assert.equal(state.status,'completed');assert(bandTypes.size>=3,'Mixed exercise types at '+levels[i]);
  }
  // Editing the source retires old exercise IDs, but must not erase completed result details.
  const bootstrap=(await (await admin.request.get(base+'/api/v1/admin/content/bootstrap')).json()).data;
  const updated={...words[0],example:words[0].example+' Updated example.'};
  const edit=await admin.request.post(base+'/api/v1/admin/content/words',{headers:{'X-CSRF-Token':bootstrap.csrf_token},data:updated});assert.equal(edit.status(),200,await edit.text());
  await page.goto(base+'/learning');
  await page.goto(base+'/learning/lesson/'+lesson+'?focus=result');assert.equal(await page.locator('.review-question.unavailable').count(),0);assert.equal(await page.locator('.review-question').count(),8);
  console.log('PASS: completed result details survive a regenerated question bank');
  await page.goto(base+'/learning/progress');assert.match(await page.locator('body').textContent(),/Đã hoàn thành/);
  if(process.env.ADMIN_SCREENSHOT_DIR){fs.mkdirSync(process.env.ADMIN_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/cefr-progress.png',fullPage:true});await ap.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/cefr-admin.png',fullPage:true});}
  await page.setViewportSize({width:390,height:844});await page.goto(base+'/learning/hearts');await page.locator('#hp-hearts').filter({hasText:'tim'}).waitFor();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(process.env.ADMIN_SCREENSHOT_DIR)await page.screenshot({path:process.env.ADMIN_SCREENSHOT_DIR+'/cefr-hearts-mobile.png',fullPage:true});
  assert.equal(errors.length,0,errors.join('\n'));console.log('PASS: CEFR CSV/UI filtering, exactly four choices, mixed types in A1–C2, logout/login resumes same question, per-account history/hearts, zero-heart practice and +1 reward, mobile layout');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
