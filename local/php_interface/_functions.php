<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
	die();
	
function pre($arParams){
	echo "<pre>";
	print_r($arParams);
	echo "</pre>";
}


/**
 * @function sortBy
 *	
 * Array sorting functions (asc / desc) by key
 *
 * @author Borisenko Valentin <vb@valentin-borisenko.com>
 *
 * @return array
 */
function sortBy(&$data, $field, $sortOrder = 'asc')
{
	switch(strtolower($sortOrder))
	{
		case 'asc':
			$code = "return strnatcmp(\$a['$field'], \$b['$field']);";
			
			usort($data, create_function('$a,$b', $code));
		break;

		case 'desc':
			$code = "return strnatcmp(\$b['$field'], \$a['$field']);";
			
			usort($data, create_function('$a,$b', $code));
		break;
	}
	
	return $data;
}

// *****************************************************************
// Debug functions by VB [2017-01-25]							   *
// *****************************************************************

/** common - VB [2017-01-25] */
if(!defined("PATHSEPARATOR")) define("PATHSEPARATOR", getenv("COMSPEC") ? "\\" : "/", true);
if(!defined("DASH_SEPARATOR")) define("DASH_SEPARATOR", str_repeat('-', 75), true);
if(!defined("_S_")) define("_S_", PATHSEPARATOR);
if(!defined("BR")) define("BR", "<br>");
if(!defined("LF")) define("LF", "\n");
if(!defined("DF")) define("DF", "/-debug.log", true);

/**
 * function getUserIP Return client IP address [for developers]
 *
 * @param null|string $ip_param_name - ключ элемента _SERVER, в котором нужно искать IP адрес
 *		если не задано ищем по индексу REMOTE_ADDR и считаем что проксирование отсутствует или прозрачное,
 *      если задано считаем что IP пробрасывается по заданному индексу, например по индексу HTTP_X_REAL_IP или любому другому
 * @param bool $allow_non_trusted - защита, при заданном $ip_param_name но 
 *      отсутствующем или не валидном значении _SERVER[$ip_param_name]
 *      если задано будем искать в _SERVER по ключам из аргумента $non_trusted_param_names
 * @param array $non_trusted_param_names - массив ключей, по которым будем искать IP в массиве _SERVER
 * @throws Exception
 * @return string
 */
function getUserIP(
	$ip_param_name = null,
    $allow_non_trusted = false,
    array $non_trusted_param_names = array('HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR')
)
{
	if(empty($ip_param_name) || !is_string($ip_param_name))
	{
		// если не задан или не корректен
        $ip = $_SERVER['REMOTE_ADDR'];
	}
	else
	{ 
		//иначе используем нужную переменную
        if(!empty($_SERVER[$ip_param_name]) && filter_var($_SERVER[$ip_param_name], FILTER_VALIDATE_IP))
		{
			// если переменная подошла как надо
            $ip = $_SERVER[$ip_param_name];
		}
		elseif($allow_non_trusted)
		{ 
			// мы решили пойти на крайний шаг и использовать сырые данные
            foreach($non_trusted_param_names as $ip_param_name_nt)
			{
				if($ip_param_name === $ip_param_name_nt)
					// мы уже проверяли эту переменную
                    continue;
				if(!empty($_SERVER[$ip_param_name_nt]) && filter_var($_SERVER[$ip_param_name_nt], FILTER_VALIDATE_IP))
				{
					// если переменная подошла как надо
					$ip = $_SERVER[$ip_param_name_nt];
                    break;
				}
			}
		}
	}
    
	if(empty($ip)) 
		// так и не нашли подходящих ip, хотя по умолчанию в $_SERVER['REMOTE_ADDR'] что-то должно лежать
		throw new Exception("Can't detect IP");
	
	return $ip;
}

/**
 * function myIP Isolation of code [for developers]
 *
 * @param string $ip IP address for isolation
 *
 * @author Borisenko Valentin <askme@valentin-borisenko.com>
 * @date 2017-01-25
 */
function myIP($ip = '')
{
	$realIP = getUserIP(); 

    return $realIP == $ip;
}

/**
 * function sv Output var data
 *
 * @param mixed $var Var
 * @param string $desc Description for var
 * @param string $ip Description for var
 * @param boolean $return File with var data
 *
 * @author Borisenko Valentin <askme@valentin-borisenko.com>
 * @date 2017-01-25
 */
