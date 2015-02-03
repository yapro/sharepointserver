<?php

class SoapServiceHandler
{
	/**
	 * это нечто вроде хэша календаря пользователя на стороне бэкэнда
	 *
	 * заметка: замечено, что в конце идет число, которое инкрементируется при изменениях в календаре бэкэнда
	 *
	 * Protocol clients SHOULD include the value of this attribute in subsequent requests to the protocol server. See
	 * notes in the changeToken parameter description for more information about paging of data with change tokens.
	 *
	 * @var string
	 */
	private $lastChangeToken = '';

	/**
	 * возвращает текущее значение токена
	 * @return string
	 */
	private function getLastChangeToken()
	{
		return $this->lastChangeToken;
	}

	/**
	 * сетит значение токена в локальную переменную класса
	 * @param $value
	 */
	private function setLastChangeToken($value)
	{
		$this->lastChangeToken = $value;
	}

	/**
	 * подгружает значение токена из базы данных и сетит его в переменную $this->lastChangeToken
	 */
	private function loadLastChangeToken($listName)
	{
		$userId = $this->getUserIdByListName($listName);
		if($q = pg_query('SELECT * FROM bums.outlook_calendar WHERE user_id = '.$userId)){
			$lastChangeTokenTime = 0;
			if($r = pg_fetch_row($q)){
				// '1;3;1a2650ed-db30-4337-b137-8e5771a08443;635582327934430000;12976'
				$lastChangeTokenTime = (int)$r['1'];
			}
			// сетим время последнего изменения данных
			return $this->setLastChangeToken($lastChangeTokenTime);
		}
		throw new \Exception('problems with database');
	}

	/**
	 * вытаскивает идентификатор пользователя из идентификатора календаря
	 *
	 * @param $listName - идентификатор календаря
	 *
	 * @return int
	 * @throws Exception
	 */
	private function getUserIdByListName($listName)
	{
		$listName = $this->checkListName($listName);
		list($tmp, $tmp, $tmp, $tmp, $userId) = sscanf($listName, '{%d-%d-%d-%d-%d}');
		if(empty($userId) || !is_numeric($userId)){
			throw new \Exception('wrong type userId');
		}
		return $userId;
	}

	/**
	 * сохраняет токен в базе данных (не сетит его в локальную переменную класса)
	 * @param $userId int
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function saveLastChangeToken($userId)
	{
		if(!pg_query('UPDATE bums.outlook_calendar
		SET lastChangeTokenTime = '.time().'
		WHERE user_id = '.$userId)){
			throw new \Exception('update database problem');
		}
		return true;
	}

	public function __call($method, $args)
	{
		try {

			logIt('method: '.var_export($method,1));
			logIt('arguments: '.var_export($args,1));

			if(method_exists($this, $method)) {

				if(empty($args['0'])){
					throw new Exception('empty $listName');
				}

				// сетим LastChangeToken из б.д.
				$this->loadLastChangeToken($args['0']);

				$xml = call_user_func_array(array($this, $method), $args);

				if(empty($xml)){
					throw new Exception('empty xml result');
				}

				// общая часть всех xml-response
				$xml = '<?xml version="1.0" encoding="utf-8"?>'.$xml;

				// альтернативный адрес по которому можно синхронизировать данные (используется, если
				// текущий перестанет работать (возможно можно вообще отказаться от него)
				$AlternateUrls = 'http://'.$_SERVER['HTTP_HOST'].'/index.php/AlternateUrls';
				$xml = str_replace('$AlternateUrls', $AlternateUrls, $xml);

				$xml = str_replace('$LastChangeToken', $this->getLastChangeToken(), $xml);

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



	/**
	 * отдает какие-то настройки календаря (нужно разобраться)
	 *
	 * @param string $listName - уникальный идентификатор календаря пользователя
	 * @return string
	 * @throws Exception
	 */
	private function GetList($listName = '')
	{
		$listName = $this->checkListName($listName);

		return file_get_contents(__DIR__.'/templates/GetList.xml');
		/*
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
		*/
	}

	/**
	 * возвращает изменения, внесенные в указанный список, согласно состоянию токена, а
	 * если токен не указан - возвращает все события
	 *
	 * @param string $listName - указанный список
	 * @param stdClass $obj - объекты с какими-то данными
	 * @param stdClass $obj2 - объекты с какими-то данными
	 * @param string $token - может и не быть переданными
	 * @return string
	 * @throws Exception
	 */
	private function GetListItemChangesSinceToken($listName, \stdClass $obj, \stdClass $obj2, $token = null)
	{
		$listName = $this->checkListName($listName);

		if(empty($token)){

			// отдаем весь список существующих событий
			return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceToken.xml');

		}elseif($token !== $this->getLastChangeToken()){

			// отдаем новый токен + diff изменений между токенами (появление, изменение, удаление событий)
			$eventsArray = $this->getNewEvents($listName, $token);
			$eventsXml = $this->formatEvents($eventsArray);
			$xml = file_get_contents(__DIR__.'/templates/GetListItemChangesSinceTokenNewToken.xml');

			return str_replace('$eventsXml', $eventsXml, $xml);

		}else{

			// нет изменений - отдаем текущий токен
			return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceTokenNoChanges.xml');
		}
	}

	/**
	 * проверяет правильность идентификатора каледаря
	 *
	 * @param $listName
	 * @return mixed
	 * @throws Exception
	 */
	private function checkListName($listName)
	{
		if(empty($listName)){
			throw new Exception('empty listName');
		}
		if(!is_string($listName)){
			throw new Exception('wrong data type listName');
		}
		return $listName;
	}

	/**
	 * находит события добавленные/измененные/удаленные после указанного $token
	 *
	 * @param $listName
	 * @param $token
	 * @return array
	 */
	private function getNewEvents($listName, $token)
	{
		// находим diff между токеном $token и $this->getLastChangeToken()
		return array(
				array(
						'ows_ID'=>2,
						'ows_Title'=>'time'.time(),
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
				'ows_Created'=>'2015-02-01T13:21:44Z',
				'ows_Modified'=>'2015-02-01T14:14:10Z',
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
	private function UpdateListItems($listName, \stdClass $obj, $tmp = null, $tmp2 = null)
	{
		$listName = $this->checkListName($listName);

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
			// удаляем событие в б.д. и возвращаем информацию о удалении
			return file_get_contents(__DIR__.'/templates/UpdateListItemsDelete.xml');
		}else{
			// обновляем события в б.д. и возвращаем данные обновленных событий
			return file_get_contents(__DIR__.'/templates/UpdateListItems.xml');
		}
	}
}