const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const base=process.env.TEST_BASE_URL?.replace(/\/$/,'');if(!base)throw Error('Set TEST_BASE_URL');
const fixture=JSON.parse(fs.readFileSync(process.env.MEDIA_FIXTURE_FILE||path.join(os.tmpdir(),'doulingo-media-fixture.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await context.request.post(base+'/api/v1/auth/login',{data:{email:fixture.email,password:fixture.password}});
  const state=async()=>{const response=await context.request.get(base+'/api/v1/learning/sessions/'+fixture.session);assert.equal(response.status(),200);return(await response.json()).data;};
  await page.goto(base+'/learning/lesson/'+fixture.lesson+'?focus=practice');
  await page.getByText('Âm thanh chưa sẵn sàng. Bạn có thể làm câu tương đương bằng chữ.',{exact:true}).waitFor();
  assert.equal(await page.locator('#lu-prompt').textContent(),fixture.vietnamese);
  assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(255, 255, 255)');
  assert.equal((await state()).current_hp,3);assert.equal((await state()).answered_main,0);
  await page.reload();await page.getByText('Âm thanh chưa sẵn sàng. Bạn có thể làm câu tương đương bằng chữ.',{exact:true}).waitFor();
  assert.equal((await state()).question.id,fixture.question_ids[0]);assert.equal((await state()).answered_main,0);
  await page.locator('#lu-controls input[type=text]').fill('definitely wrong');
  let answer=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));
  await page.locator('#lu-submit').click();let result=(await(await answer).json()).data;assert.equal(result.is_correct,false);assert.equal(result.session.current_hp,2);assert.equal(result.session.answered_main,1);
  await page.locator('#lu-next-question').click();await page.getByText('Âm thanh chưa sẵn sàng. Bạn có thể làm câu tương đương bằng chữ.',{exact:true}).waitFor();
  assert.equal(await page.locator('#lu-prompt').textContent(),fixture.english);assert.equal(await page.locator('#lu-controls input[type=radio]').count(),4);
  await page.locator('input[value=choice-0]').check();answer=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/answers'));await page.locator('#lu-submit').click();result=(await(await answer).json()).data;assert.equal(result.is_correct,true);assert.equal(result.session.current_hp,2);assert.equal(result.session.answered_main,2);
  assert.match(await page.locator('#lu-answer-feedback').textContent(),/Chính xác/);
  await page.getByRole('button',{name:'Bật chế độ tối',exact:true}).click();
  assert.equal(await page.locator('html').getAttribute('data-theme'),'dark');
  assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(19, 31, 36)');
  for(const route of ['/learning','/profile','/reviews','/start','/level']){
   await page.goto(base+route);assert.equal(await page.locator('html').getAttribute('data-theme'),'dark',route);
   assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(19, 31, 36)',route);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),route+' fits mobile');
  }
  await context.request.post(base+'/api/v1/auth/logout');await context.request.post(base+'/api/v1/auth/login',{data:{email:fixture.email,password:fixture.password}});
  const resumed=await state();assert.equal(resumed.answered_main,2);assert.equal(resumed.current_hp,2);
  await page.goto(base+'/learning');assert.equal(await page.locator('html').getAttribute('data-theme'),'dark');await page.getByRole('button',{name:'Bật chế độ sáng',exact:true}).click();await page.reload();assert.equal(await page.locator('html').getAttribute('data-theme'),'light');
  const admin=await browser.newContext({viewport:{width:390,height:844}});await admin.request.post(base+'/api/v1/auth/login',{data:{email:'books-admin@example.test',password:'books-test-password-123'}});const ap=await admin.newPage();
  for(const mode of ['light','dark']){
   for(const route of ['/admin/words','/admin/topics','/admin/learners']){
    await ap.goto(base+route);if(await ap.locator('html').getAttribute('data-theme')!==mode)await ap.locator('[data-theme-toggle]').click();
    assert.equal(await ap.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),mode==='light'?'rgb(255, 255, 255)':'rgb(19, 31, 36)');
    assert(await ap.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),route+' fits mobile');
   }
  }
  const history=await browser.newContext({viewport:{width:390,height:844}});await history.request.post(base+'/api/v1/auth/login',{data:{email:'a@example.test',password:'test-password-123'}});const hp=await history.newPage();
  await hp.goto(base+'/learning/lesson/1?focus=result');assert.equal(await hp.locator('body.result-page').count(),1);await hp.locator('[data-theme-toggle]').click();
  assert.equal(await hp.locator('html').getAttribute('data-theme'),'dark');assert.equal(await hp.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(19, 31, 36)');assert(await hp.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Archived result fits mobile');
  assert.deepEqual(errors,[]);console.log('PASS: real 404 listening fallback, actual wrong/correct grading, saved hearts/progress, light/dark persistence across learning/account/admin and mobile fit');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
