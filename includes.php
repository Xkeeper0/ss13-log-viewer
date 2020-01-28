<?php

	require_once("credentials.php");
	require_once("functions.php");

	function v(&$v) {
		return isset($v) ? $v : null;
	}
