<?php
if(!defined('ABSPATH'))exit;
final class TP_I18n {
    public static function language() {
        $s=(array)get_option('tp_settings',[]);
        return ($s['language']??'de')==='en'?'en':'de';
    }
    private static function catalogue() {
        static $map;
        if($map===null)$map=(array)json_decode(file_get_contents(__DIR__.'/../languages/en.json'),true);
        return $map;
    }
    public static function t($literal) {
        if(self::language()!=='en')return $literal;
        return self::catalogue()[$literal]??$literal;
    }
    /** Only call on static source literals, never operator or customer content. */
    public static function html($literal) {
        return self::language()==='en'?strtr($literal,self::catalogue()):$literal;
    }
    public static function fare(array $fare) {
        if(self::language()==='en')foreach($fare['lines'] as &$line) {
            $label=$line['label']; $suffix=' · einmal pro Anfrage';
            if(str_ends_with($label,$suffix)) {
                $line['label']=substr($label,0,-strlen($suffix)).' · '.self::t('einmal pro Anfrage');
            } else {
                $line['label']=strtr($label,[
                    'Rückfahrt · gleicher Festpreis'=>'Return · same fixed fare',
                    'Hinfahrt · Festpreis'=>'Outbound · fixed fare',
                    'Nachtzuschlag · Hinfahrt'=>'Night surcharge · outbound',
                    'Nachtzuschlag · Rückfahrt'=>'Night surcharge · return',
                    'Hinfahrt · Streckenpreis'=>'Outbound · distance fare',
                    'Rückfahrt · Streckenpreis'=>'Return · distance fare',
                    'Hinfahrt · '=>'Outbound · ', 'Rückfahrt · '=>'Return · '
                ]);
            }
        }
        unset($line);
        return $fare;
    }
}
