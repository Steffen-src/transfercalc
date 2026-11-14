(() => {
  'use strict';
  document.querySelectorAll('.tp-app').forEach((root, instance) => {
    const config = JSON.parse(root.querySelector('.tp-config').textContent);
    if (!config.places) return;
    const en = config.language === 'en';
    ['origin', 'destination'].forEach(name => {
      const input = root.querySelector(`[name="${name}"]`);
      if (!input) return;
      input.setAttribute('aria-label', en ? (name === 'origin' ? 'Pickup address' : 'Destination address') : (name === 'origin' ? 'Abholadresse' : 'Zieladresse'));
      const wrap = document.createElement('div'); wrap.className = 'tp-address-picker';
      input.parentNode.insertBefore(wrap, input); wrap.append(input);
      const list = document.createElement('ul'); list.className = 'tp-address-options';
      list.id = `tp-address-${instance}-${name}`; list.role = 'listbox'; list.hidden = true;
      const hint = document.createElement('span'); hint.className = 'tp-address-status';
      hint.id = `${list.id}-status`; hint.role = 'status'; hint.setAttribute('aria-live', 'polite');
      wrap.append(list, hint);
      input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-controls', list.id); input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '));
      input.autocomplete = 'off';
      let timer, controller, revision = 0, active = -1, values = [];
      function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
      function cancel() { clearTimeout(timer); controller?.abort(); revision++; close(); }
      function choose(index) {
        if (!values[index]) return;
        input.value = values[index]; cancel(); hint.textContent = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        // Synthetic selection must not initiate another paid lookup.
        clearTimeout(timer); input.dispatchEvent(new Event('change', { bubbles: true })); input.focus();
      }
      function highlight(index) {
        active = index;
        [...list.querySelectorAll('[role="option"]')].forEach((el, i) => el.setAttribute('aria-selected', String(i === active)));
        input.setAttribute('aria-activedescendant', `${list.id}-${active}`);
      }
      input.addEventListener('input', event => {
        cancel(); hint.textContent = ''; values = [];
        const query = input.value.trim(), rev = revision;
        if (!event.isTrusted || query.length < 3 || query.length > 200) return;
        timer = setTimeout(async () => {
          controller = new AbortController(); const pending = controller;
          const timeout = setTimeout(() => pending.abort(), 10000);
          hint.textContent = en ? 'Searching addresses…' : 'Adressen werden gesucht…';
          try {
            const response = await fetch(config.endpoint + 'places', { method: 'POST', credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-TP-Nonce': config.nonce, 'X-WP-Nonce': config.restNonce },
              body: JSON.stringify({ input: query }), signal: pending.signal });
            const data = await response.json();
            if (rev !== revision || document.activeElement !== input) return;
            if (!response.ok) throw new Error('provider');
            values = data.suggestions; list.replaceChildren();
            values.forEach((value, i) => {
              const option = document.createElement('li'); option.role = 'option'; option.id = `${list.id}-${i}`;
              option.setAttribute('aria-selected', 'false'); option.textContent = value;
              option.addEventListener('pointerdown', event => { event.preventDefault(); choose(i); }); list.append(option);
            });
            if (values.length) {
              const attribution = document.createElement('li'); attribution.role = 'presentation';
              attribution.className = 'tp-address-attribution'; attribution.textContent = 'Google Maps'; attribution.translate = false;
              list.append(attribution); list.hidden = false; input.setAttribute('aria-expanded', 'true');
            }
            hint.textContent = values.length ? (en ? 'Choose an address or continue typing.' : 'Adresse auswählen oder weiterschreiben.') : (en ? 'No suggestions. Enter the full address.' : 'Keine Vorschläge. Vollständige Adresse eingeben.');
          } catch (error) {
            if (rev === revision) { close(); hint.textContent = en ? 'Suggestions unavailable. Enter the full address manually.' : 'Vorschläge nicht verfügbar. Vollständige Adresse manuell eingeben.'; }
          } finally { clearTimeout(timeout); }
        }, 450);
      });
      input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { cancel(); hint.textContent = ''; return; }
        if (list.hidden || !values.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault(); highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + values.length) % values.length);
        } else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); choose(active); }
      });
      input.addEventListener('blur', () => { cancel(); hint.textContent = ''; });
      root.querySelector('[name="mode"]')?.addEventListener('change', cancel);
    });
  });
})();
