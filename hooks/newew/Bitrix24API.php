<?php namespace SmartPx\InterStar\Classes;

use SmartPx\InterStar\Models\Settings;
use Log;

class Bitrix24API
{
    public static $domain = 'escapewelt.bitrix24.ru';
    public static $auth   = 'lcsybmc0ru28u00p';
    public static $user   = '83';


    public function opt($appParams)
    {
        $appRequestUrl = 'https://'.self::$domain.'/rest/'.self::$user.'/'.self::$auth.'/batch';
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

        return json_decode($out, 1);

        // в октябре есть удобная обертка для CURL
        // https://octobercms.com/docs/api/october/rain/network/readme

        $res = \Http::post($appRequestUrl, function($http) use ($appParams) {
            $http->data($appParams);
        });

        return json_decode($res->body, 1);
    }
    // создание игры по брони
    public function bitrixCreateOrderBooking($data)
    {
        // емейл
        $email =  $data['data']['email'];
        // телефон
        $phone =  $data['data']['phone'];
        // имя
        $name =  $data['data']['first_name'];
        // фамилия
        $lastname =  $data['data']['last_name'];
        // исходя из этого условия выбираем одну из четырех игр
        if($data['defaults']['staff_id']==2) {
            $gameid = 25;
            $gametxt = 'DVG_';
        } elseif ($data['defaults']['staff_id']==3) {
            $gameid = 21;
            $gametxt = 'DVS_';
        } elseif ($data['defaults']['staff_id']==4) {
            $gameid = 23;
            $gametxt = 'EAP13z_';
        } else {
            $gameid = 19;
            $gametxt = 'DGM_';
        }
        // дата игры и проверка на зимнее время
        $date = date("d-m-Y H:i:s", strtotime($data['data']['slots'][0][2]));
        $cy = date("Y");

        $day = date("d", strtotime($data['data']['slots'][0][2]));
        $month = date("m", strtotime($data['data']['slots'][0][2]));
        $year = date("Y", strtotime($data['data']['slots'][0][2]));

        $winter = false;

        if($day >= 25 && $month >= 10 && $year==$cy) {
            $winter = true;
        } elseif ($day <= 29 && $month <= 3 && $year<$cy) {
            $winter = true;
        }

        $date_deal = $date;

        if($winter) {
            $date_new = date_create($date);
            date_modify($date_new, "-1 hour"); // на 1 час назад
            $date_deal = date_format($date_new, "d-m-Y H:i:s");
        }
        // стоимость игры и количество игроков
        if($data['data']['slots'][0][0]==5) {
            $price = 70;
            $players = 2;
        } elseif($data['data']['slots'][0][0]==6) {
            $price = 90;
            $players = 3;
        } elseif($data['data']['slots'][0][0]==1) {
            $price = 100;
            $players = 4;
        } elseif($data['data']['slots'][0][0]==7) {
            $price = 110;
            $players = 5;
        } else {
            $price = 120;
            $players = 6;
        }
        // техническое поле для обновления данных о бронировании
        $bookinginfo = "resource|".$gameid ."|".$date_deal."|5400|ESCAPE ROOM";

        // проверяем есть ли уже контакт с таким емейлом
        $params = http_build_query(array(
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

        $out = $this->opt($params);

        $cid = isset($out['result']['result']['get_contact'][0]['ID']) ? $out['result']['result']['get_contact'][0]['ID'] : null;

        if($cid) {
            // если контакт найден, то смотрим есть ли у него номер телефона и если нет добавляем номер
            $source = $out['result']['result']['get_contact'][0]['UF_CRM_1587120391'];
            if(!$source){
                $source=array();
            }
            $role = $out['result']['result']['get_contact'][0]['UF_CRM_1587646094'];
            if(!$role){
                $role=array();
            }
            if(!in_array( 665, $source)){
                array_push($source, 665);

                $params = http_build_query(array(
                    'halt' => 0,
                    'cmd' => array(
                        'get_lead' => 'crm.contact.update?'
                            . http_build_query(array(
                                    'id' => $cid,
                                    'fields' => array('UF_CRM_1587120391'=> $source))
                            )
                    )));
                $out = $this->opt($params);
            }
            if(!in_array( 677, $role)) {
                array_push($role, 677);
                $params = http_build_query(array(
                    'halt' => 0,
                    'cmd' => array(
                        'get_lead' => 'crm.contact.update?'
                            . http_build_query(array(
                                'id' => $cid,
                                'fields' => array('UF_CRM_1587646094' => $role)
                            ))
                    )
                ));
                $out = $this->opt($params);
            }
            if(!array_key_exists('PHONE', $out['result']['result']['get_contact'][0])) {
                $params = http_build_query(array(
                    'halt' => 0,
                    'cmd' => array(
                        'get_lead' => 'crm.contact.update?'
                            . http_build_query(array(
                                    'id' => $cid,
                                    'fields' => array('PHONE' => Array(
                                        "n0" => Array(
                                            "VALUE" => $phone,
                                            "VALUE_TYPE" => "WORK",
                                        ))
                                    ))
                            )
                    )));
                $out = $this->opt($params);
            }
        } else {
            // добавляем контакт
            $params = http_build_query(array(
                'halt' => 0,
                'cmd' => array(
                    'make_contact' => 'crm.contact.add?'
                        .http_build_query(array(
                                'fields' => array('NAME' => $name, 'LAST_NAME' => $lastname, 'UF_CRM_1587120391' => array(665), 'UF_CRM_1587646094' => array(677), 'EMAIL' => Array(
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
            $out = $this->opt($params);
            $cid = $out['result']['result']['make_contact'];
        }
        // имеем готовый контакт и добавляем сделку
        if($cid) {
            $params = http_build_query(array(
                'halt' => 0,
                'cmd' => array(
                    'make_deal' => 'crm.deal.add?'
                        .http_build_query(array(
                                'fields' => array('TYPE_ID' => 'SALE',
                                    'TITLE' => $gametxt.$players.' '.$date,
                                    'CONTACT_ID' => $cid,
                                    'ASSIGNED_BY_ID' => 13,
                                    'SOURCE_ID' => 'STORE',
                                    'CATEGORY_ID' => 0,
                                    'STAGE_ID' => 'NEW',
                                    'OPPORTUNITY' => $price,
                                    'UF_CRM_1568816002787' => array($bookinginfo),
                                    'UF_CRM_1586786624078' => $data['payment_type']
                                )
                            )
                        )
                )));
            $out = $this->opt($params);

        }
    }



    // добавление заказа из магазина
    public function bitrixCreateOrderShop($data)
    {
        //Log::info('test');
        //dd(array_dot($data));
        // название товара
        $good = $data['name'];
        // цена товара с НДС
        $price =$data['price'];
        // email по ответу платежной системы
        if($data['payer.payer_info.email']) {
            $email = $data['payer.payer_info.email'];
        } elseif($data['object.billing_details.email']) {
            $email = $data['object.billing_details.email'];
        } elseif($data['resource.payer.payer_info.email']) {
            $email = $data['resource.payer.payer_info.email'];
        }
        // имя по ответу платежной системы
        if($data['payer.payer_info.first_name']) {
            $name = $data['payer.payer_info.first_name'];
        } elseif($data['object.shipping.name']) {
            $name = mb_substr($data['object.shipping.name'],0,mb_strrpos(mb_substr($data['object.shipping.name'],0,240),' '));
        } elseif($data['resource.payer.payer_info.first_name']) {
            $name = $data['resource.payer.payer_info.first_name'];
        }
        // фамилия по ответу платежной системы
        if($data['payer.payer_info.last_name']) {
            $lastname = $data['payer.payer_info.last_name'];
        } elseif($data['object.shipping.name']) {
            $lastname = mb_substr($data['object.shipping.name'], strlen($name)+1);
        } elseif($data['resource.payer.payer_info.last_name']) {
            $lastname = $data['resource.payer.payer_info.last_name'];
        }
        // адрес по ответу платежной системы. adress идет в контакт. addr1 и 2 d cltkre
        if ($data['payer.payer_info.shipping_address.line1']) {
            $adress = $name.' '.$lastname.' '.$data['payer.payer_info.shipping_address.line1'].' '.$data['payer.payer_info.shipping_address.city'].' '.$data['payer.payer_info.shipping_address.postal_code'].' '.$data['payer.payer_info.shipping_address.country_code'];
            $addr1 = $data['payer.payer_info.shipping_address.line1'];
            $addr2 = $data['payer.payer_info.shipping_address.city'].' '.$data['payer.payer_info.shipping_address.postal_code'].' '.$data['payer.payer_info.shipping_address.country_code'];
        } elseif($data['object.shipping.address.line1']) {
            $adress = $name.' '.$lastname.' '.$data['object.shipping.address.line1'].' '.$data['object.shipping.address.city'].' '.$data['object.shipping.address.postal_code'].' '.$data['object.shipping.address.country'];
            if($data['object.shipping.address.line1']!= $data['object.shipping.address.line2']) {
                $addr1 = $data['object.shipping.address.line1'].' '.$data['object.shipping.address.line2'];
            } else {
                $addr1 = $data['object.shipping.address.line1'];
            }
            $addr2 = $data['object.shipping.address.city'].' '.$data['object.shipping.address.postal_code'].' '.$data['object.shipping.address.country'];
        } elseif ($data['resource.payer.payer_info.shipping_address.line1']) {
            $adress = $name.' '.$lastname.' '.$data['resource.payer.payer_info.shipping_address.line1'].' '.$data['resource.payer.payer_info.shipping_address.city'].' '.$data['resource.payer.payer_info.shipping_address.postal_code'].' '.$data['resource.payer.payer_info.shipping_address.country_code'];
            $addr1 = $data['resource.payer.payer_info.shipping_address.line1'].' '.$data['resource.payer.payer_info.shipping_address.line2'];
            $addr2 = $data['resource.payer.payer_info.shipping_address.city'].' '.$data['resource.payer.payer_info.shipping_address.postal_code'].' '.$data['resource.payer.payer_info.shipping_address.country_code'];
        }
        // форма платежа - pay pal, card, etc.
        if ($data['payer.payment_method']) {
            $paysystem = $data['payer.payment_method'];
        } elseif($data['object.payment_method_details.type']) {
            $paysystem = $data['object.payment_method_details.type'];
        } elseif ($data['resource.payer.payment_method']) {
            $paysystem = $data['resource.payer.payment_method'];
        }
        // количество
        if ($data['transactions.0.item_list.items.0.quantity']) {
            $qty = $data['transactions.0.item_list.items.0.quantity'];
        } elseif($data['object.paid']) {
            $qty = $data['object.paid'];
        } elseif ($data['resource.transactions.0.item_list.items.0.quantity']) {
            $qty = $data['resource.transactions.0.item_list.items.0.quantity'];
        }
        // имя плательщика
        if ($data['resource.payer.payer_info.shipping_address.recipient_name']){
            $addrname = $data['resource.payer.payer_info.shipping_address.recipient_name'];
        } elseif($data['object.shipping.name']) {
            $addrname = $data['object.shipping.name'];
        } else {
            $addrname = $name.' '.$lastname;
        }

        // проверяем есть ли уже контакт с таким емейлом
        $params = http_build_query(array(
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

        $out = $this->opt($params);

        $cid = isset($out['result']['result']['get_contact'][0]['ID']) ? $out['result']['result']['get_contact'][0]['ID'] : null;

        if($cid) {
            // если контакт найден, то смотрим есть ли у него номер телефона и если нет добавляем номер
            $source =  $out['result']['result']['get_contact'][0]['UF_CRM_1587120391'];
            if(!in_array( 667, $source)){
                array_push($source, 667);

                $params = http_build_query(array(
                    'halt' => 0,
                    'cmd' => array(
                        'get_lead' => 'crm.contact.update?'
                            . http_build_query(array(
                                    'id' => $cid,
                                    'fields' => array('UF_CRM_1587120391'=> $source))
                            )
                    )));
                $out = $this->opt($params);
            }
        } else {
            // добавляем контакт
            $params = http_build_query(array(
                'halt' => 0,
                'cmd' => array(
                    'make_contact' => 'crm.contact.add?'
                        .http_build_query(array(
                                'fields' => array('NAME' => $name, 'LAST_NAME' => $lastname, 'EMAIL' => Array(
                                    "n0" => Array(
                                        "VALUE" =>$email,
                                        "VALUE_TYPE" => "WORK",
                                    )
                                ))
                            )
                        )
                )));
            $out = $this->opt($params);
            $cid = $out['result']['result']['make_contact'];
        }
        // имеем готовый контакт и добавляем сделку
        if($cid) {
            $params = http_build_query(array(
                'halt' => 0,
                'cmd' => array(
                    'make_deal' => 'crm.deal.add?'
                        .http_build_query(array(
                                'fields' => array('TYPE_ID' => 'SALE',
                                    'CONTACT_ID' => $cid,
                                    'ASSIGNED_BY_ID' => 13,
                                    'SOURCE_ID' => 'STORE',
                                    'CATEGORY_ID' => 3,
                                    'STAGE_I' => 'C3:NEW',
                                    'OPPORTUNITY' => $price,
                                    'UF_CRM_1586784491448' => $good,
                                    'UF_CRM_1586784548262' => $qty,
                                    'UF_CRM_1588231312' => $addrname,
                                    'UF_CRM_1586785122025' => $addr1,
                                    'UF_CRM_1587723129'=>$addr2,
                                    'UF_CRM_1586786624078' => $paysystem

                                )
                            )
                        )
                )));
            $out = $this->opt($params);


        }
    }
}