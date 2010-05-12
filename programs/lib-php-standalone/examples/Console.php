<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.inc.php');


// Firebug / Web Inspector
$console = INSIGHT::to("console");
$console->label("Log label")->log("Hello World as log message");
$console->label("Info label")->info("Hello World as info message");


// Developer Companion Request Inspector
$inspector = INSIGHT::to("request");

$point = $inspector->timeline("Request");
$point->renderer('http:request:Method')->set('Method', $_SERVER["REQUEST_METHOD"]);

$console = $inspector->console("Request Variables");
$console->label('$_POST')->renderer('php:external-variables:$_POST')->log($_POST);
$console->label('$_GET')->renderer('php:external-variables:$_GET')->log($_GET);

$point = $inspector->timeline("Response");
$point->renderer('http:response:Status')->set('Status', 200);

?>

<p>Open firebug and click on the <i>Console</i> panel.</p>
