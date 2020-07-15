<?php
// меняем ajax строку
if(strpos($arResult['SERVICE_URL'], 'crm.lead.details')) {
    $needle = '/local';
    $surlnew = substr_replace($arResult['SERVICE_URL'],$needle,0,7);
    $arResult['SERVICE_URL'] = $surlnew;
}

//\Bitrix\Main\Diag\Debug::writeToFile($arResult, "result", "__miros5.log");

