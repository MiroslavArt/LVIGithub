<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Tasks\Dispatcher;
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/init.php');
define("BX_LOCAL_ROOT", realpath(dirname(__FILE__)."/.."));
define("BX_CLASSES_ROOT", "/local/php_interface/classes");
//use local\Helpers\SetEvents;
Loc::loadMessages(__FILE__);
//local\Helpers\SetEvents::init();
/*
 * Константы и глобальные переменные (статические константы и переменные объявлять только в этих файлах)
 */
require_once(BX_LOCAL_ROOT . '/php_interface/_constants.php');
require_once(BX_LOCAL_ROOT . '/php_interface/_globals.php');
require_once(BX_LOCAL_ROOT . '/php_interface/_functions.php');

/*
 * Подключим кастомные классы и функции
 */
/*CModule::AddAutoloadClasses(
	'',
	array(
		'CConfig' => BX_CLASSES_ROOT . '/CConfig.php',
		'CTools' => BX_CLASSES_ROOT . '/CTools.php',
		'CUUID' => BX_CLASSES_ROOT . '/CUUID.php',
		'CTimemanStatistics' => BX_CLASSES_ROOT . '/CTimemanStatistics.php',
		'HandlerPropertyIblock' => BX_CLASSES_ROOT . '/HandlerPropertyIblock.php',
		'OnTaskAdd' => BX_CLASSES_ROOT . '/OnTaskAdd.php'
		//'OwnAgent' => BX_CLASSES_ROOT . '/ownsession.php',
	)
); */
CModule::IncludeModule('crm');
CModule::IncludeModule('tasks');
EventManager::getInstance()->addEventHandler(
    'tasks',
    'OnTaskAdd',
    function ($id, $data) {
		//\Bitrix\Main\Diag\Debug::writeToFile($id, "iddd", "__miros.log");
		//\Bitrix\Main\Diag\Debug::writeToFile($data, "iddd", "__miros.log");
        if($data['GROUP_ID']==271) {
            $name = $data['TITLE'];
            $user = $data['CREATED_BY'];
            $oUserinfo = CUser::GetByID($user);
            $rs = $oUserinfo->getNext();
            $depts = $rs['UF_DEPARTMENT'];
            $oDep = CIntranetUtils::GetDepartmentsData($depts);
            $pattern = '#^[0-9]+$#';
            foreach ($oDep as $dept) {
                $first = substr($dept, 0, 1);
                //echo $first;
                if(preg_match($pattern, $first)) {
                    $line = $first;
                    break;
                }
            }
            if(!$line) {
                $line = 0;
            }
			if(!$data['UF_AUTO_733311567368']) {
				$data['UF_AUTO_733311567368'] = 0; 
			}
            $newname = "Z_".$line."_".$data['UF_AUTO_733311567368'].'€_'.$name;
            $arFields = array('TITLE' => $newname);      // 6 - CTasks::STATE_DEFERRED
            //$ID = $id;
            $oTaskItem = CTaskItem::getInstance($id, $user);   // 53 - это USER_ID, от имени которого будет сделано действие и проверены права
            $oTaskItem->update($arFields);
        }
    }
);

