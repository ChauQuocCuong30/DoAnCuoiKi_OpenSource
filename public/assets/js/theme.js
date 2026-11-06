/* One preference for every page. White is the first-visit default. */
(() => {
 'use strict';
 const key='doulingo_theme',root=document.documentElement;
 const valid=v=>v==='dark'?'dark':'light';
 function apply(mode){
  root.dataset.theme=valid(mode);
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
   const dark=root.dataset.theme==='dark';
   button.setAttribute('aria-pressed',String(dark));
   button.setAttribute('aria-label',dark?'Bật chế độ sáng':'Bật chế độ tối');
   button.title=button.getAttribute('aria-label');
   button.querySelector('[data-theme-icon]').textContent=dark?'☀':'☾';
  });
  const meta=document.querySelector('meta[name="theme-color"]');if(meta)meta.content=root.dataset.theme==='dark'?'#131f24':'#ffffff';
 }
 let saved='light';try{saved=valid(localStorage.getItem(key));}catch{}
 apply(saved);
 document.addEventListener('DOMContentLoaded',()=>apply(root.dataset.theme));
 document.addEventListener('click',event=>{
  if(!event.target.closest('[data-theme-toggle]'))return;
  const next=root.dataset.theme==='dark'?'light':'dark';
  apply(next);try{localStorage.setItem(key,next);}catch{}
 });
 window.addEventListener('storage',event=>{if(event.key===key||event.key===null)apply(valid(event.newValue));});
})();
