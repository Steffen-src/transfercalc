<?php
if (!defined('ABSPATH')) exit;
final class TP_Plugin {
    public static function defaults() {
        return ['brand'=>'TransferPilot','language'=>'de','accent'=>'#b78349','currency'=>'EUR','recipient'=>'','privacy_url'=>'','terms_url'=>'',
            'map'=>false,'traffic'=>false,'mode'=>'fixed','min_hours'=>3,'max_days'=>365,'max_km'=>500,'night_start'=>22,'night_end'=>6,'night_fee'=>0,
            'retention'=>90,'price_note'=>TP_I18n::t('Unverbindliche Preisberechnung. Die Fahrt wird erst nach persönlicher Bestätigung verbindlich.'),
            'locations'=>[],'vehicles'=>[],'routes'=>[],'extras'=>[]];
    }
    public static function settings() { return array_replace(self::defaults(), (array) get_option('tp_settings', [])); }
    public static function init() {
        add_action('admin_menu', ['TP_Admin','menu']);
        add_action('admin_post_tp_save', ['TP_Admin','save']);
        add_action('admin_post_tp_status', ['TP_Admin','status']);
        add_action('admin_post_tp_delete', ['TP_Admin','delete']);
        add_action('admin_post_tp_export', ['TP_Admin','export']);
        add_action('admin_enqueue_scripts', ['TP_Admin','assets']);
        add_action('rest_api_init', function(){
            register_rest_route('transferpilot/v1','/places',['methods'=>'POST','callback'=>['TP_Places','search'],'permission_callback'=>[self::class,'public_permission']]);
            foreach (['quote','enquiry'] as $action) register_rest_route('transferpilot/v1', '/'.$action,
                ['methods'=>'POST','callback'=>[self::class,$action], 'permission_callback'=>[self::class,'public_permission']]);
        });
        add_shortcode('transferpilot', [self::class,'shortcode']);
        add_action('tp_retention', [self::class,'retention']);
        add_filter('wp_privacy_personal_data_exporters', function($items){ $items['transferpilot']=['exporter_friendly_name'=>'TransferPilot','callback'=>[self::class,'privacy_export']]; return $items; });
        add_filter('wp_privacy_personal_data_erasers', function($items){ $items['transferpilot']=['eraser_friendly_name'=>'TransferPilot','callback'=>[self::class,'privacy_erase']]; return $items; });
        add_action('admin_init', function(){ if (function_exists('wp_add_privacy_policy_content')) wp_add_privacy_policy_content('TransferPilot', TP_I18n::html('<p>TransferPilot speichert Kontaktdaten und Fahrtdetails zur Bearbeitung von Fahrtanfragen. Die Aufbewahrungsfrist ist konfigurierbar (Standard 90 Tage). Bei aktivierter Google-Routenberechnung werden Start- und Zieladresse an Google übertragen. Der Betreiber muss seine Rechtsgrundlage, Datenschutzhinweise, Mailanbieter und gegebenenfalls Google-Nutzung selbst prüfen und ergänzen. Export und Löschung sind über die WordPress-Datenschutzwerkzeuge möglich.</p>')); });
    }
    public static function table() { global $wpdb; return $wpdb->prefix.'tp_enquiries'; }
    public static function activate() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table = self::table(); $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(40) NOT NULL,
            created_at datetime NOT NULL,
            email varchar(190) NOT NULL,
            name varchar(150) NOT NULL,
            phone varchar(60) NOT NULL,
            details longtext NOT NULL,
            total bigint(20) NOT NULL,
            currency varchar(3) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            mail_status varchar(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            KEY email (email),
            KEY created_at (created_at)
        ) $collate;");
        if (!get_option('tp_settings')) add_option('tp_settings',self::defaults(),'','no');
        if (!wp_next_scheduled('tp_retention')) wp_schedule_event(time()+3600,'daily','tp_retention');
    }
    public static function public_permission($req) {
        if (!wp_verify_nonce($req->get_header('X-TP-Nonce'), 'tp_public')) return new WP_Error('tp_nonce',TP_I18n::t('Die Sitzung ist abgelaufen. Bitte die Seite neu laden.'),['status'=>403]);
        $origin=$req->get_header('origin');
        if ($origin && wp_parse_url($origin,PHP_URL_HOST)!==wp_parse_url(home_url(),PHP_URL_HOST)) return new WP_Error('tp_origin',TP_I18n::t('Nicht erlaubter Ursprung.'),['status'=>403]);
        return true;
    }
    private static function rate_limit($type, $limit) {
        // Trust only the server peer address, never arbitrary proxy headers.
        $key='tp_rl_'.hash_hmac('sha256',($type.'|'.($_SERVER['REMOTE_ADDR']??'local')),wp_salt('nonce'));
        $count=(int)get_transient($key);
        if ($count >= $limit) throw new RuntimeException(TP_I18n::t('Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.'));
        set_transient($key,$count+1,15*MINUTE_IN_SECONDS);
    }
    private static function text($value,$max=200) {
        if (!is_scalar($value)) throw new InvalidArgumentException(TP_I18n::t('Ungültiges Textfeld.'));
        $value=sanitize_text_field((string)$value);
        if (strlen($value)>$max) throw new InvalidArgumentException(TP_I18n::t('Ein Textfeld ist zu lang.'));
        return $value;
    }
    private static function dt($date,$time) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,wp_timezone());
        if (!$d || $d->format('Y-m-d H:i')!==$date.' '.$time) throw new InvalidArgumentException(TP_I18n::t('Bitte ein gültiges Datum und eine gültige Uhrzeit wählen.'));
        return $d;
    }
    public static function normalize($input,array $s) {
        if (!is_array($input)) throw new InvalidArgumentException(TP_I18n::t('Ungültige Anfrage.'));
        $trip=[];
        foreach (['mode','route','vehicle','date','time','return_date','return_time','origin','destination'] as $k) $trip[$k]=self::text($input[$k]??'');
        if (!in_array($trip['mode'],['fixed','distance'],true) || ($trip['mode']==='distance' && $s['mode']!=='both')) throw new InvalidArgumentException(TP_I18n::t('Diese Berechnungsart ist nicht verfügbar.'));
        foreach (['passengers','bags'] as $k) {
            $value=$input[$k]??($k==='bags'?0:1);
            if (filter_var($value,FILTER_VALIDATE_INT)===false || $value<0 || $value>100) throw new InvalidArgumentException(TP_I18n::t('Ungültige Personen- oder Gepäckanzahl.'));
            $trip[$k]=(int)$value;
        }
        $trip['return']=!empty($input['return']);
        if (!isset($input['extras'])) $input['extras']=[];
        if (!is_array($input['extras']) || count($input['extras'])>20) throw new InvalidArgumentException(TP_I18n::t('Ungültige Zusatzleistungen.'));
        $trip['extras']=array_values(array_unique(array_map(fn($x)=>self::text($x,60),$input['extras'])));
        $now=new DateTimeImmutable('now',wp_timezone());
        $out=self::dt($trip['date'],$trip['time']);
        if ($out->getTimestamp() < $now->getTimestamp()+$s['min_hours']*3600 || $out > $now->modify('+'.$s['max_days'].' days')) throw new InvalidArgumentException(TP_I18n::t('Die Abholung liegt außerhalb des erlaubten Vorbuchungszeitraums.'));
        if ($trip['return']) {
            $back=self::dt($trip['return_date'],$trip['return_time']);
            if ($back<=$out || $back>$now->modify('+'.$s['max_days'].' days')) throw new InvalidArgumentException(TP_I18n::t('Die Rückfahrt muss nach der Hinfahrt und im Vorbuchungszeitraum liegen.'));
        }
        if ($trip['mode']==='distance' && (strlen($trip['origin'])<5 || strlen($trip['destination'])<5 || $trip['origin']===$trip['destination'])) throw new InvalidArgumentException(TP_I18n::t('Bitte zwei unterschiedliche, vollständige Adressen eingeben.'));
        return $trip;
    }
    public static function route_google($origin,$destination,array $s,$departure=null) {
        $key=get_option('tp_google_key','');
        if (!$key) throw new RuntimeException(TP_I18n::t('Die freie Streckenberechnung ist noch nicht eingerichtet. Bitte eine Festpreisstrecke wählen.'));
        $traffic=!empty($s['traffic']);
        $body=['origin'=>['address'=>$origin],'destination'=>['address'=>$destination],'travelMode'=>'DRIVE','routingPreference'=>$traffic?'TRAFFIC_AWARE':'TRAFFIC_UNAWARE','computeAlternativeRoutes'=>false,'units'=>'METRIC'];
        if($traffic && $departure instanceof DateTimeImmutable) $body['departureTime']=$departure->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $response=wp_remote_post('https://routes.googleapis.com/directions/v2:computeRoutes',[
            'timeout'=>15,'redirection'=>0,
            'headers'=>['Content-Type'=>'application/json','X-Goog-Api-Key'=>$key,'X-Goog-FieldMask'=>'routes.distanceMeters,routes.duration'.(!empty($s['map'])?',routes.polyline.encodedPolyline':'')],
            'body'=>wp_json_encode($body)]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) throw new RuntimeException(TP_I18n::t('Der Routendienst ist aktuell nicht verfügbar. Bitte später erneut versuchen oder eine Festpreisstrecke wählen.'));
        $data=json_decode(wp_remote_retrieve_body($response),true);
        $meters=$data['routes'][0]['distanceMeters']??0;
        $duration=$data['routes'][0]['duration']??'';
        if (!is_int($meters) || $meters<=0 || $meters>$s['max_km']*1000 || !preg_match('/^([0-9]+(?:\.[0-9]+)?)s$/D',(string)$duration,$match)) throw new RuntimeException(TP_I18n::t('Keine gültige Fahrstrecke innerhalb der erlaubten Entfernung gefunden.'));
        $result=['meters'=>$meters,'seconds'=>(int)ceil((float)$match[1])]+($traffic?['traffic'=>true]:[]);
        $line=$data['routes'][0]['polyline']['encodedPolyline']??'';
        if(!empty($s['map']) && is_string($line) && strlen($line)<=100000) $result['polyline']=$line;
        return $result;
    }
    private static function config_hash(array $s) { return hash('sha256',wp_json_encode($s)); }
    private static function encode(array $data) {
        $body=rtrim(strtr(base64_encode(wp_json_encode($data)),'+/','-_'),'=');
        return $body.'.'.hash_hmac('sha256',$body,wp_salt('auth'));
    }
    private static function decode($token) {
        if (!is_string($token) || strlen($token)>18000) throw new InvalidArgumentException(TP_I18n::t('Ungültige Preisberechnung.'));
        $parts=explode('.',$token);
        if (count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],wp_salt('auth')),$parts[1])) throw new InvalidArgumentException(TP_I18n::t('Die Preisberechnung wurde verändert. Bitte neu berechnen.'));
        $data=json_decode(base64_decode(strtr($parts[0],'-_','+/'),true),true);
        if (!is_array($data) || ($data['expires']??0)<time()) throw new InvalidArgumentException(TP_I18n::t('Der Preis ist abgelaufen. Bitte neu berechnen.'));
        return $data;
    }
    private static function failure(Throwable $e) {
        $status=$e instanceof InvalidArgumentException ? 422 : 503;
        if (str_contains($e->getMessage(),TP_I18n::t('Zu viele'))) $status=429;
        return new WP_Error('tp_request',TP_I18n::t($e->getMessage()),['status'=>$status]);
    }
    public static function quote($req) {
        try {
            self::rate_limit('quote',30);
            $s=self::settings(); $trip=self::normalize($req->get_json_params(),$s); $routes=[];
            // Reject invalid vehicle, capacity and extras before any paid API call.
            TP_Fares::calculate($s,$trip,$trip['mode']==='distance'?['outbound'=>['meters'=>1],'inbound'=>['meters'=>1]]:[]);
            if ($trip['mode']==='distance') {
                if (!$s['terms_url']) throw new RuntimeException(TP_I18n::t('Die Nutzungsbedingungen für die freie Straßenberechnung sind noch nicht eingerichtet.'));
                $routes['outbound']=self::route_google($trip['origin'],$trip['destination'],$s,self::dt($trip['date'],$trip['time']));
                if ($trip['return']) $routes['inbound']=self::route_google($trip['destination'],$trip['origin'],$s,self::dt($trip['return_date'],$trip['return_time']));
            }
            $fare=TP_Fares::calculate($s,$trip,$routes);
            $signed_routes=$routes; foreach($signed_routes as &$signed_route) unset($signed_route['polyline']); unset($signed_route);
            $token=self::encode(['trip'=>$trip,'routes'=>$signed_routes,'config'=>self::config_hash($s),'expires'=>time()+900,'id'=>wp_generate_uuid4()]);
            $response=new WP_REST_Response(['fare'=>TP_I18n::fare($fare),'routes'=>$routes,'token'=>$token,'expiresIn'=>900]);
            $response->header('Cache-Control','no-store'); return $response;
        } catch (Throwable $e) { return self::failure($e); }
    }
    public static function enquiry($req) {
        global $wpdb;
        $lock=null;
        try {
            self::rate_limit('enquiry',8);
            $p=$req->get_json_params();
            if (!is_array($p) || !empty($p['website']) || ($p['consent']??false)!==true) throw new InvalidArgumentException(TP_I18n::t('Bitte die Datenschutzhinweise bestätigen.'));
            $data=self::decode($p['token']??''); $s=self::settings();
            if (!hash_equals($data['config'],self::config_hash($s))) throw new InvalidArgumentException(TP_I18n::t('Die Preise wurden geändert. Bitte neu berechnen.'));
            $trip=self::normalize($data['trip'],$s);
            $fare=TP_Fares::calculate($s,$trip,$data['routes']);
            if (!$s['recipient'] || !$s['privacy_url']) throw new RuntimeException(TP_I18n::t('Die Anfragefunktion ist noch nicht vollständig eingerichtet.'));
            $name=self::text($p['name']??'',150); $phone=self::text($p['phone']??'',60);
            $email=self::text($p['email']??'',190); $notes=self::text($p['notes']??'',1000);
            if (strlen($name)<2 || !is_email($email) || !preg_match('/^[+()0-9 .\/-]{5,60}$/D',$phone)) throw new InvalidArgumentException(TP_I18n::t('Bitte Namen, gültige E-Mail-Adresse und Telefonnummer angeben.'));
            $reference='TP-'.strtoupper(substr(str_replace('-','',$data['id']),0,16));
            // The unique reference makes repeat submissions idempotent, including mail delivery.
            $existing=$wpdb->get_row($wpdb->prepare('SELECT id FROM '.self::table().' WHERE reference=%s',$reference));
            if ($existing) return ['reference'=>$reference,'message'=>TP_I18n::t('Diese Anfrage wurde bereits gespeichert.')];
            $stored_fare=$fare;
            if($trip['mode']==='distance') foreach($stored_fare['lines'] as &$line) {
                if(str_contains($line['label'],' km')) $line['label']=explode(' · ',$line['label'])[0].' · Streckenpreis';
            }
            unset($line);
            // Persist the commercial enquiry, not Google distance/duration results.
            $details=['trip'=>$trip,'fare'=>TP_I18n::fare($stored_fare),'notes'=>$notes,'consent_at'=>gmdate('c'),'privacy_url'=>$s['privacy_url']];
            if ($trip['mode']==='fixed') foreach ($s['routes'] as $r) if ($r['id']===$trip['route']) $details['route_name']=self::route_label($r,$s);
            $ok=$wpdb->insert(self::table(),['reference'=>$reference,'created_at'=>current_time('mysql',true),'email'=>$email,'name'=>$name,'phone'=>$phone,'details'=>wp_json_encode($details),'total'=>$fare['total'],'currency'=>$s['currency'],'status'=>'new','mail_status'=>'pending']);
            if (!$ok) {
                if ($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE reference=%s',$reference))) return ['reference'=>$reference,'message'=>TP_I18n::t('Diese Anfrage wurde bereits gespeichert.')];
                throw new RuntimeException(TP_I18n::t('Die Anfrage konnte nicht gespeichert werden. Bitte später erneut versuchen.'));
            }
            $id=$wpdb->insert_id;
            $body=TP_I18n::t('Neue Fahrtanfrage').' '.$reference."\n\n".TP_I18n::t('Bitte im WordPress-Admin öffnen:')."\n".admin_url('admin.php?page=transferpilot&view='.$id)."\n\n".TP_I18n::t('Dies ist eine Anfrage, keine bestätigte Buchung.');
            $sent=wp_mail($s['recipient'],$s['brand'].' · '.TP_I18n::t('Fahrtanfrage').' '.$reference,$body);
            $wpdb->update(self::table(),['mail_status'=>$sent?'accepted':'failed'],['id'=>$id]);
            return ['reference'=>$reference,'message'=>TP_I18n::t('Deine Fahrtanfrage wurde gespeichert. Der Anbieter meldet sich zur Bestätigung.')];
        } catch (Throwable $e) { return self::failure($e); }
    }
    public static function route_label(array $r,array $s) {
        $names=[]; foreach($s['locations'] as $l) $names[$l['id']]=$l['name'];
        return ($names[$r['from']]??'?').' → '.($names[$r['to']]??'?');
    }
    public static function browser_map_key() {
        $s=self::settings(); $key=(string)get_option('tp_maps_browser_key','');
        if(empty($s['map']) || !$s['privacy_url'] || !$s['terms_url'] || !$key) return '';
        foreach(['tp_google_key','tp_places_key'] as $option) if(hash_equals((string)get_option($option,''),$key)) return '';
        return $key;
    }
    public static function shortcode() {
        $s=self::settings();
        wp_enqueue_style('transferpilot',plugins_url('../assets/app.css',__FILE__),[],TP_VERSION);
        wp_enqueue_script('tp-i18n',plugins_url('../assets/i18n.js',__FILE__),[],TP_VERSION,true);
        wp_enqueue_script('tp-map',plugins_url('../assets/map.js',__FILE__),[],TP_VERSION,true);
        wp_enqueue_script('transferpilot',plugins_url('../assets/app.js',__FILE__),['tp-i18n','tp-map'],TP_VERSION,true);
        wp_enqueue_script('tp-places',plugins_url('../assets/places.js',__FILE__),['transferpilot'],TP_VERSION,true);
        $public=$s; unset($public['recipient']);
        $distance_ready=(bool)($s['mode']==='both'&&$s['terms_url']&&get_option('tp_google_key'));
        $config=['mapKey'=>self::browser_map_key(),'places'=>TP_Places::enabled(),'settings'=>$public,'language'=>TP_I18n::language(),'endpoint'=>rest_url('transferpilot/v1/'),'nonce'=>wp_create_nonce('tp_public'),'restNonce'=>wp_create_nonce('wp_rest'),'timezone'=>wp_timezone_string(),'today'=>wp_date('Y-m-d'),'ready'=>(bool)($s['recipient']&&$s['privacy_url']&&count($s['vehicles'])&&($s['routes']||$distance_ready))];
        ob_start(); ?>
<?php echo TP_I18n::html('        <section class="tp-app" style="--tp-accent:'); ?><?php echo esc_attr($s['accent']); ?><?php echo TP_I18n::html('" aria-label="Transferpreis und Fahrtanfrage">
          <script type="application/json" class="tp-config">'); ?><?php echo wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?><?php echo TP_I18n::html('</script>
          <header class="tp-header"><span class="tp-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 18 18 6M7 6h11v11" stroke="currentColor" stroke-width="2"/></svg></span><span>'); ?><?php echo esc_html($s['brand']); ?><?php echo TP_I18n::html('<small>TRANSFER · EINFACH PLANEN</small></span><span class="tp-pill">Deine Fahrt. Klar geplant.</span></header>
          <div class="tp-intro"><span class="tp-eyebrow">VOM ERSTEN KLICK BIS ZUM ZIEL</span><h2>Entspannt ankommen.<br><em>Schon vor dem Abflug.</em></h2><p>Plane deinen Transfer, vergleiche Fahrzeugklassen und frage deine Fahrt direkt an.</p></div>
          '); ?><?php if (!$config['ready']): ?><?php echo TP_I18n::html('<p class="tp-setup" role="status">Der Anbieter richtet den Transferrechner gerade ein. Fahrtanfragen sind noch nicht verfügbar.</p>'); ?><?php endif; ?>
<?php echo TP_I18n::html('          <ol class="tp-steps" aria-label="Fortschritt"><li aria-current="step"><b>01</b> Deine Fahrt</li><li><b>02</b> Fahrzeug & Extras</li><li><b>03</b> Prüfen & anfragen</li></ol>
          <div class="tp-layout"><div class="tp-main">
            <form class="tp-form" novalidate>
              <div class="tp-panel" data-panel="0"><div class="tp-panel-title"><span class="tp-eyebrow">01 / FAHRTDETAILS</span><h3>Wohin geht die Reise?</h3></div>
                <label class="tp-field">Berechnungsart<select name="mode"><option value="fixed" '); ?><?php disabled(empty($s['routes'])); ?><?php echo TP_I18n::html('>Festpreisstrecke</option>'); ?><?php if($s['mode']==='both'): ?><?php echo TP_I18n::html('<option value="distance" '); ?><?php disabled(!$distance_ready); selected(empty($s['routes'])&&$distance_ready); ?><?php echo TP_I18n::html('>Eigene Adresse · Straßenroute'); ?><?php echo $distance_ready?'':' '.esc_html(TP_I18n::t('(noch nicht eingerichtet)')); ?><?php echo TP_I18n::html('</option>'); ?><?php endif; ?><?php echo TP_I18n::html('</select></label>
                <div class="tp-fixed"><label class="tp-field">Transferstrecke<select name="route" required><option value="">Strecke auswählen</option>'); ?><?php foreach($s['routes'] as $r): ?><?php echo TP_I18n::html('<option value="'); ?><?php echo esc_attr($r['id']); ?><?php echo TP_I18n::html('">'); ?><?php echo esc_html(self::route_label($r,$s)); ?><?php echo TP_I18n::html('</option>'); ?><?php endforeach; ?><?php echo TP_I18n::html('</select></label><p class="tp-hint">Start und Ziel sind vom Anbieter festgelegt. Die Rückfahrt verläuft in umgekehrter Richtung.</p></div>
                <div class="tp-distance" hidden><label class="tp-field">Abholadresse<input name="origin" maxlength="200" autocomplete="off" placeholder="Straße, Hausnummer, Ort, Land"></label><label class="tp-field">Zieladresse<input name="destination" maxlength="200" autocomplete="off" placeholder="Flughafen / Terminal oder vollständige Adresse"></label><p class="tp-hint">Zur Straßenberechnung werden die beiden Adressen an Google übertragen. '); ?><?php echo esc_html(!empty($s['traffic'])?(TP_I18n::language()==='en'?'Traffic forecast for the selected pickup time; not a guaranteed arrival time. ':'Verkehrsprognose zur gewählten Abholzeit; keine garantierte Ankunftszeit. '):(TP_I18n::language()==='en'?'Live traffic is not included. ':'Keine Live-Verkehrsprognose. ')); ?><?php echo TP_I18n::html(''); ?><?php if ($s['terms_url']): ?><?php echo TP_I18n::html('<a href="'); ?><?php echo esc_url($s['terms_url']); ?><?php echo TP_I18n::html('" target="_blank" rel="noopener">Nutzungsbedingungen</a>.'); ?><?php endif; ?><?php echo TP_I18n::html(' <a href="https://maps.google.com/help/terms_maps/" target="_blank" rel="noopener">Google Maps Bedingungen</a> · <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Datenschutz</a>.</p></div>
                <div class="tp-grid"><label class="tp-field">Abholdatum<input type="date" name="date" required min="'); ?><?php echo esc_attr(wp_date('Y-m-d')); ?><?php echo TP_I18n::html('"></label><label class="tp-field">Abholzeit<input type="time" name="time" required></label></div>
                <p class="tp-hint">Alle Zeiten: '); ?><?php echo esc_html(wp_timezone_string()); ?><?php echo TP_I18n::html(' · mindestens '); ?><?php echo (int)$s['min_hours']; ?><?php echo TP_I18n::html(' Stunden im Voraus.</p>
                <div class="tp-grid"><label class="tp-field">Personen<input name="passengers" type="number" min="1" max="100" value="1" required></label><label class="tp-field">Gepäckstücke<input name="bags" type="number" min="0" max="100" value="0" required></label></div>
                <label class="tp-check"><input type="checkbox" name="return"><span>Rückfahrt hinzufügen<small>Gleiche Strecke in umgekehrter Richtung</small></span></label>
                <div class="tp-grid tp-return" hidden><label class="tp-field">Rückfahrtdatum<input type="date" name="return_date"></label><label class="tp-field">Rückfahrtzeit<input type="time" name="return_time"></label></div>
                <button type="button" class="tp-primary tp-next">Fahrzeug auswählen <span aria-hidden="true">↗</span></button>
              </div>
              <div class="tp-panel" data-panel="1" hidden><div class="tp-panel-title"><span class="tp-eyebrow">02 / DEIN KOMFORT</span><h3>Der passende Platz für dich.</h3></div><fieldset class="tp-vehicles"><legend class="tp-sr">Fahrzeugklasse auswählen</legend></fieldset><fieldset class="tp-extras"><legend>Ein bisschen mehr Komfort.</legend></fieldset><div class="tp-actions"><button type="button" class="tp-secondary tp-back">← Zurück</button><button type="button" class="tp-primary tp-calculate">Preis berechnen ↗</button></div></div>
              <div class="tp-panel" data-panel="2" hidden><div class="tp-panel-title"><span class="tp-eyebrow">03 / FAST AM ZIEL</span><h3>Wie erreichen wir dich?</h3></div><p class="tp-hint">Dein Preis bleibt 15 Minuten für diese Anfrage gültig. Eine Fahrt ist erst nach Bestätigung durch den Anbieter verbindlich.</p><label class="tp-field">Vollständiger Name<input name="name" autocomplete="name" maxlength="150" required></label><div class="tp-grid"><label class="tp-field">E-Mail<input name="email" type="email" autocomplete="email" maxlength="190" required></label><label class="tp-field">Telefon<input name="phone" type="tel" autocomplete="tel" maxlength="60" required></label></div><label class="tp-field">Flugnummer oder Hinweise <span>(optional)</span><textarea name="notes" rows="3" maxlength="1000"></textarea></label><div class="tp-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label class="tp-check"><input name="consent" type="checkbox" required><span>Ich habe die <a href="'); ?><?php echo esc_url($s['privacy_url']); ?><?php echo TP_I18n::html('" target="_blank" rel="noopener">Datenschutzhinweise</a> gelesen und möchte diese Fahrt anfragen.</span></label><div class="tp-actions"><button type="button" class="tp-secondary tp-back">← Ändern</button><button type="submit" class="tp-primary">Fahrt unverbindlich anfragen ↗</button></div></div>
            </form><div class="tp-message" role="status" aria-live="polite"></div><div class="tp-success" hidden tabindex="-1"></div>
          </div><aside class="tp-summary" aria-label="Deine Fahrtübersicht"><div class="tp-summary-art" aria-hidden="true"><svg viewBox="0 0 400 160" fill="none"><path d="M-20 130C80 130 70 25 170 70S250 170 420 40" stroke="currentColor" stroke-width="1.5" stroke-dasharray="5 7"/><circle cx="80" cy="90" r="8" fill="currentColor"/><circle cx="320" cy="90" r="8" fill="currentColor"/><path d="m190 60 35-15-15 35-6-14z" fill="currentColor"/></svg><span>VON A NACH ENTSPANNT.</span></div><span class="tp-eyebrow">DEIN TRANSFER IM ÜBERBLICK</span><h3>Alles auf einen Blick.</h3><div class="tp-trip-summary">Wähle eine Strecke und deinen Reisetermin.</div><div class="tp-price-lines"></div><div class="tp-total"><span>Gesamtpreis</span><strong>—</strong></div><p class="tp-price-note">'); ?><?php echo esc_html($s['price_note']==='Unverbindliche Preisberechnung. Die Fahrt wird erst nach persönlicher Bestätigung verbindlich.'?TP_I18n::t($s['price_note']):$s['price_note']); ?><?php echo TP_I18n::html('</p><div class="tp-summary-foot">✓ Individuelle Fahrzeugwahl<br>✓ Klare Preisaufstellung<br>✓ Direkte Anfrage beim Anbieter</div></aside></div><footer class="tp-footer">Sicher geplant. Persönlich bestätigt.</footer>
        </section>'); ?><?php return str_replace('<section class="tp-app"', '<section lang="'.esc_attr(TP_I18n::language()).'" class="tp-app"', ob_get_clean());
    }
    public static function retention() {
        global $wpdb; $days=self::settings()['retention'];
        $wpdb->query($wpdb->prepare('DELETE FROM '.self::table().' WHERE created_at < %s',gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS)));
    }
    public static function privacy_export($email,$page=1) {
        global $wpdb; $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE email=%s ORDER BY id LIMIT 50 OFFSET %d',$email,($page-1)*50)); $out=[];
        foreach($rows as $r) $out[]=['group_id'=>'transferpilot','group_label'=>TP_I18n::t('TransferPilot Fahrtanfragen'),'item_id'=>'transferpilot-'.$r->id,'data'=>[['name'=>TP_I18n::t('Referenz'),'value'=>$r->reference],['name'=>TP_I18n::t('Name'),'value'=>$r->name],['name'=>TP_I18n::t('Telefon'),'value'=>$r->phone],['name'=>TP_I18n::t('E-Mail'),'value'=>$r->email],['name'=>TP_I18n::t('Details'),'value'=>$r->details],['name'=>TP_I18n::t('Erstellt (UTC)'),'value'=>$r->created_at]]];
        return ['data'=>$out,'done'=>count($rows)<50];
    }
    public static function privacy_erase($email,$page=1) {
        global $wpdb; $n=$wpdb->delete(self::table(),['email'=>$email]); return ['items_removed'=>$n>0,'items_retained'=>false,'messages'=>[],'done'=>true];
    }
}
