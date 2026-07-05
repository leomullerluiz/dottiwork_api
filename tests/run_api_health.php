<?php

$_ENV['APP_ENV'] = 'testing';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/health';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/../api/index.php';
