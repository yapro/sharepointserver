<?php
/**
 * совет: для тестирования используйте не доменное имя, а IP адрес
 */
include_once(__DIR__.'/OutlookCalendar.php');
$userId = 1000027;
$class = new OutlookCalendar();
$link = $class->getStsSyncLink($userId);
echo '<a href="'.$link.'">stssync</a>';
exit;

/*trigger_error(print_r($_GET,1),E_USER_NOTICE);
echo ':'.time();
$params = array(
    'ver' => '1.0',
    'type' => 'calendar',
    'cmd' => 'add-folder',
    'base-url' => 'http%3A%2F%2Fspserver1',
    'list-url' => '%2FLists%2FEvts%2FAllItems%2Easpx',
    'guid' => '%7BAA7D945C%2DE5C3%2D4854%2DB631%2D10A98E711E2B%7D',
    'site-name' => 'Share|%7CPoint%20|%5BSite|%5D',
    'list-name' => 'Ev[00E900F1]ts'
);
$e='stssync://sts/?ver=1.0&type=calendar&cmd=
add-folder&base-url=http%3A%2F%2Fspserver1&list-url=
%2FLists%2FEvts%2FAllItems%2Easpx&guid=
%7BAA7D945C%2DE5C3%2D4854%2DB631%2D10A98E711E2B%7D&site-
name=Share|%7CPoint%20|%5BSite|%5D&list-name=Ev[00E900F1]ts';
*/
// Описание всех параметров http://msdn.microsoft.com/en-us/library/dd957390(v=office.12).aspx
$params = array(
  // Обязательный параметр. Версия приложения в формате x.y. Например, для Outlook значение этого параметра должно быть равно 1.0. Значения x и y должны состоять только из чисел. Значение x не может начинаться с нуля, а значение y должно быть либо нулем, либо другой последовательностью цифр, которая начинается не с нуля. Примечание: Значения x и y не могут состоять больше, чем из двух цифр каждое; иначе Outlook будет считать URL-адрес неправильным. Клиентское приложение стороннего производителя может использовать этот параметр, однако при формировании URL-адреса этот параметр должен иметь значение — иначе URL-адрес будет считаться неправильным.
    'ver' => '1.0',
    'type' => 'calendar',
    'cmd' => 'add-folder',
  // base-url - указывает узел SharePoint, например sharepoint/HR/Administration. Outlook автоматически добавляет к этому адресу URL ссылку на веб-службу списков (например, sharepoint/HR/Administration/_vti_bin/Lists.asmx). После этого путь для обмена данными с SharePoint свободен. Отдельные части адреса URL для StsSync поясняются в StsSync Structure Specification (Спецификация структуры StsSync) на веб-странице по адресу msdn.microsoft.com/cc313101
    'base-url' => urlencode('http://192.168.63.214/index.php'),// адрес страницы авторизации на сервере авторизации
    'list-url' => urlencode('/calendarPage'),// адрес календаря
  // Идентификатор GUID, который уникально идентифицирует удаленный список. Должен быть в формате "{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}", где каждый X представляет собой шестнадцатеричный символ.
    'guid' => str_replace('-', '%2D', rawurlencode('{AA7D945C-E5C3-4854-B631-10A98E711E2B}')),
    'site-name' => 'Lebnik',
    'list-name' => 'Lebedenko'
);
$paramsStr = array();
foreach ($params as $k => $v) {
  $paramsStr[] = $k.'='.$v;
}

$link = 'stssync://sts/?'.implode('&',$paramsStr);//jsOutlookUtils.Sync('calendar', '/bitrix/tools/ws_calendar', '/company/personal/user/3/calendar/', 'Nikolay Lebedenko', 'Nikolay Lebedenko', '{b1678794-21cb-078a-4d01-2fa1b254b8d1}', 443

echo '<a href="'.$link.'">stssync</a> - <a href="webcal://192.168.63.214/webcal.php">webcal</a>';
/*

"stssync://sts/?ver=1.1&type=calendar&cmd=add-folder&base-url=https%3A%2F%2Fleshas%2Ebitrix24%2Eru%3A443%2Fbitrix%2Ftools%2Fws%5Fcalendar&list-url=%2Fcompany%2Fpersonal%2Fuser%2F3%2Fcalendar%2F&guid=%7Bb1678794%2D21cb%2D078a%2D4d01%2D2fa1b254b8d1%7D&site-name=Nikolay%20Lebedenko&list-name=Nikolay%20Lebedenko"


?>
<script>
 * var jsOutlookUtils={encode:function(e){var t,o=e.length,i,l,s="",n=false,r="&\\[]|";for(t=0;t<o;t++){i=e.charAt(t);l=i.charCodeAt(0);if(n&&l<=127){s+="]";n=false}if(!n&&l>127){s+="[";n=true}if(r.indexOf(i)>=0)s+="|";if(l>=97&&l<=122||l>=65&&l<=90||l>=48&&l<=57)s+=i;else if(l<=15)s+="%0"+l.toString(16).toUpperCase();else if(l<=127)s+="%"+l.toString(16).toUpperCase();else if(l<=255)s+="00"+l.toString(16).toUpperCase();else if(l<=4095)s+="0"+l.toString(16).toUpperCase();else s+=l.toString(16).toUpperCase()}if(n)s+="]";return s},Sync:function(e,t,o,i,l,s,n,r){var a=500,c=20,f=window.location.host;if(!!n){f=f.replace(/:\d+/,"")+":"+n}t=window.location.protocol+"//"+f+t;s=s.replace(/{/g,"%7B").replace(/}/g,"%7D").replace(/-/g,"%2D");var d="stssync://sts/?ver=1.1"+"&type="+e+"&cmd=add-folder"+"&base-url="+jsOutlookUtils.encode(t)+"&list-url="+jsOutlookUtils.encode(o)+"&guid="+s;var g="&site-name="+jsOutlookUtils.encode(i)+"&list-name="+jsOutlookUtils.encode(l);if(d.length+g.length>a&&(i.length>c||l.length>c)){if(i.length>c)i=i.substring(0,c-1)+"...";if(l.length>c)l=l.substring(0,c-1)+"...";g="&site-name="+jsOutlookUtils.encode(i)+"&list-name="+jsOutlookUtils.encode(l)}d+=g;try{window.location.href=d}catch(p){}}};
 */