<?php
/** Pure fare calculation: amounts are integer minor currency units. */
final class TP_Fares {
    public static function money($value) {
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,7}(?:[.,]\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Bitte einen positiven Betrag mit höchstens zwei Nachkommastellen eingeben.');
        }
        $parts = explode('.', str_replace(',', '.', $value));
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
    public static function night($hour, $start, $end) {
        if ($start === $end) return false;
        return $start < $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
    }
    public static function calculate(array $s, array $trip, array $route_data = []) {
        $vehicle = null;
        foreach ($s['vehicles'] as $v) if ($v['id'] === $trip['vehicle']) $vehicle = $v;
        if (!$vehicle) throw new InvalidArgumentException('Bitte eine gültige Fahrzeugklasse wählen.');
        if ($trip['passengers'] < 1 || $trip['passengers'] > $vehicle['people'] || $trip['bags'] < 0 || $trip['bags'] > $vehicle['bags']) {
            throw new InvalidArgumentException('Die Fahrzeugklasse bietet nicht genug Platz für Personen oder Gepäck.');
        }
        $route = null;
        if ($trip['mode'] === 'fixed') {
            foreach ($s['routes'] as $r) if ($r['id'] === $trip['route']) $route = $r;
            if (!$route || !isset($route['prices'][$vehicle['id']])) throw new InvalidArgumentException('Für diese Strecke und Fahrzeugklasse ist kein Preis hinterlegt.');
        } elseif ($trip['mode'] !== 'distance') {
            throw new InvalidArgumentException('Ungültiger Berechnungsmodus.');
        }
        $lines = []; $total = 0;
        foreach ($trip['return'] ? ['outbound','inbound'] : ['outbound'] as $leg) {
            if ($route) {
                $amount = $route['prices'][$vehicle['id']];
                $label = $leg === 'outbound' ? 'Hinfahrt · Festpreis' : 'Rückfahrt · gleicher Festpreis';
            } else {
                $meters = $route_data[$leg]['meters'] ?? 0;
                if (!is_int($meters) || $meters <= 0 || $meters > $s['max_km'] * 1000) throw new InvalidArgumentException('Die Strecke liegt außerhalb der erlaubten Entfernung.');
                $amount = max($vehicle['minimum'], $vehicle['base'] + intdiv($meters * $vehicle['km'] + 500, 1000));
                $label = ($leg === 'outbound' ? 'Hinfahrt' : 'Rückfahrt') . ' · ' . number_format($meters/1000, 1, ',', '.') . ' km';
            }
            $lines[] = ['label'=>$label,'amount'=>$amount]; $total += $amount;
            $hour = (int) substr($trip[$leg === 'outbound' ? 'time' : 'return_time'], 0, 2);
            if ($s['night_fee'] && self::night($hour, $s['night_start'], $s['night_end'])) {
                $lines[] = ['label'=>'Nachtzuschlag · ' . ($leg === 'outbound' ? 'Hinfahrt' : 'Rückfahrt'), 'amount'=>$s['night_fee']];
                $total += $s['night_fee'];
            }
        }
        foreach ($trip['extras'] as $id) {
            $found = false;
            foreach ($s['extras'] as $e) if ($e['id'] === $id) {
                $found = true; $lines[] = ['label'=>$e['name'].' · einmal pro Anfrage', 'amount'=>$e['price']]; $total += $e['price'];
            }
            if (!$found) throw new InvalidArgumentException('Eine gewählte Zusatzleistung ist nicht mehr verfügbar.');
        }
        if ($total > 100000000) throw new InvalidArgumentException('Preis außerhalb des zulässigen Bereichs.');
        return ['total'=>$total, 'lines'=>$lines, 'vehicle'=>$vehicle['name'], 'currency'=>$s['currency']];
    }
}
