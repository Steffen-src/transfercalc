(() => {
  'use strict';
  document.querySelectorAll('.tp-app').forEach(root => {
    const config = JSON.parse(root.querySelector('.tp-config').textContent);
    /* TP_EN_LOCALE */
    const t = value => config.language === 'en' ? (window.TransferPilotEnglish?.[value] ?? value) : value;
    const locale = config.language === 'en' ? 'en-GB' : 'de-DE';
    const dateText = value => value.split('-').reverse().join(config.language === 'en' ? '/' : '.');
    const s = config.settings, form = root.querySelector('form');
    const field = name => form.elements.namedItem(name);
    const message = root.querySelector('.tp-message');
    let step = 0, quote = null, busy = false;
    const currency = cents => new Intl.NumberFormat(locale,{style:'currency',currency:s.currency}).format(cents/100);
    const text = (tag, value, cls) => {const el=document.createElement(tag); el.textContent=value; if(cls)el.className=cls; return el;};
    const tell = (value,error=false) => {message.textContent=value;message.classList.toggle('tp-error',error);};
    function show(index) {
      step=index;
      root.querySelectorAll('[data-panel]').forEach((panel,i)=>panel.hidden=i!==index);
      root.querySelectorAll('.tp-steps li').forEach((item,i)=>{if(i===index)item.setAttribute('aria-current','step');else item.removeAttribute('aria-current'); item.classList.toggle('tp-done',i<index);});
      const heading=root.querySelector(`[data-panel="${index}"] h3`);
      heading.tabIndex=-1; heading.focus({preventScroll:true}); heading.scrollIntoView({block:'nearest',behavior:'instant'});
    }
    function trip() {
      return {
        mode:field('mode').value,route:field('route').value,origin:field('origin').value.trim(),destination:field('destination').value.trim(),
        date:field('date').value,time:field('time').value,passengers:Number(field('passengers').value),bags:Number(field('bags').value),
        return:field('return').checked,return_date:field('return_date').value,return_time:field('return_time').value,
        vehicle:form.querySelector('[name="vehicle"]:checked')?.value||'',
        extras:[...form.querySelectorAll('[name="extra"]:checked')].map(el=>el.value)
      };
    }
    function validPanel(index) {
      const panel=root.querySelector(`[data-panel="${index}"]`);
      for(const input of panel.querySelectorAll('input,select,textarea')) {
        if(input.closest('[hidden]') || input.disabled)continue;
        if(!input.checkValidity()){input.reportValidity();return false;}
      }
      const journey=trip();
      if(index===0 && journey.return && (`${journey.return_date}T${journey.return_time}`<=`${journey.date}T${journey.time}`)) {tell(t('Die Rückfahrt muss nach der Hinfahrt liegen.'),true);return false;}
      return true;
    }
    function updateSummary() {
      const journey=trip(), target=root.querySelector('.tp-trip-summary');target.replaceChildren();
      const route=s.routes.find(r=>r.id===journey.route);
      const loc=id=>s.locations.find(l=>l.id===id)?.name||'';
      const origin=journey.mode==='fixed'?(route?loc(route.from):t('Start wählen')):journey.origin||t('Abholadresse wählen');
      const destination=journey.mode==='fixed'?(route?loc(route.to):t('Ziel wählen')):journey.destination||t('Zieladresse wählen');
      target.append(text('div',origin,'tp-place'),text('span','↓','tp-route-arrow'),text('div',destination,'tp-place'));
      if(journey.date && journey.time) target.append(text('p',`${dateText(journey.date)} · ${journey.time}`));
      target.append(text('p',`${journey.passengers} ${t(journey.passengers===1?'Person':'Personen')} · ${journey.bags} ${t(journey.bags===1?'Gepäckstück':'Gepäckstücke')}${journey.return?' · '+t('mit Rückfahrt'):''}`));
      if(journey.return&&journey.return_date&&journey.return_time) target.append(text('p',`${t('Zurück:')} ${dateText(journey.return_date)} · ${journey.return_time}`));
      const lines=root.querySelector('.tp-price-lines');lines.replaceChildren();
      if(quote) {
        target.append(text('p',quote.fare.vehicle,'tp-selected-car'));
        for(const line of quote.fare.lines){const row=text('div','');row.append(text('span',line.label),text('strong',currency(line.amount)));lines.append(row);}
        if(quote.routes.outbound) {
          const attribution=text('div','','tp-google-result');
          attribution.append(text('p',`${(quote.routes.outbound.meters/1000).toLocaleString(locale,{maximumFractionDigits:1})} km · ${config.language === 'en' ? 'approx.' : 'ca.'} ${Math.ceil(quote.routes.outbound.seconds/60)} ${quote.routes.outbound.traffic ? (config.language === 'en' ? 'min · traffic forecast for pickup time' : 'Min. · Verkehrsprognose zur Abholzeit') : (config.language === 'en' ? 'min · no live traffic' : 'Min. · ohne Live-Verkehr')}`));
          const source=text('span','Google Maps','tp-google-attribution');source.setAttribute('translate','no');attribution.append(source);
          target.append(attribution);
          if(config.mapKey && quote.routes.outbound.polyline) {
            const holder=text('div','','tp-route-map');
            window.TransferPilotMap?.attach(holder,quote.routes,config,s.accent);
            target.append(holder);
          }

        }
      }
      root.querySelector('.tp-total strong').textContent=quote?currency(quote.fare.total):'—';
    }
    function vehicles() {
      const target=root.querySelector('.tp-vehicles'), selected=form.querySelector('[name="vehicle"]:checked')?.value;
      target.querySelectorAll('label,.tp-hint').forEach(el=>el.remove());
      const journey=trip(), route=s.routes.find(r=>r.id===journey.route); let available=0;
      s.vehicles.forEach((v,index)=>{
        const fits=journey.passengers<=v.people&&journey.bags<=v.bags;
        const priced=journey.mode==='distance'||(route && Object.hasOwn(route.prices,v.id));
        if(!priced)return;
        const label=text('label','',`tp-vehicle${fits?'':' tp-unavailable'}`), radio=document.createElement('input');
        radio.type='radio';radio.name='vehicle';radio.value=v.id;radio.required=true;radio.disabled=!fits;radio.checked=fits&&selected===v.id;
        const art=document.createElementNS('http://www.w3.org/2000/svg','svg');art.setAttribute('viewBox','0 0 280 105');art.setAttribute('aria-hidden','true');
        const path=document.createElementNS('http://www.w3.org/2000/svg','path');
        path.setAttribute('d',v.people>5?'M28 70V32Q28 25 40 25H214L252 54V78H28Z M55 31V53H86V31 M94 31V53H125V31 M134 31V53H165V31 M176 31V53H230L207 31Z':'M25 73L35 53L87 44L115 23H189L217 48L247 56L257 77H25Z M96 45H205L184 29H120Z');
        path.setAttribute('fill','currentColor');art.append(path);
        [72,211].forEach(cx=>{const wheel=document.createElementNS('http://www.w3.org/2000/svg','circle');wheel.setAttribute('cx',cx);wheel.setAttribute('cy','76');wheel.setAttribute('r','15');wheel.setAttribute('fill','#eee8e0');wheel.setAttribute('stroke','currentColor');wheel.setAttribute('stroke-width','7');art.append(wheel);});
        const body=text('div','','tp-vehicle-body');body.append(text('strong',v.name),text('p',v.description||t('Komfortabel zum Ziel.')),text('span',`${t('Bis')} ${v.people} ${t('Personen')} · ${v.bags} ${t('Gepäckstücke')}`));
        if(journey.mode==='fixed')body.append(text('b',`${currency(route.prices[v.id])} / ${t('einfache Fahrt')}`));else body.append(text('b',`${currency(v.km)} / km · ${t('mindestens')} ${currency(v.minimum)}`));
        if(!fits)body.append(text('small',t('Zu wenig Platz für deine Auswahl.')));
        label.append(radio,art,body,text('span',fits?t('Auswählen'):t('Nicht passend'),'tp-car-choice'));target.append(label);if(fits)available++;
      });
      if(!available) target.append(text('p',t('Für diese Strecke und Personenzahl ist keine Fahrzeugklasse eingerichtet. Bitte ändere deine Auswahl.'),'tp-hint'));
      root.querySelector('.tp-calculate').disabled=!available||!config.ready;
    }
    for(const extra of s.extras){const label=text('label','','tp-check'),input=document.createElement('input');input.type='checkbox';input.name='extra';input.value=extra.id;const span=text('span',extra.name);span.append(text('small',`${currency(extra.price)} · ${t('einmal pro Anfrage')}`));label.append(input,span);root.querySelector('.tp-extras').append(label);}
    if(!s.extras.length) root.querySelector('.tp-extras').hidden=true;
    function modeChange(){const distance=field('mode').value==='distance';root.querySelector('.tp-fixed').hidden=distance;root.querySelector('.tp-distance').hidden=!distance;field('route').required=!distance;field('origin').required=distance;field('destination').required=distance;}
    function returnChange(){const back=field('return').checked;root.querySelector('.tp-return').hidden=!back;field('return_date').required=back;field('return_time').required=back;field('return_date').min=field('date').value||config.today;}
    function invalidate(){quote=null;updateSummary();}
    form.addEventListener('change',event=>{
      if(event.target.name==='mode')modeChange();
      if(['return','date'].includes(event.target.name))returnChange();
      if(!['name','email','phone','notes','consent','website'].includes(event.target.name))invalidate();
    });
    form.addEventListener('input',event=>{if(['origin','destination','passengers','bags'].includes(event.target.name))invalidate();});
    function waiting(on,label){busy=on;form.querySelectorAll('button').forEach(b=>b.disabled=on);root.setAttribute('aria-busy',String(on));tell(on?label:'');}
    async function request(path,body){
      const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),40000);
      try {const response=await fetch(config.endpoint+path,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-TP-Nonce':config.nonce,'X-WP-Nonce':config.restNonce},body:JSON.stringify(body),signal:controller.signal});let data;try{data=await response.json();}catch{throw new Error(t('Der Server hat keine gültige Antwort geliefert. Bitte erneut versuchen.'));}if(!response.ok)throw new Error(data.message||t('Die Anfrage konnte nicht verarbeitet werden.'));return data;}finally{clearTimeout(timer);}
    }
    root.querySelector('.tp-next').addEventListener('click',()=>{tell('');if(validPanel(0)){vehicles();show(1);}});
    root.querySelectorAll('.tp-back').forEach(button=>button.addEventListener('click',()=>{if(!busy){tell('');show(Math.max(0,step-1));}}));
    root.querySelector('.tp-calculate').addEventListener('click',async()=>{
      if(busy||!validPanel(1))return;
      waiting(true,t('Dein Preis wird berechnet …'));
      try {quote=await request('quote',trip());waiting(false);updateSummary();show(2);}catch(error){waiting(false);tell(error.name==='AbortError'?t('Die Berechnung dauert zu lange. Bitte erneut versuchen.'):error.message,true);}
    });
    form.addEventListener('submit',async event=>{
      event.preventDefault();if(busy||step!==2||!validPanel(2))return;
      if(!quote){tell(t('Bitte zuerst deinen Preis neu berechnen.'),true);return;}
      waiting(true,t('Deine Fahrtanfrage wird gespeichert …'));
      try {
        const result=await request('enquiry',{token:quote.token,name:field('name').value.trim(),email:field('email').value.trim(),phone:field('phone').value.trim(),notes:field('notes').value.trim(),consent:field('consent').checked,website:field('website').value});
        waiting(false);form.hidden=true;root.querySelector('.tp-steps').hidden=true;
        const success=root.querySelector('.tp-success');success.append(text('span','✓','tp-success-icon'),text('h3',t('Gut angekommen. Deine Anfrage ist da.')),text('p',result.message),text('strong',result.reference),text('p',t('Bitte notiere deine Referenz. Die Fahrt ist noch nicht verbindlich bestätigt.')));success.hidden=false;success.focus();
      }catch(error){waiting(false);tell(error.name==='AbortError'?t('Die Antwort dauert länger. Bitte erneut senden; dieselbe Anfrage wird nicht doppelt gespeichert.'):error.message,true);}
    });
    if(!config.ready)form.querySelectorAll('button').forEach(b=>b.disabled=true);
    modeChange();returnChange();updateSummary();
  });
})();
