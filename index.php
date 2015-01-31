<?php
//header("Content-Type: application/soap+xml; charset=utf-8");
header("Content-Type: text/xml; charset=utf-8");
header('Cache-Control: no-store, no-cache');
header('Expires: '.date('r'));

function logIt($str)
{
    file_put_contents(__DIR__.'/classes/log.txt', $str."\n", FILE_APPEND | LOCK_EX);
}

function HandleXmlError($errno, $errstr, $errfile, $errline)
{
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

class SoapServiceHandler
{
    /**
     * это нечто вроде хэша календаря пользователя на стороне бэкэнда (мегаплана)
     *
     * заметка: замечено, что в конце идет число, которое инкрементируется при изменениях в календаре бэкэнда
     *
     * Protocol clients SHOULD include the value of this attribute in subsequent requests to the protocol server. See
     * notes in the changeToken parameter description for more information about paging of data with change tokens.
     *
     * @var string
     */
    private $LastChangeToken = '1;3;1a2650ed-db30-4337-b137-8e5771a08443;635582327934430000;12976';

    public function __call($method, $args)
    {
        try {

            logIt('method: '.var_export($method,1));
            logIt('arguments: '.var_export($args,1));

            if(method_exists($this, $method)) {

                $xml = call_user_func_array(array($this, $method), $args);

                if(empty($xml)){
                    throw new Exception('empty xml result');
                }

                $xml = '<?xml version="1.0" encoding="utf-8"?>'.$xml;

                // альтернативный адрес по которому можно синхронизировать данные (используется, если
                // текущий перестанет работать
                $AlternateUrls = 'http://'.$_SERVER['HTTP_HOST'].'/index.php/AlternateUrls';
                $xml = str_replace('$AlternateUrls', $AlternateUrls, $xml);

                $xml = str_replace('$LastChangeToken', $this->LastChangeToken, $xml);

                // на время тестов, чтобы Outlook всегда обращался к текущему серверу
                $xml = str_replace('win-5iml50i9par', $_SERVER['HTTP_HOST'], $xml);

                logIt('RESPONSE:');
                logIt(xmlFormat($xml));
                //logIt('apache_response' .var_export(apache_response_headers()));
                logIt('--------------------------------------------');

                echo $xml;
                exit;

            }else{
                $s = sprintf('The required method "%s" does not exist for %s', $method, get_class($this));
                logIt('Exception: '.$s);
                throw new Exception($s);
            }
        } catch (\Exception $e) {
            // log errors here as well!
            $s = $e->getMessage();
            logIt('Exception: '.$s);
            throw new SOAPFault('SERVER', $s);
        }
    }

    private function GetList($listName = '')
    {
        if(empty($listName)){
            throw new Exception('empty listName');
        }

        return file_get_contents(__DIR__.'/templates/GetList.xml');

        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body>
    <GetListItemChangesSinceTokenResponse xmlns="http://schemas.microsoft.com/sharepoint/soap/">
      <GetListItemChangesSinceTokenResult>
        <listitems xmlns:rs="urn:schemas-microsoft-com:rowset" MinTimeBetweenSyncs="0" RecommendedTimeBetweenSyncs="180" MaxBulkDocumentSyncSize="500" MaxRecommendedEmbeddedFileSize="500" AlternateUrls="'.$AlternateUrls.'"><Changes LastChangeToken="'.$LastChangeToken.'">
</Changes>
<rs:data ItemCount="0"/></listitems>
      </GetListItemChangesSinceTokenResult>
    </GetListItemChangesSinceTokenResponse>
  </soap:Body>
</soap:Envelope>';
    }

    /**
     * возвращает изменения, внесенные в указанный список, после того, как событие, выраженное маркером изменения
     * если маркер изменения не указан - возвращается все элементы (предположительно события)
     * @param string $listName - указанный список
     * @param stdClass $obj - где-то здесь может быть маркер изменения (change token)
     * @param stdClass $obj2 - где-то здесь может быть маркер изменения (change token)
     * @return string
     * @throws Exception
     */
    private function GetListItemChangesSinceToken($listName = '', \stdClass $obj, \stdClass $obj2, $token = null)
    {
        if(empty($listName)){
            throw new Exception('empty listName');
        }

        if(empty($token)){

            // отдаем весь список существующих событий
            return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceToken.xml');

        }elseif($token !== $this->LastChangeToken){

            // отдаем новый токен + diff изменений между токенами (появление, изменение, удаление событий)
            $events = $this->getNewEvents();
            $this->formatEvents($events);
            return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceTokenNewToken.xml');

        }else{

            // нет изменений - отдаем текущий токен
            return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceTokenNoChanges.xml');
        }
    }

    private function getNewEvents()
    {
        return array(
            array(
                'ows_Title'=>'th2222222222222222',
            )
        );
    }

    /**
     * форматирует массив событий в XML-ответ
     * @param array $events
     * @return string
     */
    private function formatEvents(array $events)
    {
        // обязательные поля
        $fields = array(
            'ows_ID'=>'2',
            'ows_Attachments'=>'0',
            'ows_owshiddenversion'=>'2',
            'ows_Created'=>'2015-01-30T13:21:44Z',
            'ows_Modified'=>'2015-01-31T14:14:10Z',
            'ows_ContentTypeId'=>'0x010200FEA33FD05ED01C4A91C5B8FD2B3A9C9F',
            'ows_EventType'=>'0',
            'ows_Title'=>'th2222222222222222',
            'ows_Description'=>'&#10;&#10;&#10;&#10;&#10;&#10;&#10;&#10;&#10;&lt;div dir=&quot;LTR&quot;&gt;&lt;fontface=&quot;Calibri&quot;&gt;ttttttttttttttttttttt&lt;/font&gt;&lt;/div&gt;&#10;&#10;&#10;',
            'ows_Location'=>'mmmmmmmmmmmmmmmmmm',
            'ows_EventDate'=>'2015-01-30T00:00:00Z',
            'ows_EndDate'=>'2015-01-30T23:59:00Z',
            'ows_fAllDayEvent'=>'1',
            'ows_Duration'=>'86340',
            'ows_fRecurrence'=>'0',
            'ows_Editor'=>'1073741823;#  ,#SHAREPOINT\system,#,#,#  ',
            'ows_PermMask'=>'0x7fffffffffffffff',
            'ows__ModerationStatus'=>'0',
            'ows__Level'=>'1',
            'ows_UniqueId'=>'2;#{F336191C-4B8D-4EAA-BFE3-65A648976041}',
            'ows_FSObjType'=>'2;#0',
            'ows_FileRef'=>'2;#sites/lebnik/Lists/Calendar/2_.000',
            'ows_MetaInfo_vti_versionhistory'=>'00000000000000000000000000000000:1,2fa454dc8373da4bbe5e3ba2a8701f73:2',
            'ows_MetaInfo_Categories'=>'',
            'ows_MetaInfo_IntendedBusyStatus'=>'-1',
            'ows_MetaInfo_vti_externalversion'=>'2',
            'ows_MetaInfo_FollowUp'=>'',
            'ows_MetaInfo_Priority'=>'0',
            'ows_MetaInfo_ReplicationID'=>'{23ABF7AA-4A85-4F75-93D1-9163E4ED4462}',
            'ows_MetaInfo_vti_clientversion'=>'2',
            'ows_MetaInfo_BusyStatus'=>'0'
        );

        $result = array();

        foreach($events as $r){
            $array = array();
            foreach($fields as $k => $v){
                if(isset($r[$k])){
                   $v = $r[$k];
                }
                $array[] = '"'.$k.'"="'.$v.'"';
            }
            $result[] = '<z:row '.implode(' ', $array).'>';
        }

        return '<rs:data ItemCount="'.count($result).'">'.implode('', $result).'</rs:data>';
    }

    /**
     * вызывается при изменении или удалении события
     *
     *
     *
     * @param string $listName
     * @param stdClass $obj
     * @param null $tmp
     * @param null $tmp2
     * @return string
     * @throws Exception
     */
    private function UpdateListItems($listName = '', \stdClass $obj, $tmp = null, $tmp2 = null)
    {
        if(empty($listName)){
            throw new Exception('empty listName');
        }

        list($ID,
            $owshiddenversion,
            $Title,
            $Description,
            $Location,
            $EventDate,
            $EndDate,
            $fAllDayEvent,
            $fRecurrence,
            $EventType,
            $MetaInfoFollowUp,
            $MetaInfoPriority,
            $MetaInfoIntendedBusyStatus,
            $MetaInfoBusyStatus,
            $MetaInfoCategories,
            $MetaInfovti_versionhistory) = $obj->Batch->Method->Field;

        if(isset($zz) && !empty($obj->Batch->Method) && $obj->Batch->Method['Cmd'] === 'Delete'){
            // удаляем событие
            return file_get_contents(__DIR__.'/templates/UpdateListItemsDelete.xml');
        }else{
            return file_get_contents(__DIR__.'/templates/UpdateListItems.xml');
        }
    }
}

/**
1. Outlook посылает запрос получения списка (ожидает авторизацию)
2. Мы отвечаем ему запросом бэсик-авторизации
3. Outlook запрашивает логин/пароль у пользователя
4. Outlook посылает нам запрос с логином/паролем в хедерах + повторяет пункт 1
5. Мы отвечаем ему списком GetList
6. Outlook посылает нам запрос GetListItemChangesSinceToken
7. Мы отвечаем ему списком событий + последний токен (который мы генерируем)
8. Outlook посылает нам запрос GetListItemChangesSinceToken + последний токен (который мы ему послали)
9. Мы отвечаем, что ничего не изменилось
 */

$server = new SoapServer(null, array(
    'uri' => 'http://'.$_SERVER['HTTP_HOST'].'/namespace.php',
    'soap_version'=>SOAP_1_2
));
$server->setObject(new SoapServiceHandler());
$server->handle();
