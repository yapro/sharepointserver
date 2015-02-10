<?php

class OutlookCalendar
{
	/**
	 * генерирует и отдает идентификатор календаря
	 *
	 * @param $userId - идентификатор бэкэнд-пользователя
	 * @return array
	 * @throws Exception
	 */
	public function generateListName($userId)
	{
		if(!is_numeric($userId)){
			throw new \Exception('wrong type userId');
		}
		/* Любой придуманный Вами идентификатор в формате: 8символа-4символа-4символа-4символа-12символов
		GUID (or UUID) is an acronym for 'Globally Unique Identifier' (or 'Universally Unique Identifier'). It is a
		128-bit integer number used to identify resources. The term GUID is generally used by developers working with
		Microsoft technologies, while UUID is used everywhere else. 128-bits is big enough and the generation algorithm is
		unique enough that if 1,000,000,000 GUIDs per second were generated for 1 year the probability of a duplicate
		would be only 50%. Or if every human on Earth generated 600,000,000 GUIDs there would only be a 50% probability
		of a duplicate. */
		return '00000000-0000-0000-0000-'.sprintf("%'012s",  $userId);//date('ymdHis');
		//$token = '1;1;'.$listName.';'.time().';1';
	}

	/**
	 * создает ссылку для подключения Outlook к SharePoint-серверу
	 *
	 * @param $userId - идентификатор пользователя из б.д.
	 * @return string - ссылка для подключения Outlook к SharePoint-серверу
	 * @throws Exception
	 */
	public function getStsSyncLink($userId)
	{
		$params = array(
			/* Обязательный параметр. Версия приложения в формате x.y. Например, для Outlook значение этого параметра
			должно быть равно 1.0. Значения x и y должны состоять только из чисел. Значение x не может начинаться с нуля,
			а значение y должно быть либо нулем, либо другой последовательностью цифр, которая начинается не с нуля.
			Примечание: Значения x и y не могут состоять больше, чем из двух цифр каждое; иначе Outlook будет считать
			URL-адрес неправильным. Клиентское приложение стороннего производителя может использовать этот параметр, однако
			при формировании URL-адреса этот параметр должен иметь значение — иначе URL-адрес будет считаться неправильным.
			*/
			'ver' => '1.0',
			'type' => 'calendar',
			'cmd' => 'add-folder',
			/* base-url - указывает узел SharePoint. Outlook автоматически добавляет к этому адресу URI адрес списков
			(например, http://site.ru/index.php/_vti_bin/lists.asmx). Отдельные части адреса URL для StsSync поясняются в
			Спецификации структуры  StsSync) по адресу http://msdn.microsoft.com/cc313101
			При клики по кнопке Открыть в браузере (в Outlook) будет открыта страница base-url + /DispForm.aspx?ID=$ID */
			'base-url' => urlencode('http://'.$_SERVER['HTTP_HOST'].'/index.php'),// он же является адресом авторизации
			/* адрес календаря, возможно не обязателен т.к. запрашивается адрес base-url + /_vti_bin/lists.asmx */
			'list-url' => urlencode('/calendarPage'),
			/* Идентификатор GUID, который уникально идентифицирует удаленный список. Должен быть в формате
			{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX} где каждый X представляет собой шестнадцатеричный символ */
			'guid' => str_replace( '-', '%2D', rawurlencode('{'.$this->generateListName($userId).'}' ) ),
			'site-name' => 'mySiteName',
			'list-name' => 'myListName'
		);
		$paramsStr = array();
		foreach ( $params as $k => $v ) {
			$paramsStr[] = $k . '=' . $v;
		}

		return 'stssync://sts/?'.implode('&', $paramsStr);
	}
}