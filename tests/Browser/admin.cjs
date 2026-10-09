/* Run against the explicitly supplied isolated local WordPress test server. */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
(async () => {
 const base = process.env.QSN_BASE_URL;
 if (!base || !/^http:\/\/127\.0\.0\.1:\d+$/.test(base)) throw new Error('Use an isolated localhost test URL.');
 const output = process.env.QSN_SCREENSHOTS || path.resolve(__dirname,'../../docs/screenshots');
 fs.mkdirSync(output,{recursive:true});
 const browser = await chromium.launch({executablePath:process.env.QSN_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 const context = await browser.newContext({viewport:{width:1440,height:1000}});
 await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
 const page = await context.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(`${base}/wp-login.php`);
 await page.locator('#user_login').fill(process.env.QSN_ADMIN_USER);
 await page.locator('#user_pass').fill(process.env.QSN_ADMIN_PASSWORD);
 await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
 const settings = `${base}/wp-admin/admin.php?page=quixdevs-stocknotifier-settings`;
 await page.goto(settings);
 const field=page.locator('#qsn-threshold');
 assert.equal(await field.getAttribute('type'),'number');
 assert.equal(await field.getAttribute('inputmode'),'numeric');
 assert.equal(await field.getAttribute('min'),'0');assert.equal(await field.getAttribute('step'),'1');
 for(const value of ['0','1','5','10','100']){
  await field.fill(value);assert.equal(await field.evaluate(e=>e.checkValidity()),true);
 }
 await field.fill('5');await field.focus();await field.press('End');
 for(const key of ['e','E','+','-','.',',','a',' ']){
  await field.press(key);assert.equal(await field.inputValue(),'5',`keyboard ${key}`);
  assert.ok(await page.locator('#qsn-threshold-error').textContent());
 }
 for(const value of ['abc','5abc','5.5','-5','+5','1e3','2E4','5,000','5 000','1/2','']){
  await field.fill('5');
  const prevented = await field.evaluate((e,text)=>{
   const dt = new DataTransfer();dt.setData('text/plain',text);
   const event = new ClipboardEvent('paste',{clipboardData:dt,bubbles:true,cancelable:true});
   e.dispatchEvent(event);return event.defaultPrevented;
  },value);
  assert.equal(prevented,true,`paste ${value}`);assert.equal(await field.inputValue(),'5');
 }
 await field.fill('5');
 assert.equal(await field.evaluate(e=>{const dt=new DataTransfer();dt.setData('text','-1');const ev=new DragEvent('drop',{dataTransfer:dt,bubbles:true,cancelable:true});e.dispatchEvent(ev);return ev.defaultPrevented;}),true);
 for(const value of ['1e3','5.5','-5','2147483648','']){
  await field.evaluate((e,v)=>{e.value=v;e.dispatchEvent(new Event('input',{bubbles:true}));},value);
  assert.equal(await field.evaluate(e=>e.checkValidity()),false,`input ${value}`);
 }
 await field.fill('5');await field.press('ArrowUp');assert.equal(await field.inputValue(),'6');
 await field.press('ArrowDown');assert.equal(await field.inputValue(),'5');
 // Directly bypass the browser input to verify original-string server validation.
 for(const value of ['-1','5.5','1e3','abc','5abc','5,000','',' ','2147483648']){
  const response=await page.evaluate(async (v)=>{
   const form=document.querySelector('form input[name=task][value=settings]').form;
   const data=new FormData(form);data.set('threshold',v);
   return (await fetch(form.getAttribute('action'),{method:'POST',body:data,credentials:'same-origin'})).text();
  },value);
  assert.match(response,/Use ASCII digits only|Threshold exceeds the supported maximum/);await page.goto(settings);assert.equal(await field.inputValue(),'5');
 }
 const denied=await page.evaluate(async ()=>{
  const form=document.querySelector('form input[name=task][value=settings]').form;const data=new FormData(form);data.delete('_wpnonce');
  return (await fetch(form.getAttribute('action'),{method:'POST',body:data,credentials:'same-origin'})).status;
 });assert.equal(denied,403);
 for(const [slug,name] of [['','dashboard'],['-products','low-stock-products'],['-templates','email-templates'],['-settings','settings'],['-activity','activity'],['-tools','tools']]){
  await page.goto(`${base}/wp-admin/admin.php?page=quixdevs-stocknotifier${slug}`);
  await page.locator('.qsn h1').waitFor();
  assert.equal(await page.locator('.qsn').count(),1);
  await page.screenshot({path:path.join(output,`${name}.png`),fullPage:true});
 }
 // Preview never submits email, and the editor supports a real synthetic preview.
 await page.goto(`${base}/wp-admin/admin.php?page=quixdevs-stocknotifier-templates`);
 await page.getByRole('button',{name:'Preview Email',exact:true}).click();
 await page.getByRole('heading',{name:'Email Preview — Synthetic Sample'}).waitFor();
 assert.match(await page.locator('.qsn-preview').textContent(),/Wireless Headphones/);
 await page.goto(settings);await page.setViewportSize({width:390,height:844});
 await page.screenshot({path:path.join(output,'settings-mobile.png'),fullPage:true});
 assert.equal(errors.length,0,errors.join('\n'));
 console.log('PASS: native numeric attributes, 5 valid values, 8 invalid keys, 11 invalid pastes, invalid drop, 5 input-event values, spinner controls, 9 server rejection cases, nonce rejection, 6 screens, synthetic preview, mobile layout.');
 await browser.close();
})().catch(error=>{console.error(error);process.exit(1);});