function sv($var, $desc = '', $ip = '', $return = false)
{
    if($return == 'force') $return = false;
	
    if(is_string($return))
    {
        if($return == 'file') $file = $_SERVER['DOCUMENT_ROOT'] . '/sv-log.html';
        else $file = $return;
		
        $return = '
			<style type="text/css">
				b.sv {font-size:16px;}
				pre {display:block; padding:9.5px; margin:0 0 10px; font-size:13px; line-height:1.42857143; color:#333; word-break:break-all; word-wrap:break-word; background-color:#f5f5f5; border:1px solid #ccc; border-radius:4px;}
				pre code {padding:0; font-size:inherit; color:inherit; white-space:pre-wrap; background-color:transparent; border-radius:0;}
			</style>
		';
		
        if($desc != '') $desc = '<b class="sv">' . $desc . '</b></br>';
		
        if(is_bool($var))
        {
			$return .= '<pre style="text-align:left; color:' . ($var === false ? 'red' : 'green') . '; border:1px solid ' . ($var === false ? 'red' : 'green') . ';">'.$desc;
            $return .= $var === false ? 'false' : 'true';
            $return .= '</pre>';
        }
        if(is_array($var) || is_object($var))
        {
            $return .= '<pre style="text-align:left; color:blue; border:1px solid blue">' . $desc;
            $return .= htmlspecialchars(var_export($var,true));
            $return .= '</pre>';
        }
        else
        {
            $return .= '<pre style="text-align:left; color:green; border:1px solid green; ">' . $desc;
            $return .= htmlspecialchars($var);
            $return .= '</pre>';
        }
		
        $file = fopen($file, 'a'); 
		
        fputs($file,  $return . "(" . date('H:i:s d.m.y') . ")\n");
        fclose($file);
    }
    else
    {
		if(!empty($ip) && myIP($ip) || empty($ip))
		{
			echo '
				<style type="text/css">
					b.sv {font-size:16px;}
					pre {display:block; padding:9.5px; margin:0 0 10px; font-size:13px; line-height:1.42857143; color:#333; word-break:break-all; word-wrap:break-word; background-color:#f5f5f5; border:1px solid #ccc; border-radius:4px;}
					pre code {padding:0; font-size:inherit; color:inherit; white-space:pre-wrap; background-color:transparent; border-radius:0;}
				</style>
			';
			echo '<b class="sv">' . $desc . '</b><br />';
			
			if(is_bool($var))
			{
				echo '<pre style="text-align:left; color:' . ($var === false ? 'red' : 'green') . '; border:1px solid ' . ($var === false ? 'red' : 'green') . ';">';
				htmlspecialchars(var_dump($var));
				echo '</pre>';
			}
			elseif(is_array($var))
			{
				echo '<pre style="text-align:left; color:blue; border:1px solid blue">';
				print_r($var);
				echo '</pre>';
			}
			elseif(is_string($var))
			{
				echo '<pre style="text-align:left; color:gray; border:1px solid gray">';
				echo htmlspecialchars($var);
				echo '</pre>';
			}
			else
			{
				echo '<pre style="text-align:left; color:orange; border:1px solid orange">';
				var_dump($var);
				echo '</pre>';
			}
		}
	}
}

/**
 * function printObject Output var data
 *
 * @param mixed $var Var
 * @param boolean $format Formating output
 *
 * @author Borisenko Valentin <askme@valentin-borisenko.com>
 * @date 2017-01-25
 */
function printObject($var, $format = false)
{
	if(is_string($var) && !is_numeric($var))
	{ 
		if($format) $out = '<span style="color:green">' . $var . '</span>';
	}
	elseif(is_numeric($var))
	{
		if($format) $out = '<span style="color:red">' . $var . '</span>';
	}
	elseif(is_bool($var))
	{ 
		$var = ($var === true) ? 'TRUE' : 'FALSE';
		if($format) $out = '<span style="color:magenta">' . $var . '</span>';
	}
	elseif(is_null($var))
	{ 
		$var = 'NULL';
		if($format) $out = '<span style="color:#D1D405">' . $var . '</span>';
	}
	elseif(is_array($var))
	{
		$var = var_export($var, true);
		if($format) $out = '<span style="color:#118E96">' . $var . '</span>';
	}
	elseif(is_object($var))
	{
		$var = var_export($var, true);
		if($format) $out = '<span style="color:orange">' . $var . '</span>';
	}
	
	if($format)
	{
		$out = "<div style='font:normal 8pt monospace;'><pre>" . $out . "</pre></div>";
	}
	else $out = $var;
	
	return $out;
}

/**
 * function logObject Write var data to a file
 *
 * @param string $p Path to the log file
 * @param mixed $d Var
 * @param array $arParams Params for writing
 *
 * @author Borisenko Valentin <askme@valentin-borisenko.com>
 * @date 2017-01-25
 */
function logObject($p, $d = "", $arParams = array())
{
	if(empty($p) || func_num_args() == 1)
	{
		$path 	= $_SERVER['DOCUMENT_ROOT'] . PATHSEPARATOR . "-debug.log";
		$d		= empty($d) ? $p : $d;
	}
	else
	{
		if(strstr($p, $_SERVER['DOCUMENT_ROOT']) !== false) $path = $p;
		else
		{
			if(strstr($p, ".") !== false && strtolower(substr ($p, -3)) == 'txt') $p = substr($p, 0, -3) . "log";
			else $p .= ".log" ;
			
			$path = $_SERVER['DOCUMENT_ROOT'] . PATHSEPARATOR . $p;
		}
	}
	
	$o = (!empty($arParams['TITLE']) ? $arParams['TITLE'] . ' [' . date("d.m.Y H:i:s") . ']' . LF . DASH_SEPARATOR : date("d.m.Y H:i:s"));
	
	if($arParams['LOG_ONLY_DATA'])
		$d = (!empty($arParams['VAR_TEXT']) ? '[' . date("d.m.Y H:i:s") . '] ' . $arParams['VAR_TEXT'] : '') . (printObject($d)) . LF;
	else
		$d = LF . $o . LF . (!empty($arParams['VAR_TEXT']) ? '[' . date("d.m.Y H:i:s") . '] ' . $arParams['VAR_TEXT'] : '') . (printObject($d)) . LF;
	
	file_put_contents($path, $d, FILE_APPEND);
}

/**
 * function docxReplace Replacement tags at the *.odt, *.docx files
 *
 * @param string $tplFile Path to the template
 * @param mixed $newFile Path to the new file
 * @param array $arReplacements Array with replacements
 *
 * @return mixed Return a new file or false
 *
 * @author Borisenko Valentin <askme@valentin-borisenko.com>
 * @date 2017-02-16
 */
function docxReplace($tplFile, $newFile, $arReplacements)
{
	if(!copy($tplFile, $newFile))
		return false;
	
	$zip = new ZipArchive();
	
	if($zip->open($newFile, ZIPARCHIVE::CHECKCONS) !== true)
		return false;
	
	$file = substr($tplFile, -4) == '.odt' ? 'content.xml' : 'word/document.xml';
	$data = $zip->getFromName($file);
	
	foreach($arReplacements as $key=>$value)
		$data = str_replace($key, $value, $data);
	
	$zip->deleteName($file);
	$zip->addFromString($file, $data);
	$zip->close();
	
	return true;
}

if(!function_exists('GetStringInsideHtmlTag')) {
	/**
	 * Returns string, that placed inside passed html tag
	 *
	 * @param $string
	 * @param $tagname
	 * @return mixed
	 */
	function GetStringInsideHtmlTag($string, $tagname) {
		$pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
		preg_match($pattern, $string, $matches);

		return $matches[1];
	}
}

if(!function_exists('GetStringInsideHtmlAttribute')) {
	/**
	 * Returns string, that placed inside passed html attribute and html tag (input type additional)
	 *
	 * @param $string
	 * @param $tagname
	 * @param $attr_name
	 * @param $input_type
	 * @return mixed
	 */
	function GetStringInsideHtmlAttribute($string, $tagname, $attr_name, $input_type = '') {
		preg_match('/<'.$tagname.'.*\s+'.($input_type ? 'type=\"'.$input_type.'\"' : '').'.* '.$attr_name.'=\"(.*?)\".*>/', $string, $matches);

		return $matches[1];
	}
}
if(!function_exists('IsCommandExist')) {
	/**
	 * Determines if a command exists on the current environment
	 *
	 * @param string $command The command to check
	 * @return bool True if the command has been found ; otherwise, false.
	 */
	function IsCommandExist($command) {
		$whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';

		$process = proc_open(
			"$whereIsCommand $command",
			array(
				0 => array("pipe", "r"), //STDIN
				1 => array("pipe", "w"), //STDOUT
				2 => array("pipe", "w"), //STDERR
			),
			$pipes
		);
		if ($process !== false) {
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);

			return $stdout != '';
		}

		return false;
	}
}
if(!function_exists('IsDirEmpty')) {
	/**
	 * @param string $dir Directory to check
	 * @return bool|null
	 */
	function IsDirEmpty($dir) {
		if(!is_readable($dir)) {
			return null;
		}
		$handle = opendir($dir);
		while (false !== ($entry = readdir($handle))) {
			if ($entry != '.' && $entry != '..') {
				return false;
			}
		}

		return true;
	}
}

if(!function_exists('GetDirFileList')) {
	function GetDirFileList($dir) {
		$result = array();

		if(is_readable($dir)) {
			$result = array_diff(scandir($dir), array('..', '.'));
		}

		return $result;
	}
}
?>