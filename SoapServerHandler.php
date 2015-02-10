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
	public function getLastChangeToken()
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
	 * идентификатор пользователя
	 * @var int
	 */
	private $userId = 0;

	private function setUserId($value)
	{
		$this->userId = $value;
	}

	private function getUserId()
	{
		return $this->userId;
	}


	/**
	 * подгружает значение токена из базы данных и сетит его в переменную $this->lastChangeToken
	 */
	public function loadLastChangeToken($listName)
	{
		$this->setUserId($this->getUserIdByListName($listName));
		$this->setUserId(1000027);// временно
		// if($q = pg_query('SELECT * FROM bums.outlook_calendar WHERE user_id = )){
		if($q = pg_query('SELECT MAX(time_updated) FROM bums.item WHERE user_created_id = '.$this->getUserId())){
			$lastTime = 0;
			if($r = pg_fetch_row($q)){
				$lastTime = $r['0'];// '1;3;1a2650ed-db30-4337-b137-8e5771a08443;635582327934430000;12976'
			}
			// сетим время последнего изменения данных
			return $this->setLastChangeToken($lastTime);
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
	 * обновляет в б.д. токен текущего пользователя и сетит токен в локальную переменную класса
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function updateLastChangeToken()
	{
		$lastTime = time();
		if(!pg_query('UPDATE bums.outlook_calendar
		SET last_time = '.$lastTime.'
		WHERE user_id = '.$this->getUserId())){
			throw new \Exception('update database problem');
		}
		$this->setLastChangeToken($lastTime);
		return true;
	}

	/**
	 * отдает какие-то настройки календаря (нужно разобраться)
	 *
	 * @return string
	 * @throws Exception
	 */
	public function GetList()
	{
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
	 * @param SimpleXMLElement $obj - объекты с какими-то данными
	 * @param SimpleXMLElement $obj2 - объекты с какими-то данными
	 * @param string $token - может и не быть переданными
	 * @return string
	 * @throws Exception
	 */
	public function GetListItemChangesSinceToken(\SimpleXMLElement $obj, \SimpleXMLElement $obj2, $token = null)
	{
		$token = 1;// для теста

		if(
				empty($token)// при первой синхронизации не посылается токен
				||
				$token != $this->getLastChangeToken()// когда Microsoft Outlook отстает от SharePoint-сервера
		){

			$token = empty($token)? '2000-01-01 01:01:01+01' : $token;

			// if empty($token) - отдаем весь список существующих событий
			// return file_get_contents(__DIR__.'/templates/GetListItemChangesSinceToken.xml');

			// отдаем новый токен + diff изменений между токенами (появление, изменение, удаление событий)
			$eventsArray = $this->getNewEvents($token);

			$eventsXml = $this->formatEvents($eventsArray);

			// обновим LastChangeToken в Microsoft Outlook
			if(!empty($eventsArray)){
				$latest = array_pop($eventsArray);
				$this->setLastChangeToken($latest['time_updated']);
			}

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
	 * @param $token
	 * @return array
	 */
	private function getNewEvents($token)
	{
		$dt1 = date_create($token);// $dt->format('YmdHis');
		$dt2 = date_create($this->getLastChangeToken());
		$min = '2015-01-25 12:42:41+03';//($dt1 > $dt2)? $this->getLastChangeToken() : $token;
		$max = '2015-01-26 12:42:41+03';//($dt1 > $dt2)? $token : $this->getLastChangeToken();

		// находим diff между токеном $token и $this->getLastChangeToken()

		$data = array();
		if($q = pg_query('SELECT * FROM bums.item WHERE
		user_created_id = \''.$this->getUserId().'\' AND
		time_updated BETWEEN \''.$min.'\' and \''.$max.'\' ORDER BY time_updated')){
			while($r = pg_fetch_assoc($q)){
				$data[] = $this->mapEvent($r);
			}
		}
		return $data;
	}

	/**
	 * мапит данные события в формат угодный Microsoft Outlook
	 *
	 * @param array $r
	 *
	 * @return array
	 */
	private function mapEvent(array $r)
	{
		// если не указано время от и до, то значит событие на весь день
		if(empty($r['time_from']) || empty($r['time_to'])){
			$r['time_from'] = $r['date_from'];
			$r['time_to'] = $r['date_to'];
			$r['full_day'] = 1;
		}

		return array(
			'ows_ID'=>$r['item_id'],
			'ows_fAllDayEvent'=>($r['full_day']? 1 : 0),
			'ows_fRecurrence'=>($r['repetition']? 1 : 0),
			'ows_Created'=>$this->dateFormat($r['time_created']),
			'ows_Modified'=>$this->dateFormat($r['time_updated']),
			'ows_EventDate'=>$this->dateFormat($r['time_from']),
			'ows_EndDate'=>$this->dateFormat($r['time_to']),
			'ows_Title'=>$r['name'],
			'ows_Description'=>$r['description'],
		);
	}

	/**
	 * приводит дату времени к формату угодному Microsoft Outlook
	 *
	 * @param $date
	 *
	 * @return string
	 */
	private function dateFormat($date)
	{
		return date_create($date)->format('Y-m-d\TH:i:s\Z');
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
			'ows_Description'=>'htmlspecialchars(HTML)',
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
				$array[] = $k.'="'.$v.'"';
			}
			$result[] = '<z:row '.implode(' ', $array).'/>';
		}

		return '<rs:data ItemCount="'.count($result).'">'.implode('', $result).'</rs:data>';
	}

	/**
	 * вызывается при изменении или удалении события
	 *
	 * @param SimpleXMLElement $obj
	 * @return string
	 * @throws Exception
	 */
	public function UpdateListItems(\SimpleXMLElement $obj)
	{
		if(empty($obj->Batch->Method)){
			throw new \Exception('empty event info');
		}

		foreach($obj->Batch->Method as $event){

			//$event = clone $event1;
			$arguments = (array)$event->Field;

			unset($arguments['@attributes']);

			if(empty($arguments)){
				throw new \Exception('event arguments is empty');
			}

			$method = (string)$event['Cmd'];

			if(empty($method)){
				throw new \Exception('Cmd not found');
			}

			// если выполняется обновление данных события
			if(count($arguments) > 1){
				$arguments = array($arguments);
			}

			if(!call_user_func_array(array($this, $method), $arguments)){
				throw new \Exception('Cmd not executed');
			}
		}
		// возвращаем информацию о удалении
		$xml = file_get_contents(__DIR__.'/templates/UpdateListItemsDelete.xml');

		return str_replace('$method', $method, $xml);

		if(count($obj->Batch->Method->Field) == 1){

			// возвращаем информацию о удалении
			return file_get_contents(__DIR__.'/templates/UpdateListItemsDelete.xml');
		}else{
			// обновляем события в б.д. и возвращаем данные обновленных событий
			return file_get_contents(__DIR__.'/templates/UpdateListItems.xml');
		}
	}

	/**
	 * обновляет данные события
	 *
	 * @param array $event - новые данные события
	 *
	 * @return bool - true в случае успеха
	 */
	private function Update(array $event)
	{

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
				$MetaInfovti_versionhistory) = $event;

		// обновляем информацию в базе данных

		return true;
	}

	/**
	 * удаляет событие
	 *
	 * @param int $eventId - ИД события
	 *
	 * @return bool - true в случае успеха
	 */
	private function Delete($eventId)
	{
		// удаляем событие в б.д. (фэйк-дропним событие)
		$a = 1;
		// обновляем токен в б.д.
		// $this->updateLastChangeToken(); - не нужно, т.к. дата изменения последнего события и является токеном

		return true;
	}
}