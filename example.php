<?php

require_once('smSourcemap.class.php');

$commonPath = '/path/to/web/root/js';
$commonURL = 'https://www.example.com/js';

$merged = new smSourcemap();
$merged->addUrlMapping($commonPath,$commonURL);
$merged->includeOriginals = false;

$map1 = new smSourcemap();
$map1->addUrlMapping($commonPath,$commonURL);
$map1->loadOriginal($commonPath.'/script1.js');
$map1->loadMinified($commonPath.'/script1.min.js');
$map1->loadSourcemap($commonPath.'/script1.min.js.map');
$merged->appendSourcemap($map1);

$map2 = new smSourcemap();
$map2->addUrlMapping($commonPath,$commonURL);
$map2->loadOriginal($commonPath.'/script2.js');
$map2->loadMinified($commonPath.'/script2.min.js');
$map2->loadSourcemap($commonPath.'/script2.min.js.map');
$merged->appendSourcemap($map2);

$merged->sourceMappingUrl = $commonURL.'/merged.min.js.map';
$merged->saveMinified($commonPath.'/merged.min.js');
$merged->saveSourcemap($commonPath.'/merged.min.js.map');
