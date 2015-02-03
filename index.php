<?php
//header("Content-Type: application/soap+xml; charset=utf-8");
header("Content-Type: text/xml; charset=utf-8");
header('Cache-Control: no-store, no-cache');
header('Expires: '.date('r'));

function logIt($str){
	file_put_contents(__DIR__.'/classes/log.txt', $str."\n", FILE_APPEND | LOCK_EX);
}

function HandleXmlError($errno, $errstr, $errfile, $errline){
	if ($errno==E_WARNING && substr_count($errstr,"DOMDocument::loadXML()")>0){
		throw new DOMException($errstr);
	}else{
		return false;
	}
}

function xmlFormat($xml){
	if(empty($xml)){
		return '';
	}
	set_error_handler('HandleXmlError');
	$xmlDoc = new DOMDocument();
	$xmlDoc->loadXML($xml);
	restore_error_handler();
	//$xmlDoc->preserveWhiteSpace = false;
	$xmlDoc->formatOutput = true;
	return $xmlDoc->saveXML();
}

logIt('START: '.date('d.m.Y H:i:s'));
logIt('REQUEST: '.$_SERVER['REQUEST_URI']);
logIt(xmlFormat( file_get_contents('php://input') ));
//logIt('apache_request' .var_export(apache_request_headers()));

if (!isset($_SERVER['PHP_AUTH_USER'])) {
	header('WWW-Authenticate: Basic realm="'.$_SERVER['HTTP_HOST'].'"');
	header('HTTP/1.1 401 Unauthorized');
	echo '401 UNAUTHORIZED';
	exit;
}else{
	logIt('PHP_AUTH_USER: '.$_SERVER['PHP_AUTH_USER'].'   PHP_AUTH_PW: '.$_SERVER['PHP_AUTH_PW']);
}

$conn = pg_connect('host=localhost port=5432 dbname=master_clean user=bums_www password=__www_www_bumsik__');

include_once(__DIR__.'/SoapServerHandler.php');

/**
Стандартная схема взаимодействия:
1. Outlook посылает запрос получения списка (ожидает авторизацию)
2. Мы отвечаем ему запросом бэсик-авторизации
3. Outlook запрашивает логин/пароль у пользователя - пользователь вводит их
4. Outlook посылает нам запрос с логином/паролем в хедерах + повторяет пункт 1
5. Мы отвечаем ему списком GetList
6. Outlook посылает нам запрос GetListItemChangesSinceToken
7. Мы отвечаем ему списком событий + последний токен (который мы формируем)
8. Outlook посылает нам запрос GetListItemChangesSinceToken + последний токен (который мы ему послали)
9. Мы отвечаем, что ничего не изменилось
 */

$server = new SoapServer(null, array(
	'uri' => 'http://'.$_SERVER['HTTP_HOST'].'/namespace.php',
	'soap_version'=>SOAP_1_2
));
$server->setObject(new SoapServiceHandler());
$server->handle();
