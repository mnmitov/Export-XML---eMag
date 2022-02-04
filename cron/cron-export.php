<?php

$url = 'https://***************@services.digit.bg/xml_export_emag/export.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_USERPWD, "********************");
$cron = curl_exec($ch);
curl_close($ch);

echo $cron;
