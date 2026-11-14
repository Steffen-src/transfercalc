(() => {
  'use strict';
  let loader;
  function load(key, language) {
    if (window.google?.maps?.Map) return Promise.resolve(window.google.maps);
    if (loader) return loader;
    loader = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      const timer = setTimeout(() => reject(new Error('timeout')), 15000);
      window.tpMapReady = () => { clearTimeout(timer); resolve(window.google.maps); delete window.tpMapReady; };
      script.onerror = () => { clearTimeout(timer); reject(new Error('network')); };
      script.src = 'https://maps.googleapis.com/maps/api/js?' + new URLSearchParams({ key, v: 'quarterly', loading: 'async', callback: 'tpMapReady', language });
      script.async = true; document.head.append(script);
    });
    return loader;
  }
  function decode(encoded) {
    let i = 0, lat = 0, lng = 0; const points = [];
    function number() {
      let value = 0, shift = 0, part;
      do {
        if (i >= encoded.length || shift > 30) throw new Error('Invalid route');
        part = encoded.charCodeAt(i++) - 63;
        if (part < 0 || part > 63) throw new Error('Invalid route');
        value |= (part & 31) << shift; shift += 5;
      } while (part >= 32);
      return value & 1 ? ~(value >> 1) : value >> 1;
    }
    while (i < encoded.length) {
      lat += number(); lng += number();
      if (Math.abs(lat) > 9000000 || Math.abs(lng) > 18000000) throw new Error('Invalid coordinates');
      points.push({ lat: lat / 1e5, lng: lng / 1e5 });
    }
    if (points.length < 2) throw new Error('Empty route');
    return points;
  }
  window.TransferPilotMap = { attach(holder, routes, config, accent) {
    const en = config.language === 'en';
    const button = document.createElement('button'); button.type = 'button'; button.className = 'tp-map-button';
    button.textContent = en ? 'Show route on map' : 'Route auf Karte anzeigen';
    const note = document.createElement('p'); note.className = 'tp-map-note';
    note.textContent = en ? 'Click to load Google Maps. Your IP address and browser information will be sent to Google. See the privacy notice.' : 'Per Klick wird Google Maps geladen. Dabei werden IP-Adresse und Browserinformationen an Google übertragen. Bitte Datenschutzhinweise beachten.';
    const privacy = document.createElement('a'); privacy.href = config.settings.privacy_url;
    privacy.textContent = en ? ' Privacy notice' : ' Datenschutzhinweise'; note.append(privacy);
    const status = document.createElement('p'); status.role = 'status';
    holder.append(button, note, status);
    button.addEventListener('click', async () => {
      button.disabled = true; status.textContent = en ? 'Loading map…' : 'Karte wird geladen…';
      try {
        const paths = [decode(routes.outbound.polyline)];
        if (routes.inbound?.polyline) paths.push(decode(routes.inbound.polyline));
        const maps = await load(config.mapKey, config.language);
        if (!holder.isConnected) return;
        const canvas = document.createElement('div'); canvas.className = 'tp-map-canvas';
        canvas.setAttribute('aria-label', en ? 'Calculated driving route' : 'Berechnete Fahrstrecke'); holder.append(canvas);
        const map = new maps.Map(canvas, { center: paths[0][0], zoom: 10, gestureHandling: 'cooperative', mapTypeControl: false, streetViewControl: false, fullscreenControl: true });
        const bounds = new maps.LatLngBounds();
        paths.forEach((path, i) => {
          new maps.Polyline({ map, path, strokeColor: i ? '#394550' : accent, strokeOpacity: 0.9, strokeWeight: i ? 3 : 5 });
          path.forEach(point => bounds.extend(point));
        });
        [paths[0][0], paths[0].at(-1)].forEach((center, i) => new maps.Circle({ map, center, radius: 90, fillColor: i ? '#25231f' : '#3b7753', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 }));
        map.fitBounds(bounds, 30); button.hidden = true;
        status.textContent = en ? 'Green: pickup · Dark: destination. Return route shown if calculated. Approximate route, not navigation.' : 'Grün: Abholung · Dunkel: Ziel. Rückroute sichtbar, sofern berechnet. Ungefähre Strecke, keine Navigation.';
      } catch {
        button.disabled = false; status.textContent = en ? 'Map unavailable. Your quote and enquiry still work.' : 'Karte nicht verfügbar. Preis und Fahrtanfrage bleiben nutzbar.';
      }
    });
  }};
})();
