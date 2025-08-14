<?php

define('ABSPATH', str_replace('\\', '/', dirname(__FILE__, 2)).'/');



$tempPos = strpos($_SERVER['PHP_SELF'], basename($_SERVER['SCRIPT_FILENAME']));
$tempPath0 = substr($_SERVER['PHP_SELF'], 0, $tempPos);
$tempPath1 = ($tempPath0 > 1) ? str_replace($tempPath0, '', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];
$tempPos = strpos($tempPath1, '?');
if ($tempPos > 1) {
    $tempPath1 = substr($tempPath1, 0, $tempPos);
}

$protocol = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'] || 443 == $_SERVER['SERVER_PORT']) ? 'https://' : 'http://';
$urladdr = $_SERVER['HTTP_HOST'].$tempPath0;
define('BASEHREF', $protocol.$urladdr);
define('REQUEST_URI', $tempPath1);

unset($tempPath0,$tempPath1, $tempPos, $urladdr);


require(ABSPATH . 'app/config/bootstrap.php');