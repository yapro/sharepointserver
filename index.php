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

$inputXML = file_get_contents('php://input');

logIt('START: '.date('d.m.Y H:i:s'));
logIt('REQUEST: '.$_SERVER['REQUEST_URI']);
logIt(xmlFormat($inputXML));
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

$requestObject = simplexml_load_string($inputXML);

if(empty($requestObject->Body)){
	throw new \Exception('request without method');
}

$method = key($requestObject->Body);
$arguments = (array)$requestObject->Body->$method;

logIt('method: '.var_export($method,1));
logIt('arguments: '.var_export($arguments,1));

//list($listName, $viewFields, $queryOptions, $changeToken) = $arguments;

if(empty($arguments['listName'])){
	throw new Exception('empty $listName');
}

$handler = new SoapServiceHandler();

// сетим LastChangeToken из б.д.
$handler->loadLastChangeToken($arguments['listName']);

unset($arguments['listName']);

$xml = call_user_func_array(array($handler,$method), $arguments);

if(empty($xml)){
	throw new Exception('empty xml result');
}

// общая часть всех xml-response
$xml = '<?xml version="1.0" encoding="utf-8"?>'.$xml;

// альтернативный адрес по которому можно синхронизировать данные (используется, если
// текущий перестанет работать (возможно можно вообще отказаться от него)
$AlternateUrls = 'http://'.$_SERVER['HTTP_HOST'].'/index.php/AlternateUrls';
$xml = str_replace('$AlternateUrls', $AlternateUrls, $xml);

$xml = str_replace('$LastChangeToken', $handler->getLastChangeToken(), $xml);

// на время тестов, чтобы Outlook всегда обращался к текущему серверу
$xml = str_replace('win-5iml50i9par', $_SERVER['HTTP_HOST'], $xml);

logIt('RESPONSE:');
logIt(xmlFormat($xml));
//logIt('apache_response' .var_export(apache_response_headers()));
logIt('--------------------------------------------');

echo $xml;