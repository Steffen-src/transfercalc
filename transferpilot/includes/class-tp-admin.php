<?php
if (!defined('ABSPATH')) exit;
final class TP_Admin {
    public static function menu() {
        add_menu_page('TransferPilot','TransferPilot','manage_options','transferpilot',[self::class,'dashboard'],'dashicons-airplane',58);
        add_submenu_page('transferpilot',TP_I18n::t('Einrichtung'),TP_I18n::t('Einrichtung'),'manage_options','tp-settings',[self::class,'settings_page']);
    }
    public static function assets($hook) {
        if (!str_contains($hook,'transferpilot') && !str_contains($hook,'tp-settings') && !str_contains($hook,'tp-diagnostics')) return;
        wp_enqueue_style('tp-admin',plugins_url('../assets/admin.css',__FILE__),[],TP_VERSION);
        wp_enqueue_script('tp-admin',plugins_url('../assets/admin.js',__FILE__),[],TP_VERSION,true);
    }
    private static function guard($nonce) { if(!current_user_can('manage_options')) wp_die(TP_I18n::t('Keine Berechtigung.'),403); check_admin_referer($nonce); }
    private static function posted() { return wp_unslash($_POST); }
    private static function go($page,$notice) { wp_safe_redirect(add_query_arg(['page'=>$page,'tp_notice'=>$notice],admin_url('admin.php'))); exit; }
    public static function head($title,$subtitle) {
        echo '<div class="wrap tpa"><header class="tpa-header"><div><span class="tpa-brand">↗ TRANSFERPILOT <small>'.esc_html(TP_VERSION).'</small></span><h1>'.esc_html($title).'</h1><p>'.esc_html($subtitle).'</p></div><a class="tpa-button" href="'.esc_url(admin_url('admin.php?page=tp-settings')).TP_I18n::html('">Einrichtung & Tarife ↗</a></header>');
        if(isset($_GET['tp_notice'])) echo '<div class="notice notice-info"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['tp_notice']))).'</p></div>';
    }
    private static function field($label,$name,$value,$type='text',$attrs='') {
        echo '<label class="tpa-field">'.esc_html($label).'<input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.$attrs.'></label>';
    }
    private static function amount($value) { return number_format($value/100,2,'.',''); }
    public static function settings_page() {
        if(!current_user_can('manage_options')) return;
        $s=TP_Plugin::settings(); self::head(TP_I18n::t('Bereit für die nächste Fahrt.'),TP_I18n::t('Richte deinen Betrieb ein. Es werden keine fremden Strecken, Preise oder Empfänger vorgegeben.'));
        echo TP_I18n::html('<nav class="tpa-nav"><a href="#tp-general">01 Betrieb</a><a href="#tp-locations">02 Orte</a><a href="#tp-vehicles">03 Fahrzeuge</a><a href="#tp-routes">04 Strecken</a><a href="#tp-extras">05 Extras</a><a href="#tp-integrations">06 Integration</a></nav>');
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="tpa-settings"><input type="hidden" name="action" value="tp_save">'; wp_nonce_field('tp_save');
        echo TP_I18n::html('<section class="tpa-card" id="tp-general"><h2>01 · Dein Betrieb</h2><p>Diese Angaben erscheinen im Transferrechner. Alle Preise werden in der gewählten Währung angezeigt.</p><div class="tpa-grid">');
        self::field(TP_I18n::t('Anzeigename'),'brand',$s['brand'],'text','required maxlength="80"'); self::field(TP_I18n::t('Akzentfarbe'),'accent',$s['accent'],'color');
        echo '<label class="tpa-field">Sprache / Language<select name="language"><option value="de" '.selected($s['language'],'de',false).'>Deutsch</option><option value="en" '.selected($s['language'],'en',false).'>English</option></select></label>';
        self::field(TP_I18n::t('Empfänger für interne Benachrichtigungen'),'recipient',$s['recipient'],'email','required');
        self::field(TP_I18n::t('URL deiner Datenschutzhinweise'),'privacy_url',$s['privacy_url'],'url','required');
        self::field(TP_I18n::t('URL deiner Nutzungsbedingungen (für Google-Routen)'),'terms_url',$s['terms_url'],'url');
        echo TP_I18n::html('<label class="tpa-field">Währung<select name="currency">'); foreach(['EUR','USD','GBP','CHF','TRY'] as $c) echo '<option '.selected($s['currency'],$c,false).'>'.esc_html($c).'</option>'; echo '</select></label>';
        self::field(TP_I18n::t('Mindestvorlauf in Stunden'),'min_hours',$s['min_hours'],'number','min="0" max="720"');
        self::field(TP_I18n::t('Maximal im Voraus (Tage)'),'max_days',$s['max_days'],'number','min="1" max="730"');
        self::field(TP_I18n::t('Anfragen löschen nach (Tage)'),'retention',$s['retention'],'number','min="1" max="730"');
        echo TP_I18n::html('</div><label class="tpa-field">Preishinweis / enthaltene Leistungen<textarea name="price_note" rows="2" maxlength="500" required>').esc_textarea($s['price_note']).TP_I18n::html('</textarea></label><p class="tpa-note">Die Tarife sind Endbeträge des Betreibers. Weise auf deiner Website korrekt aus, ob und welche Steuern enthalten sind. Das Plugin berechnet keine zusätzlichen Steuern. Alle Fahrzeiten richten sich nach der WordPress-Zeitzone: <strong>').esc_html(wp_timezone_string()).'</strong>.</p></section>';
        foreach(['locations'=>TP_I18n::t('Orte & Flughäfen'),'vehicles'=>TP_I18n::t('Fahrzeugklassen'),'routes'=>TP_I18n::t('Festpreisstrecken'),'extras'=>TP_I18n::t('Zusatzleistungen')] as $type=>$label) {
            echo '<section class="tpa-card" id="tp-'.esc_attr($type).'"><h2>'.esc_html($label).'</h2>';
            if($type==='routes') echo TP_I18n::html('<p>Jede Strecke hat eine Richtung. Bei Rückfahrt wird dieselbe Verbindung umgekehrt zum gleichen Festpreis berechnet. Leeres Preisfeld = diese Fahrzeugklasse wird nicht angeboten. Nach dem Anlegen neuer Orte/Fahrzeuge zuerst speichern, dann die Strecke hinzufügen.</p>');
            if($type==='vehicles') echo TP_I18n::html('<p>Kapazität gilt für jede einzelne Fahrt. Kilometerpreise gelten nur bei aktivierter freier Straßenberechnung. Preise eingeben z. B. 49,90.</p>');
            if($type==='extras') echo TP_I18n::html('<p>Jede Zusatzleistung wird genau einmal pro gesamter Anfrage berechnet, auch bei Rückfahrt. Bezeichne Leistungen entsprechend eindeutig.</p>');
            echo '<div data-rows="'.esc_attr($type).'">'; foreach($s[$type] as $i=>$row) self::row($type,$i,$row,$s); echo '</div>';
            echo '<template data-template="'.esc_attr($type).'">'; self::row($type,'__INDEX__',[],$s); echo '</template><button type="button" class="tpa-button secondary" data-add="'.esc_attr($type).TP_I18n::html('">+ Hinzufügen</button></section>');
        }
        echo TP_I18n::html('<section class="tpa-card"><h2>Zuschläge</h2><p>Nachtzuschlag pro Fahrt. Bei Hin- und Rückfahrt wird die jeweilige Abholzeit einzeln geprüft. Gleiche Start-/Endstunde deaktiviert das Nachtfenster.</p><div class="tpa-grid">');
        self::field(TP_I18n::t('Nacht beginnt (Stunde)'),'night_start',$s['night_start'],'number','min="0" max="23"');
        self::field(TP_I18n::t('Nacht endet (Stunde)'),'night_end',$s['night_end'],'number','min="0" max="23"');
        self::field(TP_I18n::t('Nachtzuschlag je Fahrt'),'night_fee',self::amount($s['night_fee']),'text','inputmode="decimal" required');
        echo TP_I18n::html('</div></section><section class="tpa-card" id="tp-integrations"><h2>Integration & freie Straßenroute</h2><p>Füge den Shortcode <code>[transferpilot]</code> in eine beliebige WordPress-Seite ein. Für die Anfragefunktion müssen Empfänger, Datenschutz-URL und ein nutzbarer Tarif eingerichtet sein.</p><label class="tpa-field">Berechnungsarten<select name="mode"><option value="fixed" ').selected($s['mode'],'fixed',false).TP_I18n::html('>Nur hinterlegte Festpreisstrecken · ohne Google</option><option value="both" ').selected($s['mode'],'both',false).TP_I18n::html('>Festpreise und freie Straßenberechnung mit Google</option></select></label><div class="tpa-grid">');
        self::field(TP_I18n::t('Maximale Entfernung je Fahrt (km)'),'max_km',$s['max_km'],'number','min="1" max="5000"');
        self::field(TP_I18n::t('Neuer Google Routes API-Schlüssel'),'google_key','','password',TP_I18n::html('autocomplete="new-password" placeholder="Leer lassen, um vorhandenen Schlüssel zu behalten"'));
        $places_en=TP_I18n::language()==='en';
        echo '<label><input type="checkbox" name="traffic" value="1" '.checked(!empty($s['traffic']),true,false).'> '.esc_html($places_en?'Traffic-aware routing (optional, Google Pro pricing)':'Verkehrsabhängige Route (optional, Google-Pro-Tarif)').'</label><p>'.esc_html($places_en?'Uses the selected pickup time for a traffic forecast, not a guaranteed arrival time. No automatic congestion surcharge; a different route distance can change kilometre-based fares. Fixed fares are unchanged.':'Verwendet die gewählte Abholzeit für eine Verkehrsprognose, keine garantierte Ankunftszeit. Kein automatischer Stauzuschlag; eine andere Streckenlänge kann Kilometerpreise ändern. Festpreise bleiben unverändert.').'</p>';

        echo '<label><input type="checkbox" name="map" value="1" '.checked(!empty($s['map']),true,false).'> '.esc_html($places_en?'Enable optional route map (loads on visitor click)':'Optionale Streckenkarte aktivieren (lädt nach Klick)').'</label>';
        self::field($places_en?'Maps JavaScript browser key (public; restrict to website referrers)':'Maps-JavaScript-Browserschlüssel (öffentlich; auf Website-Referrer beschränken)','maps_browser_key','','password','autocomplete="new-password"');
        echo '<label><input type="checkbox" name="delete_maps_key" value="1"> '.esc_html($places_en?'Remove saved browser key':'Gespeicherten Browserschlüssel entfernen').'</label><p>'.esc_html($places_en?'Use a SEPARATE browser key restricted to Maps JavaScript API and your website HTTP referrers. Never enter a Routes/Places server key here. Browser keys are necessarily visible to visitors. The map loads only on click; update your privacy notice and consent process. Dynamic Maps has separate usage charges.':'Einen SEPARATEN Browserschlüssel verwenden, beschränkt auf Maps JavaScript API und die HTTP-Referrer der Website. Hier niemals einen Routes-/Places-Serverschlüssel eintragen. Browserschlüssel sind für Besucher sichtbar. Die Karte lädt erst nach Klick; Datenschutzhinweise und Einwilligungsablauf ergänzen. Dynamic Maps wird gesondert abgerechnet.').'</p>';
        self::field($places_en?'New Places API (New) key':'Neuer Places API (New)-Schlüssel','places_key','','password','autocomplete="new-password"');
        echo '<label><input type="checkbox" name="places_enabled" value="1" '.checked(get_option('tp_places_enabled'),true,false).'> '.esc_html($places_en?'Enable address suggestions (additional Google usage charges)':'Adressvorschläge aktivieren (zusätzliche Google-Nutzungskosten)').'</label>';
        echo '<label><input type="checkbox" name="delete_places_key" value="1"> '.esc_html($places_en?'Remove saved Places key':'Gespeicherten Places-Schlüssel entfernen').'</label><p>'.esc_html($places_en?'Requires Places API (New), billing, public privacy/terms pages and an API-restricted server key. Each search request is billed separately; no session discount. At least 3 characters, 450 ms delay, up to 5 suggestions. Limits: 60 searches / 15 minutes / IP and 1,000 / day / site (best effort, not a billing guarantee). Manual entry remains available.':'Benötigt Places API (New), Abrechnung, öffentliche Datenschutz-/Nutzungsseiten und einen API-beschränkten Serverschlüssel. Jede Suchanfrage wird einzeln abgerechnet; kein Sitzungsrabatt. Ab 3 Zeichen, 450 ms Wartezeit, bis zu 5 Vorschläge. Grenzen: 60 Suchen / 15 Minuten / IP und 1.000 / Tag / Website (nach bestem Bemühen, keine Kostengarantie). Manuelle Eingabe bleibt möglich.').'</p>';
        echo '</div><p>'.(get_option('tp_google_key')?TP_I18n::t('✓ Ein Schlüssel ist gespeichert. Er wird niemals an den Browser übertragen.'):TP_I18n::t('Noch kein Google-Schlüssel gespeichert.')).TP_I18n::html('</p><label><input type="checkbox" name="delete_key" value="1"> Gespeicherten Schlüssel entfernen</label><p class="tpa-note">Optionaler externer Dienst: Google Routes API mit aktiviertem Abrechnungskonto. Der Betreiber trägt mögliche Nutzungskosten. Schlüssel auf die Routes API und, soweit vom Hosting unterstützt, auf Server-IP-Adressen beschränken. Adressvorschläge sind über die zusätzliche Places API (New) optional verfügbar. Die optionale Karte benötigt einen separaten Maps-JavaScript-Browserschlüssel. Ohne gültige Route gibt es keinen geschätzten Fantasiepreis.</p><p>Erste Einrichtung: Preise speichern → Seite mit Shortcode anlegen → Testanfrage senden → Eingang im Dashboard und tatsächliche Mailzustellung prüfen. Cache für die Formularseite ausschließen. Server-Cron für zuverlässige Datenlöschung konfigurieren.</p></section><div class="tpa-save"><button class="tpa-button" type="submit">Einstellungen speichern ↗</button><a href="').esc_url(admin_url('admin.php?page=transferpilot')).TP_I18n::html('">Zu den Anfragen</a></div></form></div>');
    }
    private static function select($label,$name,$value,$items) {
        echo '<label class="tpa-field">'.esc_html($label).'<select name="'.esc_attr($name).TP_I18n::html('" required><option value="">Bitte wählen</option>');
        foreach($items as $item) echo '<option value="'.esc_attr($item['id']).'" '.selected($value,$item['id'],false).'>'.esc_html($item['name']).'</option>'; echo '</select></label>';
    }
    private static function row($type,$i,$row,$s) {
        $n=$type.'['.$i.']'; echo '<div class="tpa-row"><input type="hidden" name="'.esc_attr($n.'[id]').'" value="'.esc_attr($row['id']??'').'">';
        if($type==='locations') { self::field(TP_I18n::t('Bezeichnung'),$n.'[name]',$row['name']??'','text','required maxlength="100"'); }
        if($type==='vehicles') {
            self::field(TP_I18n::t('Name'),$n.'[name]',$row['name']??'','text','required maxlength="100"');
            self::field(TP_I18n::t('Kurzbeschreibung'),$n.'[description]',$row['description']??'','text','maxlength="180"');
            self::field(TP_I18n::t('Personen maximal'),$n.'[people]',$row['people']??3,'number','min="1" max="100" required');
            self::field(TP_I18n::t('Gepäckstücke maximal'),$n.'[bags]',$row['bags']??3,'number','min="0" max="100" required');
            foreach(['base'=>TP_I18n::t('Grundpreis'),'km'=>TP_I18n::t('Preis pro km'),'minimum'=>TP_I18n::t('Mindestpreis')] as $key=>$label) self::field($label,$n.'['.$key.']',self::amount($row[$key]??0),'text','inputmode="decimal" required');
        }
        if($type==='routes') {
            self::select(TP_I18n::t('Start'),$n.'[from]',$row['from']??'',$s['locations']); self::select(TP_I18n::t('Ziel'),$n.'[to]',$row['to']??'',$s['locations']);
            foreach($s['vehicles'] as $v) self::field(TP_I18n::t('Preis · ').$v['name'],$n.'[prices]['.$v['id'].']',isset($row['prices'][$v['id']])?self::amount($row['prices'][$v['id']]):'','text',TP_I18n::html('inputmode="decimal" placeholder="Nicht angeboten"'));
        }
        if($type==='extras') { self::field(TP_I18n::t('Bezeichnung'),$n.'[name]',$row['name']??'','text','required maxlength="100"'); self::field(TP_I18n::t('Preis einmal pro Anfrage'),$n.'[price]',self::amount($row['price']??0),'text','inputmode="decimal" required'); }
        echo TP_I18n::html('<button type="button" class="tpa-remove" aria-label="Diesen Eintrag entfernen">Entfernen ×</button></div>');
    }
    private static function integer($value,$min,$max) {
        if(filter_var($value,FILTER_VALIDATE_INT)===false || $value<$min || $value>$max) throw new InvalidArgumentException(TP_I18n::t('Eine Zahl liegt außerhalb des erlaubten Bereichs.')); return (int)$value;
    }
    public static function save() {
        self::guard('tp_save'); $p=self::posted();
        try {
            $s=TP_Plugin::defaults();
            $s['map']=!empty($p['map']);
            $s['traffic']=!empty($p['traffic']);
            $s['language']=($p['language']??'de')==='en'?'en':'de';
            foreach(['brand'=>80,'price_note'=>500] as $k=>$len) { $s[$k]=sanitize_text_field($p[$k]??''); if(!$s[$k] || strlen($s[$k])>$len) throw new InvalidArgumentException(TP_I18n::t('Anzeigename oder Preishinweis fehlt bzw. ist zu lang.')); }
            $s['accent']=sanitize_hex_color($p['accent']??'')?:'#b78349';
            $s['recipient']=sanitize_email($p['recipient']??''); if(!is_email($s['recipient'])) throw new InvalidArgumentException(TP_I18n::t('Bitte eine gültige Empfänger-E-Mail angeben.'));
            $s['privacy_url']=esc_url_raw($p['privacy_url']??'',['http','https']); if(!$s['privacy_url']) throw new InvalidArgumentException(TP_I18n::t('Bitte eine Datenschutz-URL angeben.'));
            $s['terms_url']=esc_url_raw($p['terms_url']??'',['http','https']);
            $s['currency']=$p['currency']??'EUR'; if(!in_array($s['currency'],['EUR','USD','GBP','CHF','TRY'],true)) throw new InvalidArgumentException(TP_I18n::t('Ungültige Währung.'));
            foreach(['min_hours'=>[0,720],'max_days'=>[1,730],'max_km'=>[1,5000],'night_start'=>[0,23],'night_end'=>[0,23],'retention'=>[1,730]] as $k=>$bounds) $s[$k]=self::integer($p[$k]??'',...$bounds);
            if($s['min_hours'] >= $s['max_days']*24) throw new InvalidArgumentException(TP_I18n::t('Der Mindestvorlauf muss kürzer als der maximale Vorbuchungszeitraum sein.'));
            $s['night_fee']=TP_Fares::money($p['night_fee']??0); $s['mode']=($p['mode']??'')==='both'?'both':'fixed';
            foreach(['locations','vehicles','routes','extras'] as $type) {
                $rows=$p[$type]??[]; if(!is_array($rows) || count($rows)>100 || ($type==='extras'&&count($rows)>20)) throw new InvalidArgumentException(TP_I18n::t('Zu viele oder ungültige Einträge.'));
                $ids=[];
                foreach($rows as $row) {
                    if(!is_array($row)) throw new InvalidArgumentException(TP_I18n::t('Ungültiger Eintrag.'));
                    $id=sanitize_key($row['id']??'')?:wp_generate_uuid4(); if(isset($ids[$id])) throw new InvalidArgumentException(TP_I18n::t('Doppelte Einträge erkannt.')); $ids[$id]=true;
                    $out=['id'=>$id];
                    if($type!=='routes') { $out['name']=sanitize_text_field($row['name']??''); if(!$out['name']||strlen($out['name'])>100) throw new InvalidArgumentException(TP_I18n::t('Eine Bezeichnung fehlt oder ist zu lang.')); }
                    if($type==='vehicles') {
                        $out['description']=substr(sanitize_text_field($row['description']??''),0,180);
                        $out['people']=self::integer($row['people']??0,1,100); $out['bags']=self::integer($row['bags']??0,0,100);
                        foreach(['base','km','minimum'] as $k) $out[$k]=TP_Fares::money($row[$k]??0);
                    }
                    if($type==='extras') $out['price']=TP_Fares::money($row['price']??0);
                    if($type==='routes') {
                        $location_ids=array_column($s['locations'],'id');
                        $out['from']=sanitize_key($row['from']??''); $out['to']=sanitize_key($row['to']??'');
                        if(!in_array($out['from'],$location_ids,true)||!in_array($out['to'],$location_ids,true)||$out['from']===$out['to']) throw new InvalidArgumentException(TP_I18n::t('Strecken brauchen zwei unterschiedliche vorhandene Orte.'));
                        $out['prices']=[];
                        foreach($s['vehicles'] as $v) if(isset($row['prices'][$v['id']]) && trim($row['prices'][$v['id']])!=='') $out['prices'][$v['id']]=TP_Fares::money($row['prices'][$v['id']]);
                        if(!$out['prices']) throw new InvalidArgumentException(TP_I18n::t('Für jede Strecke mindestens einen Fahrzeugpreis hinterlegen.'));
                        foreach($s['routes'] as $old) if($old['from']===$out['from']&&$old['to']===$out['to']) throw new InvalidArgumentException(TP_I18n::t('Diese Streckenrichtung ist bereits vorhanden.'));
                    }
                    $s[$type][]=$out;
                }
            }
            $key=trim($p['google_key']??''); if($key && !preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$key)) throw new InvalidArgumentException(TP_I18n::t('Der Google-Schlüssel hat ein ungültiges Format.'));
            $maps_key=trim($p['maps_browser_key']??'');
            if($maps_key && !preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$maps_key)) throw new InvalidArgumentException('Invalid Maps browser key format.');
            if($maps_key && (hash_equals((string)get_option('tp_google_key',''),$maps_key)||hash_equals((string)get_option('tp_places_key',''),$maps_key)||(!empty($p['google_key'])&&hash_equals(trim($p['google_key']),$maps_key))||(!empty($p['places_key'])&&hash_equals(trim($p['places_key']),$maps_key)))) throw new InvalidArgumentException('Use a separate Maps JavaScript browser key, never a server key.');
            if(!empty($p['delete_maps_key'])) delete_option('tp_maps_browser_key'); elseif($maps_key) update_option('tp_maps_browser_key',$maps_key,false);
            $places_key=trim($p['places_key']??'');
            if($places_key && !preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$places_key)) throw new InvalidArgumentException('Invalid Places API key format.');
            if(!empty($p['delete_places_key'])) delete_option('tp_places_key'); elseif($places_key) update_option('tp_places_key',$places_key,false);
            update_option('tp_places_enabled',!empty($p['places_enabled']),false);
            update_option('tp_settings',$s,false);
            if(!empty($p['delete_key'])) delete_option('tp_google_key'); elseif($key) update_option('tp_google_key',$key,false);
            self::go('tp-settings',TP_I18n::t('Gespeichert. Bitte den Rechner mit deinen Tarifen testen.'));
        } catch(Throwable $e) { wp_die(esc_html(TP_I18n::t($e->getMessage())).' '.esc_html(TP_I18n::t('Bitte zurückgehen und die Eingaben korrigieren.')),TP_I18n::t('Einstellungen nicht gespeichert'),['response'=>422,'back_link'=>true]); }
    }
    public static function dashboard() {
        if(!current_user_can('manage_options')) return; global $wpdb;
        self::head(TP_I18n::t('Deine Transfers. Im Blick.'),TP_I18n::t('Fahrtanfragen prüfen und persönlich beantworten. Es erfolgt keine automatische Buchungsbestätigung.'));
        $table=TP_Plugin::table(); $s=TP_Plugin::settings();
        $counts=$wpdb->get_results("SELECT status,COUNT(*) AS n FROM $table GROUP BY status",OBJECT_K); $all=array_sum(array_map(fn($r)=>(int)$r->n,$counts));
        echo '<div class="tpa-stats">'; foreach(['new'=>TP_I18n::t('Neue Anfragen'),'contacted'=>TP_I18n::t('In Bearbeitung'),'closed'=>TP_I18n::t('Abgeschlossen')] as $key=>$label) echo '<div><span>'.esc_html($label).'</span><strong>'.(int)($counts[$key]->n??0).'</strong></div>'; echo TP_I18n::html('<div><span>Gesamt gespeichert</span><strong>').(int)$all.'</strong></div></div>';
        if(!$s['vehicles'] || (!$s['routes']&&$s['mode']==='fixed') || !$s['recipient'] || !$s['privacy_url']) echo TP_I18n::html('<div class="tpa-card"><h2>Willkommen an Bord.</h2><p>Beginne mit Betrieb, Orten und Fahrzeugklassen. Danach legst du die Preise fest und bindest <code>[transferpilot]</code> auf deiner Seite ein.</p><a class="tpa-button" href="').esc_url(admin_url('admin.php?page=tp-settings')).TP_I18n::html('">Einrichtung starten ↗</a></div>');
        if(!empty($_GET['view'])) {
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($_GET['view'])));
            if($row) {
                $d=json_decode($row->details,true); echo '<section class="tpa-card"><h2>'.esc_html($row->reference).'</h2><p>'.esc_html($row->name).' · '.esc_html($row->email).' · '.esc_html($row->phone).'</p><dl class="tpa-details">';
                foreach([TP_I18n::t('Strecke')=>$d['route_name']??(($d['trip']['origin']??'').' → '.($d['trip']['destination']??'')),TP_I18n::t('Abholung')=>($d['trip']['date']??'').' '.($d['trip']['time']??''),TP_I18n::t('Rückfahrt')=>!empty($d['trip']['return'])?($d['trip']['return_date'].' '.$d['trip']['return_time']):TP_I18n::t('Keine'),TP_I18n::t('Fahrzeug')=>$d['fare']['vehicle']??'',TP_I18n::t('Personen')=>$d['trip']['passengers']??'',TP_I18n::t('Gepäck')=>$d['trip']['bags']??'',TP_I18n::t('Hinweise')=>$d['notes']??'',TP_I18n::t('Benachrichtigung')=>$row->mail_status==='accepted'?TP_I18n::t('Vom Maildienst angenommen; Zustellung nicht bestätigt'):($row->mail_status==='failed'?TP_I18n::t('Versand fehlgeschlagen — Anfrage hier gespeichert'):TP_I18n::t('Ausstehend'))] as $label=>$value) echo '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html($value).'</dd></div>';
                echo '</dl><ul>'; foreach($d['fare']['lines']??[] as $line) echo '<li>'.esc_html($line['label']).' — '.esc_html(number_format_i18n($line['amount']/100,2).' '.$row->currency).'</li>'; echo TP_I18n::html('</ul><strong>Gesamt: ').esc_html(number_format_i18n($row->total/100,2).' '.$row->currency).'</strong><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="tpa-inline"><input type="hidden" name="action" value="tp_status"><input type="hidden" name="id" value="'.(int)$row->id.'">'; wp_nonce_field('tp_status'); echo '<select name="status">'; foreach(['new'=>TP_I18n::t('Neu'),'contacted'=>TP_I18n::t('In Bearbeitung'),'closed'=>TP_I18n::t('Abgeschlossen')] as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($row->status,$key,false).'>'.esc_html($label).'</option>'; echo TP_I18n::html('</select><button class="tpa-button">Status speichern</button></form><details><summary>Anfrage endgültig löschen</summary><form method="post" action="').esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tp_delete"><input type="hidden" name="id" value="'.(int)$row->id.'">'; wp_nonce_field('tp_delete'); echo TP_I18n::html('<label><input type="checkbox" name="confirm" value="1" required> Unwiderrufliches Löschen bestätigen</label> <button class="button">Jetzt löschen</button></form></details></section>');
            }
        }
        $page=max(1,absint($_GET['paged']??1)); $offset=($page-1)*25;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,reference,created_at,name,total,currency,status,mail_status FROM $table ORDER BY id DESC LIMIT 25 OFFSET %d",$offset));
        echo TP_I18n::html('<section class="tpa-card"><div class="tpa-toolbar"><h2>Fahrtanfragen</h2><a class="tpa-button secondary" href="').esc_url(wp_nonce_url(admin_url('admin-post.php?action=tp_export'),'tp_export')).TP_I18n::html('">CSV exportieren ↓</a></div><div class="tpa-table-wrap"><table class="widefat striped"><thead><tr><th>Referenz</th><th>Eingang</th><th>Name</th><th>Preis</th><th>Status</th><th>Mail</th></tr></thead><tbody>');
        foreach($rows as $r) echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=transferpilot&view='.$r->id)).'">'.esc_html($r->reference).'</a></td><td>'.esc_html(get_date_from_gmt($r->created_at,'d.m.Y H:i')).'</td><td>'.esc_html($r->name).'</td><td>'.esc_html(number_format_i18n($r->total/100,2).' '.$r->currency).'</td><td>'.esc_html(['new'=>TP_I18n::t('Neu'),'contacted'=>TP_I18n::t('In Bearbeitung'),'closed'=>TP_I18n::t('Abgeschlossen')][$r->status]??$r->status).'</td><td>'.esc_html($r->mail_status==='accepted'?TP_I18n::t('Angenommen'):($r->mail_status==='failed'?TP_I18n::t('Fehlgeschlagen'):TP_I18n::t('Ausstehend'))).'</td></tr>';
        if(!$rows) echo TP_I18n::html('<tr><td colspan="6">Noch keine Anfragen. Eine Testanfrage hilft dir, Preise und E-Mail-Zustellung zu prüfen.</td></tr>');
        echo '</tbody></table></div><div class="tpa-toolbar">';
        if($page>1) echo '<a href="'.esc_url(admin_url('admin.php?page=transferpilot&paged='.($page-1))).TP_I18n::html('">← Vorherige</a>');
        if($offset+25<$all) echo '<a href="'.esc_url(admin_url('admin.php?page=transferpilot&paged='.($page+1))).TP_I18n::html('">Weitere →</a>');
        echo TP_I18n::html('</div><p class="tpa-note">Anfragen werden nach ').(int)$s['retention'].TP_I18n::html(' Tagen automatisch gelöscht, sobald WordPress-Cron ausgeführt wird. Mailstatus „Angenommen“ bestätigt keine Zustellung beim Empfänger.</p></section></div>');
    }
    public static function status() {
        self::guard('tp_status'); global $wpdb; $p=self::posted();
        if(!in_array($p['status']??'',['new','contacted','closed'],true)) wp_die(TP_I18n::t('Ungültiger Status.'),422);
        $wpdb->update(TP_Plugin::table(),['status'=>$p['status']],['id'=>absint($p['id']??0)]);
        self::go('transferpilot',TP_I18n::t('Status aktualisiert.'));
    }
    public static function delete() {
        self::guard('tp_delete'); if(empty($_POST['confirm'])) wp_die(TP_I18n::t('Löschung nicht bestätigt.'),422); global $wpdb;
        $wpdb->delete(TP_Plugin::table(),['id'=>absint($_POST['id']??0)]); self::go('transferpilot',TP_I18n::t('Anfrage gelöscht.'));
    }
    public static function csv_cell($v) {
        $v=(string)$v; return preg_match('/^[\s]*[=+@\-\t\r]/u',$v)?"'".$v:$v;
    }
    public static function export() {
        self::guard('tp_export'); global $wpdb; nocache_headers();
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="transferpilot-anfragen.csv"');
        $stream=fopen('php://output','w'); fwrite($stream,"\xEF\xBB\xBF");
        fputcsv($stream,[TP_I18n::t('Referenz'),TP_I18n::t('Erstellt UTC'),TP_I18n::t('Name'),TP_I18n::t('E-Mail'),TP_I18n::t('Telefon'),TP_I18n::t('Preis'),TP_I18n::t('Währung'),'Status',TP_I18n::t('Details')],';', '"', '');
        $last=0;
        do {
            $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.TP_Plugin::table().' WHERE id>%d ORDER BY id LIMIT 200',$last));
            foreach($rows as $r) { $last=$r->id; fputcsv($stream,array_map([self::class,'csv_cell'],[$r->reference,$r->created_at,$r->name,$r->email,$r->phone,number_format($r->total/100,2,'.',''),$r->currency,$r->status,$r->details]),';','"',''); }
        } while(count($rows)===200); fclose($stream); exit;
    }
}
