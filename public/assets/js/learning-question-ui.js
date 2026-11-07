(() => {
    'use strict';
    const element = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const title = {choose_meaning:'Chọn nghĩa đúng', token_order:'Sắp xếp câu', match_pairs:'Chọn cặp từ',
        fill_blank:'Điền từ còn thiếu', translate_text:'Viết câu trả lời', choose_image:'Chọn hình phù hợp',
        listen_type:'Nghe và viết lại', listen_select:'Nghe và chọn đáp án'};
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function move(node, from, duration = 180) {
        if (!from || reduced() || !node.animate) return;
        const to = node.getBoundingClientRect(), x = from.left - to.left, y = from.top - to.top;
        if (Math.abs(x) + Math.abs(y) < 1) return;
        node.animate([{transform:`translate(${x}px, ${y}px)`, opacity:.8}, {transform:'translate(0, 0)', opacity:1}],
            {duration, easing:'cubic-bezier(.2,.7,.2,1)'});
    }
    window.DLQuestionUI = {mount({question:q, host, words=[], base='', autoReadDelay=0,
        onChange=()=>{}, onBrokenMedia=()=>{}}) {
        const c = q.content || {}, selected = [], paired = new Map();
        let pendingLeft = null, locked = false, autoReadTimer = null;
        host.replaceChildren(); host.className = 'dq-controls';
        const defaults = q.type === 'token_order' ? {prompt:'vi',answer:'en'}
            : q.type === 'fill_blank' ? {prompt:'en',answer:'en'} : {prompt:'und',answer:'und'};
        const language = value => {
            const en = words.some(w=>w.english===value), vi = words.some(w=>w.vietnamese===value);
            return en && !vi ? 'en' : vi && !en ? 'vi' : 'und';
        };
        const answerLang = c.answer_lang || (/tiếng Anh/i.test(c.instruction||'') ? 'en'
            : /Chọn nghĩa tiếng Việt/i.test(c.instruction||'') ? 'vi' : defaults.answer);
        const inferred = language(c.prompt), promptLang = c.prompt_lang || (inferred !== 'und' ? inferred : defaults.prompt);
        const silentTyping = ['fill_blank', 'translate_text'].includes(q.type);
        const speaker = document.getElementById('lu-question-sound'), slow = document.getElementById('lu-question-slow');
        const note = document.getElementById('lu-audio-note'); if (note) note.textContent = '';
        window.DLAudio?.configure({base, onStatus:message=>{ if (note && !locked) note.textContent=message; }});
        const wordFor = value => words.find(w=>w.english===value);
        const say = (value, lang, src=null, kind='word', rate=.95) => {
            if (lang !== 'en' || locked) return;
            clearTimeout(autoReadTimer);
            document.getElementById('lu-audio')?.pause();
            window.DLAudio?.speak(value, {lang, src:src||wordFor(value)?.audio, kind, rate});
        };
        const promptAudio = c.prompt_audio || c.audio;
        const hasPrompt = !silentTyping && (!!promptAudio || (promptLang === 'en' && !!c.prompt));
        function playPrompt(rate=1) {
            if (locked || !hasPrompt) return;
            clearTimeout(autoReadTimer);
            if (c.audio) {
                const audio = document.getElementById('lu-audio'); window.DLAudio?.stop();
                if (audio) { audio.playbackRate=rate; audio.play().catch(()=>{ if (note && !locked) note.textContent='Nhấn nút loa để nghe lại.'; }); }
            } else say(c.prompt, promptLang, promptAudio, 'example', rate);
        }
        if (speaker) { speaker.hidden=!hasPrompt; speaker.onclick=()=>playPrompt(1); }
        if (slow) { slow.hidden=!hasPrompt; slow.onclick=()=>playPrompt(.75); }
        const heading = document.getElementById('lu-instruction'); if (heading) heading.textContent=c.instruction||title[q.type]||'Trả lời câu hỏi';
        let input = null;
        const radios=[], selects=[], tokenButtons=new Map(), selectedButtons=new Map(), leftButtons=new Map(), rightButtons=new Map();
        if (c.options) {
            host.classList.add('options');
            c.options.forEach((option,index)=>{
                const label=element('label','option'), radio=element('input');
                radio.type='radio'; radio.name='answer'; radio.value=option.id; radios.push(radio);
                label.append(radio,element('b','dq-number',String(index+1)));
                if (q.type === 'choose_image') {
                    const img=element('img'); img.onerror=onBrokenMedia; img.src=window.DLAudio?.url(option.image)||'';
                    img.alt='Hình '+(index+1); label.classList.add('picture'); label.append(img);
                } else label.append(element('span','',option.text));
                radio.addEventListener('change',()=>{
                    host.querySelectorAll('.option').forEach(el=>el.classList.toggle('is-selected',el.contains(radio)));
                    say(option.text, option.lang||(answerLang!=='und'?answerLang:language(option.text)), option.audio);
                    onChange();
                }); host.append(label);
            });
        } else if (q.type === 'token_order') {
            const line=element('div','token-line'), bank=element('div','token-bank');
            line.setAttribute('role','group'); line.setAttribute('aria-label','Câu trả lời đã chọn');
            bank.setAttribute('role','group'); bank.setAttribute('aria-label','Các từ có thể chọn');
            const redraw = (movingId=null, origin=null, returning=false) => {
                const before = new Map();
                for (const [id, button] of selectedButtons) {
                    button.getAnimations?.().forEach(animation=>animation.cancel());
                    if (button.isConnected) before.set(id,button.getBoundingClientRect());
                }
                line.replaceChildren(...selected.map(id=>selectedButtons.get(id)));
                tokenButtons.forEach((button,id)=>{
                    const used=selected.includes(id); button.disabled=used; button.classList.toggle('is-used',used);
                    button.setAttribute('aria-pressed',String(used));
                });
                selected.forEach(id=>move(selectedButtons.get(id), id===movingId&&!returning ? origin : before.get(id)));
                if (returning) move(tokenButtons.get(movingId),origin);
            };
            c.tokens.forEach(token=>{
                const chosen=element('button','btn mini dq-token',token.text);
                chosen.type='button'; chosen.dataset.tokenId=token.id; chosen.setAttribute('aria-label','Bỏ từ '+token.text);
                chosen.onclick=()=>{
                    if (locked) return;
                    const origin=chosen.getBoundingClientRect(), index=selected.indexOf(token.id);
                    if (index<0) return;
                    selected.splice(index,1); redraw(token.id,origin,true); onChange();
                    tokenButtons.get(token.id).focus({preventScroll:true});
                }; selectedButtons.set(token.id,chosen);
                const button=element('button','btn mini dq-token',token.text);
                button.type='button'; button.dataset.tokenId=token.id; button.setAttribute('aria-pressed','false');
                button.onclick=()=>{
                    if (locked||selected.includes(token.id)) return;
                    const origin=button.getBoundingClientRect(); selected.push(token.id);
                    say(token.text,token.lang||answerLang,token.audio); redraw(token.id,origin); onChange();
                }; tokenButtons.set(token.id,button); bank.append(button);
            });
            const reset=element('button','btn mini dq-reset','Xếp lại'); reset.type='button';
            reset.onclick=()=>{if (!locked) {selected.length=0; redraw(); onChange();}};
            host.append(line,bank,reset);
        } else if (q.type === 'match_pairs') {
            host.classList.add('dq-matching'); const left=element('div','dq-pair-column'),right=element('div','dq-pair-column');
            const redraw=()=>{
                leftButtons.forEach((b,id)=>{b.classList.toggle('is-selected',pendingLeft===id);b.classList.toggle('is-paired',paired.has(id));});
                rightButtons.forEach((b,id)=>b.classList.toggle('is-paired',[...paired.values()].includes(id)));
                selects.forEach(s=>{s.value=paired.get(s.dataset.leftId)||'';});
            };
            c.left.forEach(item=>{
                const b=element('button','dq-pair',item.text);b.type='button';b.dataset.leftId=item.id;
                b.onclick=()=>{if(locked)return;say(item.text,item.lang||c.left_lang||language(item.text));
                    if(paired.has(item.id)){paired.delete(item.id);pendingLeft=null;}else pendingLeft=item.id;redraw();onChange();};
                leftButtons.set(item.id,b);left.append(b);
                const select=element('select','dq-legacy-select');select.dataset.leftId=item.id;select.tabIndex=-1;select.setAttribute('aria-hidden','true');
                select.add(new Option('Chọn từ',''));c.right.forEach(r=>select.add(new Option(r.text,r.id)));
                select.onchange=()=>{if(select.value)paired.set(item.id,select.value);else paired.delete(item.id);redraw();onChange();};selects.push(select);host.append(select);
            });
            c.right.forEach(item=>{
                const b=element('button','dq-pair',item.text);b.type='button';b.dataset.rightId=item.id;
                b.onclick=()=>{if(locked)return;say(item.text,item.lang||c.right_lang||language(item.text));
                    if(!pendingLeft){const existing=[...paired].find(([,id])=>id===item.id);if(existing){paired.delete(existing[0]);redraw();onChange();}return;}
                    if([...paired.values()].includes(item.id))return;paired.set(pendingLeft,item.id);pendingLeft=null;redraw();onChange();};
                rightButtons.set(item.id,b);right.append(b);
            });host.append(left,right);
        } else {
            input=element('input','answer-input');input.type='text';input.maxLength=1000;input.autocomplete='off';input.setAttribute('aria-label','Câu trả lời');
            input.placeholder=answerLang==='vi'?'Nhập câu trả lời tiếng Việt':'Nhập câu trả lời tiếng Anh';input.oninput=onChange;host.append(input);
        }
        const read=()=>c.options?{option_id:radios.find(r=>r.checked)?.value||''}
            :q.type==='token_order'?{token_ids:selected.slice()}
            :q.type==='match_pairs'?{pairs:selects.map(s=>({left_id:s.dataset.leftId,right_id:s.value}))}:{text:input?.value||''};
        const complete=()=>c.options?radios.some(r=>r.checked)
            :q.type==='token_order'?selected.length===c.tokens.length
            :q.type==='match_pairs'?selects.every(s=>s.value)&&new Set(selects.map(s=>s.value)).size===selects.length:!!input?.value.trim();
        function feedback(correct,detail={}) {
            locked=true;clearTimeout(autoReadTimer);window.DLAudio?.stop();
            if (speaker) speaker.disabled=true;if (slow) slow.disabled=true;
            host.querySelectorAll('input,button,select').forEach(e=>e.disabled=true);host.classList.add(correct?'dq-correct':'dq-wrong');
            if(c.options) for(const radio of radios){const row=radio.closest('label');if(radio.checked)row.classList.add(correct?'is-correct':'is-wrong');
                if(detail.option_results?.find(x=>x.id===radio.value)?.is_correct)row.classList.add('is-correct');}
            for(const pair of detail.pairs||[]){leftButtons.get(pair.left_id)?.classList.add(pair.is_correct?'is-correct':'is-wrong');rightButtons.get(pair.right_id)?.classList.add(pair.is_correct?'is-correct':'is-wrong');}
            const box=document.getElementById('lu-answer-feedback');
            if(box){
                box.hidden=false;box.className='dq-feedback '+(correct?'is-correct':'is-wrong');
                const icon=element('span','dq-feedback-icon',correct?'✓':'×');icon.setAttribute('aria-hidden','true');
                const copy=element('div','dq-feedback-copy');copy.append(element('strong','',correct?'Chính xác!':'Chưa đúng'));
                if(!correct&&detail.correct_text)copy.append(element('p','','Đáp án: '+detail.correct_text));
                box.replaceChildren(icon,copy);
                const footer=box.closest('.dq-next-actions');if(footer){footer.classList.toggle('is-correct',correct);footer.classList.toggle('is-wrong',!correct);}
            }
        }
        if (speaker) speaker.disabled=false;if (slow) slow.disabled=false;
        // Keyboard exercises are silent; meaning questions read English selections only.
        if(q.type!=='choose_meaning'&&hasPrompt&&!c.prompt?.includes('____')){
            if(autoReadDelay>0)autoReadTimer=setTimeout(()=>playPrompt(),autoReadDelay);else playPrompt();
        }
        return {read,complete,feedback,destroy(){locked=true;clearTimeout(autoReadTimer);window.DLAudio?.stop();host.getAnimations?.({subtree:true}).forEach(a=>a.cancel());}};
    }};
})();