EventManager::getInstance()->addEventHandler(
    'tasks',
    'OnTaskUpdate',
    function ($id, $data) {
		$newsum = $data['UF_AUTO_733311567368']; 
		$oldsum = $data['META:PREV_FIELDS']['UF_AUTO_733311567368'];
        $user = $data['CREATED_BY'];
		//\Bitrix\Main\Diag\Debug::writeToFile($data, "idddup", "__miros.log");
		//\Bitrix\Main\Diag\Debug::writeToFile($newsum, "idddup", "__miros.log");
		//\Bitrix\Main\Diag\Debug::writeToFile($oldsum, "idddup", "__miros.log");
        // смена сумм
		if($newsum != $oldsum) {
			$oldname = $data['TITLE'];
			$needle = '_';
			$name = strrchr($oldname, $needle);
            $oUserinfo = CUser::GetByID($user);
            $rs = $oUserinfo->getNext();
            $depts = $rs['UF_DEPARTMENT'];
            $oDep = CIntranetUtils::GetDepartmentsData($depts);
            $pattern = '#^[0-9]+$#';
            foreach ($oDep as $dept) {
                $first = substr($dept, 0, 1);
                //echo $first;
                if(preg_match($pattern, $first)) {
                    $line = $first;
                    break;
                }
            }
            if(!$line) {
                $line = 0;
            }
            $newname = "Z_".$line."_".$data['UF_AUTO_733311567368'].'€'.$name;
            $arFields = array('TITLE' => $newname);      // 6 - CTasks::STATE_DEFERRED
            //$ID = $id;
            $oTaskItem = CTaskItem::getInstance($id, $user);   // 53 - это USER_ID, от имени которого будет сделано действие и проверены права
            $oTaskItem->update($arFields);
		}
		// закрываемость сделок
		if($data['META:PREV_FIELDS']['GROUP_ID'] == 279 && $data['CLOSED_BY']) {
		    if(!$user) {
		        $user = $data['META:PREV_FIELDS']['CREATED_BY'];
            }
            //\Bitrix\Main\Diag\Debug::writeToFile("here", "here", "__miros.log");
            $arFields = array('STATUS' => 2, 'CLOSED_BY' => '', 'CLOSED_DATE' => '');      // 6 - CTasks::STATE_DEFERRED
            $deal = $data['META:PREV_FIELDS']['UF_CRM_TASK'][0];
            $dealclean = preg_replace("/[^0-9]/", '', $deal);
            if($dealclean) {
                $date = new DateTime('-5 days');
                $from = $date->format("d.m.Y");
                $rs = Bitrix\Crm\Timeline\Entity\TimelineTable::getList(array(
                    'order' => array("ID" => "DESC"),
                    'filter' => array(
                        '>=CREATED' => ConvertDateTime($from,'DD.MM.YYYY')." 00:00:00.000000",
                        'CRM_TIMELINE_ENTITY_TIMELINE_BINDINGS_ENTITY_TYPE_ID' => 2,
                        'CRM_TIMELINE_ENTITY_TIMELINE_BINDINGS_ENTITY_ID' => $dealclean
                    ),
                    'select'=>array("*", "BINDINGS")
                ));
                $n = 0;
                while ($arResCompany = $rs->Fetch()){
                    //\Bitrix\Main\Diag\Debug::writeToFile($arResCompany, "event", "__miros.log");
                    if($arResCompany['ASSOCIATED_ENTITY_CLASS_NAME']=='TASKS') {
                    } elseif($arResCompany['SETTINGS']['WORKFLOW_ID']) {
                    } else {
                        $n++;
                    }
                }
                if($n==0) {
                    CModule::IncludeModule('im');
                    $arFieldschat = array(
                        "MESSAGE_TYPE" => "P", # P - private chat, G - group chat, S - notification
                        "TO_USER_ID" => $data['CLOSED_BY'],
                        "FROM_USER_ID" => 13,
						"MESSAGE" => "Die Aufgabe ".$id." kann nicht geschlossen werden, da der Auftrag keine Aktivität erfasst!",
                        "AUTHOR_ID" => 13
						//"EMAIL_TEMPLATE" => "some",
						//"NOTIFY_TYPE" => 2,  # 1 - confirm, 2 - notify single from, 4 - notify single
						//"NOTIFY_MODULE" => "main", # module id sender (ex: xmpp, main, etc)
						//"NOTIFY_EVENT" => "IM_GROUP_INVITE", # module event id for search (ex, IM_GROUP_INVITE)
						//"NOTIFY_TITLE" => "title to send email", # notify title to send email
                    );
                    CIMMessenger::Add($arFieldschat);
                    $todo = array();
                    array_push($todo, array(
                        'OPERATION' => 'task.renew',
                        'ARGUMENTS' => array(
                            'id' => $id
                        ),
                        'PARAMETERS' => array(
                            'code' => 'op_0'
                        )
                    ));
                    array_push($todo, array(
                        'OPERATION' => 'task.get',
                        'ARGUMENTS' => array(
                            'id' => $id,
                            'parameters' => array(
                                'ENTITY_SELECT' => array('DAYPLAN')
                            )
                        ),
                        'PARAMETERS' => array(
                            'code' => 'task_data'
                        )
                    ));
                    // новый вариант
                    $plan = new Dispatcher\ToDo\Plan();
                    $plan->import($todo);
                    $dispatcher = new Dispatcher();
                    $dispatcher->run($plan);
                    $exec = $plan->exportResult();
                    return array($exec);

                    //$oTaskItem = CTaskItem::getInstance($id, $user);   // 53 - это USER_ID, от имени которого будет сделано действие и проверены права
                    //$oTaskItem->update($arFields);
                }
            }
        }
    }
);

