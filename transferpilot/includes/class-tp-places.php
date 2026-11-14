<?php
if (!defined('ABSPATH')) exit;
/** Optional server-side Places proxy: no key or provider error bodies reach visitors. */
final class TP_Places {
    public static function enabled() {
        return (bool)(get_option('tp_places_enabled') && get_option('tp_places_key') && TP_Plugin::settings()['terms_url'] && TP_Plugin::settings()['privacy_url']);
    }
    public static function search($req) {
        $en=TP_I18n::language()==='en';
        $error=$en?'Address suggestions are unavailable. You can enter the full address manually.':'Adressvorschläge sind nicht verfügbar. Bitte die vollständige Adresse manuell eingeben.';
        if (!self::enabled()) return new WP_Error('tp_places_disabled',$error,['status'=>503]);
        $body=$req->get_json_params();
        $input=is_array($body)&&is_string($body['input']??null)?trim(sanitize_text_field($body['input'])):'';
        if (strlen($input)<3 || strlen($input)>200) return new WP_Error('tp_places_input',$error,['status'=>400]);
        // Nonces are not abuse protection: independently bound paid requests per IP and site.
        $ip='tp_places_rl_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'local',wp_salt('nonce'));
        $daily='tp_places_day_'.gmdate('Ymd');
        $count=(int)get_transient($ip); $day=(int)get_transient($daily);
        if ($count>=60 || $day>=1000) return new WP_Error('tp_places_limit',$error,['status'=>429]);
        set_transient($ip,$count+1,15*MINUTE_IN_SECONDS);
        set_transient($daily,$day+1,DAY_IN_SECONDS);
        // Deliberately per-request billing; no incomplete/abandoned session-token promises.
        $response=wp_remote_post('https://places.googleapis.com/v1/places:autocomplete',[
            'timeout'=>8,'redirection'=>0,
            'headers'=>['Content-Type'=>'application/json','X-Goog-Api-Key'=>get_option('tp_places_key'),
                'X-Goog-FieldMask'=>'suggestions.placePrediction.text.text'],
            'body'=>wp_json_encode(['input'=>$input,'languageCode'=>$en?'en':'de','includeQueryPredictions'=>false])]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200) return new WP_Error('tp_places_provider',$error,['status'=>503]);
        $data=json_decode(wp_remote_retrieve_body($response),true); $items=[];
        foreach(array_slice($data['suggestions']??[],0,5) as $row) {
            $label=$row['placePrediction']['text']['text']??'';
            if(is_string($label)&&$label!=='') $items[]=sanitize_text_field($label);
        }
        $result=rest_ensure_response(['suggestions'=>$items]);
        $result->header('Cache-Control','no-store, private');
        return $result;
    }
}
