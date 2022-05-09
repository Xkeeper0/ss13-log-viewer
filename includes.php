<?php

	define('ZLOG_ENABLE_COMPRESSION', function_exists('gzuncompress') && function_exists('gzcompress'));

	require_once("credentials.php");
	require_once("functions.php");
	require_once("functions-logs.php");

