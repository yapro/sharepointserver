<?php
/**
 * совет: для тестирования используйте не доменное имя, а IP адрес
 */
include_once(__DIR__.'/OutlookCalendar.php');
$userId = 1000027;// ID пользователя на backend, календарь которого нужно синхронизировать с календарем Outlook
$class = new OutlookCalendar();
$link = $class->getStsSyncLink($userId);
echo '<a href="'.$link.'">stssync</a>';

// однонаправленная синхронизация: <a href="webcal://192.168.63.214/webcal.php">webcal</a>