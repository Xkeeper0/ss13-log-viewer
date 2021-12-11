<?php



	// PHP 5 shim function to simulate null-coalescing "??" operator
	// PHP 7: $v ?? $default
	// This:  v($v, $default)
	// PHP 5: (isset($v) && $v !== null) ? $v : $default
	function v(&$v, $default = null) {
		return isset($v) ? $v : $default;
	}


	/**
	 * get_remote_log_url
	 * Gets a remote log ($url) from $server, or the index if no $url
	 */
	function get_remote_log_url($server, $url = "") {
		global $config;

		$auth	= v($config['servers'][$server]['auth']);

		if ($auth) {

			if (!v($config['auth'][$auth])) {
				throw new \Exception("Server '$server' requires auth '$auth', but '$auth' doesn't exist");
			}

			$baseurl	= str_replace('<auth>', $config['auth'][$auth], $config['servers'][$server]['url']);

		} else {
			$baseurl	= $config[$server]['url'];
		}

		return $baseurl . "logs/full/". $url;
	}


	/**
	 * get_log($server, $log, $redownload)
	 * Returns the $log from $server, redownloading if $redownload is set or if it hasn't been downloaded yet
	 */
	function get_log($server, $log, $redownload = false) {
		global $config;

		if (!$server || !$log) {
			throw new \Exception("Can't get a log from a server without both a log and a server. How did you get here?");
		}

		$localFile = "logs/$server/$log";
		if (!$redownload && file_exists($localFile)) {
			return file_get_contents($localFile);
		}

		$html = @file_get_contents(get_remote_log_url($server, $log));
		if ($html === false) {
			throw new \Exception("Failed to get log '$log' from server '$server'. Uh oh!");
		}

		file_put_contents($localFile, $html);
		return $html;


	}


	/**
	 * get_remote_log_list($server)
	 * Gets and parses the directory listing for a given log server.
	 * Returns an array of available logs
	 */
	function get_remote_log_list($server) {

		$html = @file_get_contents(get_remote_log_url($server));
		if ($html === false) {
			throw new \Exception("Failed to get dictory list from '$server'");
		}

		$matches = [];
		$matched = preg_match_all('#<a href="([0-9-]+\.html)">#im', $html, $matches);

		if (!$matched) {
			throw new \Exception("No log links found in '$server' directory list (???). Is your configuration correct?");
		}

		$logs = [];
		foreach ($matches[1] as $matchtext) {
			$logs[]	= $matchtext;
		}

		rsort($logs);
		return $logs;
	}


	/**
	 * get_logs($force)
	 * Gets a list of available logs for each server
	 * Saves the list on-disk and uses it if it was fetched less than 5 minutes ago,
	 * otherwise (or with $force) does a fresh update
	 */
	function get_logs($force = false) {
		global $config;

		$logs	= [];
		foreach ($config['servers'] as $serverId => $server) {
			try {

				if ($force || (!file_exists("logs/$serverId.txt") || (time() - filemtime("logs/$serverId.txt")) > 300)) {
					$logs[$serverId] = get_remote_log_list($serverId);
					file_put_contents("logs/$serverId.txt", implode("\n", $logs[$serverId]));
				} else {
					$logs[$serverId] = explode("\n", file_get_contents("logs/$serverId.txt"));
				}
				if (!file_exists("logs/$serverId/")) {
					mkdir("logs/$serverId");
				}

			} catch (\Exception $e) {
				// oh well
				print "Error: ". $e->getMessage() . "<br>";
			}

		}

		return $logs;

	}


	/**
	 * list_available_logs($logs)
	 * Prints out a table with logs and links to those logs! Wow!
	 * Will also try to show what logs we have that the server doesn't (or vice-versa?)
	 * idk i'm writing this comment months later, sorry
	 */
	function list_available_logs($logs) {
		global $config;

		$thead	= "";
		$tbody	= "";


		$min	= date("Y-m-d", time() - 86400 * 14);
		if (isset($_GET['min'])) {
			$min	= $_GET['min'];
		}

		$max	= null;;
		if (isset($_GET['max'])) {
			$max	= $_GET['max'];
		}

		foreach ($config['servers'] as $serverId => $server) {
			if (!file_exists("logs/$serverId")) {
				// No logs here, don't bother trying to scan it
				continue;
			}
			$thead .= "<th>$serverId</th>";
			$tbody .= "<td valign='top'><table><tbody>";
			$ourLogs = array_flip(scandir("logs/$serverId"));
			$prevDate = null;
			foreach ($logs[$serverId] as $log) {
				$islocal	= 0;
				if (isset($ourLogs[$log])) {
					unset($ourLogs[$log]);
					$islocal = 1;
				}

				$logDisp	= preg_replace('/([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})-([0-9]{2})\.html/', '$1-$2-$3 $4:$5', $log);
				$shortDate	= substr($logDisp, 0, 10);
				if (!((!$min || $shortDate >= $min) && (!$max || $shortDate <= $max))) {
					continue;
				}
				if ($prevDate && $shortDate !== $prevDate) {
					$tbody .= "<tr><td colspan=2>&nbsp;</td></tr>\n";
				}
				$prevDate	= $shortDate;

				$tbody .= "<tr><td><a href='?server=$serverId&amp;view=$log'>$logDisp</a>". ($islocal ? "*" : "") ."</td><td>". log_date_relative($log) ."</td></tr>\n";
			}

			foreach ($ourLogs as $log => $_) {
				if ($log !== "." && $log !== "..") {
					$logDisp	= preg_replace('/([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})-([0-9]{2})\.html/', '$1-$2-$3 $4:$5', $log);
					if (!((!$min || $logDisp >= $min) && (!$max || $logDisp <= $max))) {
						continue;
					}
					$tbody .= "<tr><td><a href='?server=$serverId&amp;view=$log'>$logDisp</a></td><td>not on remote</td></tr>\n";
				}
			}
			$tbody .= "</tbody></table></td>";
		}

		return <<<E
<style type="text/css">
* { font-family: Verdana; }
a { text-decoration: none; }

table table tr:hover { background: #ddd; }
table table td {
	white-space: nowrap;
}
table table {
	margin: 0 1em;
}
table table td + td {
	text-align: right;
	white-space: pre;
	font-family: monospace;
}
</style>
<div style="text-align: center;">
<h1>goon log viewer v1.2</h1>
<a href="https://github.com/Xkeeper0/ss13-log-viewer">github</a> for issues, support, etc.
<br>
<br><form method="get">
<label>start: <input type="date" name="min" value="$min"></label> &mdash;
<label>end: <input type="date" name="max" value="$max"></label> &mdash;
<input type="submit" value="show"></form></div>
</div>

<table style='width: 50%; margin: auto;'>
<tr>$thead</tr>
<tr>$tbody</tr>
</table>
E;

	}


	/**
	 * log_date_relative($filename)
	 * Translates a filename like into a relative timestamp.
	 * This is gross and assumes the server is EST! Oh well!
	 */
	function log_date_relative($filename) {
		$x = explode("-", str_replace(".html", "", $filename));
		$y = sprintf("%04d-%02d-%02d %02d:%02d:00 UTC+0", $x[0], $x[1], $x[2], $x[3], $x[4]);
		$s = time() - strtotime($y);

		return timeunits2($s);
	}


	/**
	 * timeunits2($sec)
	 * Gives a human-readable length of time based on $sec
	 * Totally not stolen from 2001-era Acmlmboard code. I swear.
	 */
	function timeunits2($sec) {
		if ($sec < 0) {
			// what
			$sec	= abs($sec);
		}
		if (floor($sec) === 0) { return "0 sec."; }
		$d = floor($sec/86400);
		$h = floor($sec/3600)%24;
		$m = floor($sec/60)%60;
		$str =	($d ? "{$d}d " : '') .
				($sec > 3600 ? sprintf("%2dh ", $h) :'' ) .
				sprintf("%2dm", $m);
		return trim($str);
	}


	/**
	 * relative_time
	 * Give a relative timestamp (ish)
	 */
	function relative_time($sec) {
		if ($sec < 0) {
			// what
			$sec	= abs($sec);
		}
		$h = floor($sec/3600);
		$m = floor($sec/60)%60;
		$sms = $sec - ($h * 3600 + $m * 60);

		return sprintf("%d:%02d:%06.3f", $h, $m, $sms);

	}

