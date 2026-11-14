<?php
if(!defined('ABSPATH'))exit;
final class TP_Diagnostics {
    public static function boot() {
        add_action('admin_menu',function(){add_submenu_page('transferpilot',TP_I18n::t('Systemprüfung'),TP_I18n::t('Systemprüfung'),'manage_options','tp-diagnostics',[self::class,'page']);});
        add_action('admin_post_tp_diagnostic',[self::class,'run']);
    }
    public static function check_google($origin,$destination) {
        if(!get_option('tp_google_key'))return new WP_Error('tp_not_configured',TP_I18n::t('Kein Google-Schlüssel gespeichert. Es wurde keine externe Anfrage ausgeführt.'));
        $origin=sanitize_text_field($origin);$destination=sanitize_text_field($destination);
        if(strlen($origin)<5||strlen($destination)<5||strlen($origin)>200||strlen($destination)>200||$origin===$destination)return new WP_Error('tp_addresses',TP_I18n::t('Bitte zwei unterschiedliche, vollständige Testadressen eingeben.'));
        try {TP_Plugin::route_google($origin,$destination,TP_Plugin::settings());return true;}
        catch(Throwable $e){return new WP_Error('tp_provider',TP_I18n::t($e->getMessage()));}
    }
    public static function check_mail() {
        $s=TP_Plugin::settings();
        if(!is_email($s['recipient']))return new WP_Error('tp_recipient',TP_I18n::t('Bitte zuerst einen gültigen Empfänger speichern.'));
        $sent=wp_mail($s['recipient'],$s['brand'].' · '.TP_I18n::t('Testnachricht'),TP_I18n::t('Dies ist ein TransferPilot-Systemtest. Es wurde keine Fahrt angefragt.'));
        return $sent?true:new WP_Error('tp_mail',TP_I18n::t('Der Mailversand ist fehlgeschlagen. Bitte den Maildienst des Hostings prüfen.'));
    }
    public static function run() {
        if(!current_user_can('manage_options'))wp_die('Forbidden',403);
        check_admin_referer('tp_diagnostic');$type=sanitize_key($_POST['type']??'');
        if(!in_array($type,['google','mail'],true))wp_die('Invalid test',422);
        $key='tp_diag_rate_'.get_current_user_id().'_'.$type;
        if(get_transient($key))$result=new WP_Error('tp_wait',TP_I18n::t('Bitte eine Minute bis zum nächsten Test warten.'));
        else {
            set_transient($key,1,MINUTE_IN_SECONDS);
            $result=$type==='google'?self::check_google(wp_unslash($_POST['origin']??''),wp_unslash($_POST['destination']??'')):self::check_mail();
        }
        $message=is_wp_error($result)?$result->get_error_message():($type==='google'?TP_I18n::t('Google hat eine gültige Straßenroute geliefert. Verbindung erfolgreich.'):TP_I18n::t('Der Maildienst hat die Nachricht angenommen. Bitte den Eingang im Postfach bestätigen.'));
        set_transient('tp_diag_result_'.get_current_user_id(),['ok'=>!is_wp_error($result),'message'=>$message,'time'=>current_time('mysql')],300);
        wp_safe_redirect(admin_url('admin.php?page=tp-diagnostics'));exit;
    }
    public static function page() {
        if(!current_user_can('manage_options'))return;
        TP_Admin::head(TP_I18n::t('Systemprüfung'),TP_I18n::t('Prüfe Verbindungen vor dem Einsatz. Es werden keine Zugangsdaten angezeigt.'));
        $result=get_transient('tp_diag_result_'.get_current_user_id());
        if($result)echo '<div class="notice '.($result['ok']?'notice-success':'notice-error').'"><p>'.esc_html($result['message']).' · '.esc_html($result['time']).'</p></div>';
        $s=TP_Plugin::settings();
        echo '<section class="tpa-card"><h2>Google Routes</h2><p>'.esc_html(TP_I18n::t('Optional. Für Festpreisstrecken nicht erforderlich. Dieser Test sendet die beiden eingegebenen Adressen an Google und kann Nutzungskosten verursachen.')).'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tp_diagnostic"><input type="hidden" name="type" value="google">';wp_nonce_field('tp_diagnostic');
        echo '<div class="tpa-grid"><label class="tpa-field">'.esc_html(TP_I18n::t('Abholadresse')).'<input name="origin" required maxlength="200"></label><label class="tpa-field">'.esc_html(TP_I18n::t('Zieladresse')).'<input name="destination" required maxlength="200"></label></div><button class="tpa-button">'.esc_html(TP_I18n::t('Google-Verbindung testen')).'</button></form></section>';
        echo '<section class="tpa-card"><h2>'.esc_html(TP_I18n::t('Mailzustellung')).'</h2><p>'.esc_html(TP_I18n::t('Dieser Test sendet eine Nachricht an den gespeicherten Benachrichtigungsempfänger. Nur der Eingang im Postfach bestätigt die tatsächliche Zustellung.')).'</p><p>'.esc_html($s['recipient']?:'—').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tp_diagnostic"><input type="hidden" name="type" value="mail">';wp_nonce_field('tp_diagnostic');echo '<button class="tpa-button">'.esc_html(TP_I18n::t('Testnachricht senden')).'</button></form></section></div>';
    }
}
