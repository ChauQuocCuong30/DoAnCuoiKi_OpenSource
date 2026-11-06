const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.env.TEST_BASE_URL?.replace(/\/+$/,'');
if(!base)throw Error('Set TEST_BASE_URL to the test server.');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'}),page=await context.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let demoWrites=0;page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/v1/learning'))demoWrites++;});
  for(const p of['/start','/level','/placement'])assert.equal((await page.goto(base+p)).status(),200);
  const legacy={level:'a1',xp:987,learned:{a1:['apple']}};
  await page.evaluate(v=>localStorage.setItem('doulingo_progress',JSON.stringify(v)),legacy);
  for(const correct of[true,false]){
   await page.goto(base+'/placement');
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   for(let i=0;i<9;i++){
    if(correct){
     const word=(await page.locator('.q-title').textContent()).match(/“(.+)”/)[1];
     const level=['a1','a2','b1'][Math.floor(i/3)];
     const vi=await page.evaluate(({word,level})=>window.DOULINGO_VOCAB[level].find(w=>w.en===word).vi,{word,level});
     const n=await page.locator('.opt').evaluateAll((els,vi)=>els.findIndex(e=>e.querySelector('span:last-child').textContent===vi),vi);
     assert(n>=0);await page.locator('.opt').nth(n).click();
    }else await page.locator('#dk').click();
    if(i<8)await page.waitForFunction(expected=>document.querySelector('#count').textContent===expected,`${i+2}/9`);
   }
   await page.locator('.result').waitFor();
   assert.match(await page.locator('.result p').first().textContent(),correct?/9\/9/:/0\/9/);
   assert.equal(await page.locator('.result .badge').textContent(),correct?'B1':'A1');
   assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('doulingo_progress'))),legacy);
   assert.equal(demoWrites,0,'Demo never posts learning scores or XP');
   await page.locator('#goOn').click();
   await page.getByRole('link',{name:'Tạo hồ sơ'}).waitFor();
   assert.equal(new URL(await page.getByRole('link',{name:'Tạo hồ sơ'}).getAttribute('href'),base).pathname,new URL(base+'/register').pathname);
   assert.equal(new URL(await page.getByRole('link',{name:'Lúc khác'}).getAttribute('href'),base).pathname,new URL(base+'/').pathname);
  }
  await page.goto(base+'/level');await page.locator('[data-level=a2]').click();await page.waitForURL('**/placement?level=a2');
  assert.equal(await page.locator('#count').textContent(),'1/6','Level pick runs a 6-question test first');
  const legacyRoute=await context.request.get(base+'/lesson',{maxRedirects:0});assert.equal(legacyRoute.status(),303);assert(legacyRoute.headers().location.endsWith('/learning'));
  for(const role of['admin','user']){
   const actor=await browser.newContext({reducedMotion:'reduce'});const p=await actor.newPage();p.on('pageerror',e=>errors.push(e.message));
   await p.goto(base+'/login');await p.locator('[name=email]').fill('books-'+role+'@example.test');await p.locator('[name=password]').fill('books-test-password-123');await p.locator('button[type=submit]').click();
   await p.waitForURL(role==='admin'?'**/admin/words':'**/learning');
   await p.goto(base+'/');
   assert.equal(await p.locator('body.ld').count(),1,'PR dashboard remains the home page');
   assert.equal(await p.locator('.ld-nav a[href$="/admin/words"]').count(),role==='admin'?1:0);
   await p.goto(base+'/profile');await p.locator('[data-form=profile]').waitFor({state:'visible'});
   assert.equal(await p.locator('.account-navigation a[href$="/admin/words"]').count(),role==='admin'?1:0);
   assert.equal(await p.locator('[data-admin-id]').count(),0,'Learner management stays in Admin');
   await actor.close();
  }
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS: restored onboarding, 9/9 and 0/9 placement, A1/B1 suggestions, separate local result, no learning writes, mobile layout, manual A2 and navigation into PR #26');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