EventManager::getInstance()->addEventHandler(
    'crm',
    'OnActivityUpdate',
    function ($data) {
		//\Bitrix\Main\Diag\Debug::writeToFile('mir', "idddup", "__miros.log");
		//\Bitrix\Main\Diag\Debug::writeToFile($data, "acupdate", "__miros.log");
 	}
);


//
/*
 * Функции (функции объявлять только здесь)
 */
//require_once(BX_LOCAL_ROOT . '/php_interface/_functions.php');

/*
 * Обработчики событий (статические обработчики объявлять только здесь)
 */
//require_once(BX_LOCAL_ROOT . '/php_interface/_handlers.php');

/*
 * Свой тип свойств инфоблока для БП Согласование счета
 */
//require_once(__DIR__ . '/mydivisions.php');


/*function GetGlobalID()
{
	global $GLOBAL_IBLOCK_ID;
	global $GLOBAL_FORUM_ID;
	global $GLOBAL_BLOG_GROUP;
	global $GLOBAL_STORAGE_ID;
	$ttl = 2592000;
	$cache_id = 'id_to_code_';
	$cache_dir = '/bx/code';
	$obCache = new CPHPCache;

	if ($obCache->InitCache($ttl, $cache_id, $cache_dir))
	{
		$tmpVal = $obCache->GetVars();
		$GLOBAL_IBLOCK_ID = $tmpVal['IBLOCK_ID'];
		$GLOBAL_FORUM_ID = $tmpVal['FORUM_ID'];
		$GLOBAL_BLOG_GROUP = $tmpVal['BLOG_GROUP'];
		$GLOBAL_STORAGE_ID = $tmpVal['STORAGE_ID'];

		unset($tmpVal);
	}
	else
	{
		if (CModule::IncludeModule("iblock"))
		{
			$res = CIBlock::GetList(
				Array(),
				Array("CHECK_PERMISSIONS" => "N")
			);

			while ($ar_res = $res->Fetch())
			{
				$GLOBAL_IBLOCK_ID[$ar_res["CODE"]] = $ar_res["ID"];
			}
		}

		if (CModule::IncludeModule("forum"))
		{
			$res = CForumNew::GetList(
				Array()
			);

			while ($ar_res = $res->Fetch())
			{
				$GLOBAL_FORUM_ID[$ar_res["XML_ID"]] = $ar_res["ID"];
			}
		}

		if (CModule::IncludeModule("blog"))
		{
			$arFields = Array("ID", "SITE_ID");

			$dbGroup = CBlogGroup::GetList(array(), array(), false, false, $arFields);
			if ($arGroup = $dbGroup->Fetch())
			{
				$GLOBAL_BLOG_GROUP[$arGroup["SITE_ID"]] = $arGroup["ID"];
			}
		}

		if (CModule::IncludeModule("disk"))
		{
			$dbDisk = Bitrix\Disk\Storage::getList(array("filter"=>array("=ENTITY_TYPE" => Bitrix\Disk\ProxyType\Common::className())));
			if ($commonStorage = $dbDisk->Fetch())
			{
				$GLOBAL_STORAGE_ID["shared_files"] = $commonStorage["ID"];
			}
		}

		if ($obCache->StartDataCache())
		{
			$obCache->EndDataCache(array(
			   'IBLOCK_ID' => $GLOBAL_IBLOCK_ID,
			   'FORUM_ID' => $GLOBAL_FORUM_ID,
			   'BLOG_GROUP' => $GLOBAL_BLOG_GROUP,
			   'STORAGE_ID' => $GLOBAL_STORAGE_ID,
		   ));
		}
	}
}
?>*/
