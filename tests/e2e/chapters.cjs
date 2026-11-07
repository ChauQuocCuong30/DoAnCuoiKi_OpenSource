const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const base=process.env.TEST_BASE_URL?.replace(/\/$/,'');if(!base)throw Error('Set TEST_BASE_URL');
const fixture=JSON.parse(fs.readFileSync(process.env.CHAPTER_FIXTURE_FILE||path.join(os.tmpdir(),'doulingo-chapter-fixture.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  assert.equal((await context.request.post(base+'/api/v1/auth/login',{data:{email:fixture.email,password:fixture.password}})).status(),200);
  const guest=await browser.newContext();assert.equal((await guest.request.post(base+'/api/v1/learning/chapters/'+fixture.topics[1]+'/challenge',{data:{}})).status(),401);
  assert.equal((await context.request.post(base+'/api/v1/learning/chapters/'+fixture.topics[1]+'/challenge',{data:{}})).status(),403,'CSRF is required');
  await page.goto(base+'/learning');
  const chapter=page.locator('[data-chapter="'+fixture.topics[1]+'"]');
  assert.equal(await chapter.locator('.ld-lesson.is-locked').count(),6,'Future chapter is locked');
  await chapter.locator('[data-chapter-challenge]').click();await page.locator('#cc-start').click();await page.locator('#cc-test').waitFor({state:'visible'});
  const id=new URL(page.url()).searchParams.get('attempt');assert(id);
  const state=async()=>{const r=await context.request.get(base+'/api/v1/learning/chapter-attempts/'+id);assert.equal(r.status(),200);return(await r.json()).data;};
  let current=await state();let answered=0;
  while(current.status==='in_progress'){
    const q=current.question,c=q.content;
    assert(!JSON.stringify(q).includes('accepted_answers'));assert(!c.hint_image,'Checkpoint does not show learning hints');
    if(c.options){
      const word=fixture.words.find(([en,vi])=>en===c.prompt||vi===c.prompt);assert(word);
      const expected=word[0]===c.prompt?word[1]:word[0];const option=c.options.find(o=>o.text===expected);assert(option);
      await page.locator('#lu-controls input[value="'+option.id+'"]').check();
    }else if(q.type==='token_order'){
      const word=fixture.words.find(([en,vi])=>(c.answer_lang==='vi'?'I learn '+en+'.':'Tôi học '+vi+'.')===c.prompt);assert(word);
      const text=c.answer_lang==='vi'?'Tôi học '+word[1]+'.':'I learn '+word[0]+'.';
      for(const token of text.split(/\s+/))await page.locator('.token-bank button:enabled').filter({hasText:new RegExp('^'+token.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'$')}).first().click();
    }else{
      const meaning=q.type==='fill_blank'?c.instruction.replace('Điền từ tiếng Anh: ',''):c.prompt;
      const word=fixture.words.find(w=>w[1]===meaning);assert(word);await page.locator('#lu-controls input[type=text]').fill(word[0]);
    }
    const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/chapter-attempts/'+id+'/answers'));
    await page.locator('#cc-submit:enabled').click();const reply=await response;assert.equal(reply.status(),200,await reply.text());current=(await reply.json()).data.attempt;answered++;
    await page.locator('#cc-next').waitFor({state:'visible'});
    if(answered===1){await page.reload();await page.locator('#cc-test').waitFor({state:'visible'});assert.equal((await state()).answered,1,'Reload resumes stored progress');}
    else await page.locator('#cc-next').click();
  }
  assert.equal(current.status,'completed');await page.getByText('Đã mở toàn bộ chương!',{exact:true}).waitFor();
  await page.goto(base+'/learning');assert.equal(await page.locator('[data-chapter="'+fixture.topics[1]+'"] .ld-lesson.is-locked').count(),0);
  for(const width of [1440,390]){
    await page.setViewportSize({width,height:950});
    for(const mode of ['light','dark']){
      if(await page.locator('html').getAttribute('data-theme')!==mode)await page.locator('[data-theme-toggle]').click();
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Chapter path fits '+width+' '+mode);
      assert.equal(await page.locator('[data-chapter="'+fixture.topics[1]+'"] .ld-lesson a.ld-btn').last().evaluate(e=>getComputedStyle(e).color),mode==='dark'?'rgb(238, 245, 248)':'rgb(25, 61, 48)','Lesson buttons stay legible in '+mode);
      if(process.env.CHAPTER_SCREENSHOT_DIR){fs.mkdirSync(process.env.CHAPTER_SCREENSHOT_DIR,{recursive:true});await page.locator('[data-chapter="'+fixture.topics[1]+'"]').screenshot({path:process.env.CHAPTER_SCREENSHOT_DIR+'/chapter-'+width+'-'+mode+'.png'});}
    }
  }
  await page.goto(base+'/learning/lesson/'+fixture.lesson+'?focus=words');
  await page.locator('#lu-image').waitFor({state:'visible'});assert((await page.locator('#lu-image').getAttribute('src')).endsWith('/vocabulary/skill.svg'));
  assert.match(await page.locator('#lu-ipa').textContent(),/skɪl/);
  await page.locator('#lu-start').click();await page.locator('#lu-question').waitFor({state:'visible'});
  const hint=page.locator('.dq-prompt-row > img.dq-word-art');await hint.waitFor({state:'visible'});assert(!(await hint.getAttribute('src')).includes('notebook'));
  assert.deepEqual(errors,[]);console.log('PASS: chapter locks, CSRF, server-scored skip, reload, all six nodes open, correct word artwork, mobile light/dark');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
