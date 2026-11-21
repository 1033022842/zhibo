const { chromium } = require('D:/ever/bmserv/node_modules/playwright-core');
const EXE='C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
(async()=>{
const b=await chromium.launch({executablePath:EXE,headless:true});
const c=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true,userAgent:UA});
const p=await c.newPage();
await p.goto('https://joi.com/generate/character?g=female&s=realistic&e=white&step=1',{waitUntil:'load',timeout:60000});
await p.waitForTimeout(3000);
const g=p.getByRole('button',{name:/over 18/i}).first(); if(await g.count()){await g.click({timeout:4000}).catch(()=>{});await p.waitForTimeout(1500);} 
const info=await p.evaluate(()=>({imgs:[...document.querySelectorAll('img')].map(i=>({src:i.currentSrc||i.src,alt:i.alt,w:i.naturalWidth})),bgs:[...document.querySelectorAll('*')].map(e=>getComputedStyle(e).backgroundImage).filter(x=>x&&x!=='none').slice(0,20),html:document.querySelector('main')?document.querySelector('main').innerHTML.slice(0,1500):''}));
console.log(JSON.stringify(info,null,1).slice(0,4000));
await b.close();})();
