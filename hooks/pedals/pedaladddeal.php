<?php
// данные для авторизации
$domain            = 'b24.lvi-outlet.com';
$auth              = 'tcn3azy7ls1p065y';
$user              = '2240';

// берем имейл клиента
$email =  'lya-job@yandex.ru';
// берем телефон клиента
$phone = '79099337755';
// берем имя и фамилию клиента
$name =  'Федор';
$lastname =  'Федоров';
// метод доставки
$delmethod = 'DHL';
// street
$street = 'Friedrichstrasse';
// state
$state = 'DE';
// state
$country = 'DE';
// state
$city = 'Berlin';
// zip
$zip = '777';
// сумма продажи
$fund = 120;
// стадия сделки
$stage = 'C10:NEW';

// проверяем есть ли уже контакт с таким емейлом
$appParams = http_build_query(array(
        'halt' => 0,
        'cmd' => array(
            'get_contact' => 'crm.contact.list?'
                .http_build_query(array(
                    'order' => array('ID' => "ASC"),
                    'filter' => array('EMAIL'=>$email),
                    'select' => array("*","UF_*", "PHONE", "EMAIL")
                ))
        )
));
// скрипт для передачи данных в CRM - можете потом упаковать его в одну функцию
$appRequestUrl = 'https://'.$domain.'/rest/'.$user.'/'.$auth.'/batch';
$curl=curl_init();
curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $appRequestUrl,
        CURLOPT_POSTFIELDS => $appParams
));
$out=curl_exec($curl);
$out = json_decode($out, 1);
$cid = $out['result']['result']['get_contact'][0]['ID'];

if($cid) {
    // если контакт найден, то смотрим есть ли у него номер телефона и если нет добавляем номер
    if(!array_key_exists('PHONE', $out['result']['result']['get_contact'][0])){
        $appParams = http_build_query(array(
            'halt' => 0,
            'cmd' => array(
                'get_lead' => 'crm.contact.update?'
                    . http_build_query(array(
                            'id' => $cid,
                            'fields' => array('PHONE' => Array(
                                "n0" => Array(
                                    "VALUE" =>$phone,
                                    "VALUE_TYPE" => "WORK",
                                ))
                            ))
                    )
            )));
        $appRequestUrl = 'https://' . $domain . '/rest/' . $user . '/' . $auth . '/batch';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $appRequestUrl,
            CURLOPT_POSTFIELDS => $appParams
        ));
        $out = curl_exec($curl);
    }
} else {
    // добавляем контакт
    $appParams = http_build_query(array(
        'halt' => 0,
        'cmd' => array(
            'make_contact' => 'crm.contact.add?'
                .http_build_query(array(
                        'fields' => array('NAME' => $name, 'LAST_NAME' => $secname, 'EMAIL' => Array(
                            "n0" => Array(
                                "VALUE" =>$email,
                                "VALUE_TYPE" => "WORK",
                            )
                        ),'PHONE' => Array(
                            "n0" => Array(
                                "VALUE" =>$phone,
                                "VALUE_TYPE" => "WORK",
                            )
                        ))
                    )
                )
        )));
    $appRequestUrl = 'https://'.$domain.'/rest/'.$user.'/'.$auth.'/batch';
    $curl=curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $appRequestUrl,
        CURLOPT_POSTFIELDS => $appParams
    ));
    $out=curl_exec($curl);
    $out = json_decode($out, 1);
    $cid = $out['result']['result']['make_contact'];
}
// имеем готовый контакт и добавляем сделку
if($cid) {
       $appParams = http_build_query(array(
            'halt' => 0,
            'cmd' => array(
                'make_deal' => 'crm.deal.add?'
                    .http_build_query(array(
                            'fields' => array('TYPE_ID' => 'SALE',
                                'TITLE' =>  'New order from:'.$name.' '.$lastname,
                                'CONTACT_ID' => $cid,
                                'TYPE_ID' => 'SALE',
                                'ASSIGNED_BY_ID' => 2240,
                                //'SOURCE_ID' => 'WEB',
                                'CATEGORY_ID' => 10,
                                'STAGE_ID' => 'C10:NEW',
                                'OPPORTUNITY' => $fund,
                                'UF_CRM_1594121498' => $name.' '.$lastname,
                                'UF_CRM_1594121513' => $street,
                                'UF_CRM_1594121528' => $country.' '.$zip.' '.$state,
                                'UF_CRM_1594121544' => $delmethod,
                                'UF_CRM_1587361552' => 1,
                                'UF_CRM_1587361577' => 1

                            )
                        )
                    )
            )));
        $appRequestUrl = 'https://'.$domain.'/rest/'.$user.'/'.$auth.'/batch';
        $curl=curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $appRequestUrl,
            CURLOPT_POSTFIELDS => $appParams
        ));
        $out=curl_exec($curl);
        $out = json_decode($out, 1);
        echo "<pre>";
        print_r($out);
        echo "</pre>";
        // тут по желанию пишешь лог с id сделки, указав корректный путь на сервере
        $title = 'newordadd';
        $log = "\n------------------------\n";
        $log .= date("Y.m.d G:i:s") . "\n";
        $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
        $log .= print_r($out['result']['result']['make_deal'], 1);
        $log .= "\n------------------------\n";
        //file_put_contents('/loggappnew.log', $log, FILE_APPEND);
}
