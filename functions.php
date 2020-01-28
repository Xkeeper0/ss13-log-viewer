<?php


	function get_remote_log_url($server, $url = "") {
		return "http://". GOONLOG_AUTH ."@goon{$server}.goonhub.com/logs/full/". $url;
	}

	function get_log($server, $log, $redownload = false) {
		if (!$server || !$log) {
			throw new Exception("can't get a log if you dont say what log to get, chief");
		}

		$localFile = "logs/$server/$log";
		if (!$redownload && file_exists($localFile)) {
			return file_get_contents($localFile);
		}

		$html = file_get_contents(get_remote_log_url($server, $log));
		if (!$html) {
			throw new Exception("Failed to get log HTML. Uh oh!");
		}

		file_put_contents($localFile, $html);
		return $html;


	}


	function get_remote_log_list($server) {
		$html = file_get_contents(get_remote_log_url($server));
		if (!$html) {
			throw new Exception("Failed to get log HTML");
		}

		$matches = [];
		$matched = preg_match_all('#<a href="([0-9-]+\.html)">#im', $html, $matches);

		if (!$matched) {
			throw new Exception("No log links found (??)");
		}
		//var_dump($html, $matched, $matches);

		$logs = [];
		foreach ($matches[1] as $matchtext) {
			$logs[]	= $matchtext;
		}

		rsort($logs);
		return $logs;
	}


	function get_logs($force = false) {
		global $config;

		$logs	= [];
		foreach ($config['servers'] as $serverId) {
			if ($force || (!file_exists("logs/$serverId.txt") || (time() - filemtime("logs/$serverId.txt")) > 300)) {
				$logs[$serverId] = get_remote_log_list($serverId);
				file_put_contents("logs/$serverId.txt", implode("\n", $logs[$serverId]));
			} else {
				$logs[$serverId] = explode("\n", file_get_contents("logs/$serverId.txt"));
			}
			if (!file_exists("logs/$serverId/")) {
				mkdir("logs/$serverId");
			}
		}

		return $logs;

	}


	function list_available_logs($logs) {
		global $config;

		$thead	= "";
		$tbody	= "";

		foreach ($config['servers'] as $serverId) {
			$thead .= "<th style='width: 50%;'>Server $serverId</th>";
			$tbody .= "<td valign='top'><ul>";
			$ourLogs = array_flip(scandir("logs/$serverId"));

			foreach ($logs[$serverId] as $log) {
				$islocal	= 0;
				if (isset($ourLogs[$log])) {
					unset($ourLogs[$log]);
					$islocal = 1;
				}
				$tbody .= "<li><span>". log_date_relative($log) ."</span><a href='?server=$serverId&amp;view=$log'>$log</a>". ($islocal ? " (saved)" : "") ."</li>\n";
			}

			foreach ($ourLogs as $log => $_) {
				if ($log !== "." && $log !== "..") {
					$tbody .= "<li><a href='?view=$log'>$log</a> (not on other server)</li>\n";
				}
			}
			$tbody .= "</ul></td>";
		}

		return <<<E
<h1>goon log viewer v1.1</h1>
<style type="text/css">
ol { margin: 0; list-style: none; }
li { background: #eee; margin: 0; list-style: none; margin-bottom: 6px; }
li:hover { background: #ddd; }
li > span {
	display: inline-block;
	text-align: right;
	width: 13em;
	margin-right: 1em;
}
</style>
<table style='width: 80%; max-width: 1200px; margin: auto;'>
	<tr>$thead</tr>
	<tr>$tbody</tr>
</table>
E;

	}



	function log_date_relative($filename) {
		$x = explode("-", str_replace(".html", "", $filename));
		$y = sprintf("%04d-%02d-%02d %02d:%02d:00 EST", $x[0], $x[1], $x[2], $x[3], $x[4]);
		$s = time() - strtotime($y);

		return timeunits2($s);
		//return time() - strtotime($y);
		//return (time() - mktime($x[3], $x[4], 0, $x[1], $x[2], $x[0]));
	}

	function timeunits2($sec) {
		if (floor($sec) === 0) { return "0 sec."; }
		$d = floor($sec/86400);
		$h = floor($sec/3600)%24;
		$m = floor($sec/60)%60;
		$str =	($d ? "{$d}d " : '') .
				($sec > 3600 ? sprintf("%02dh ", $h) :'' ) .
				sprintf("%02dm", $m);
		return trim($str);
	}




	function pretty_log($line, $time, $type, $msg) {
		$typeL	= strtolower($type);

		if ($typeL === "say" && strpos($msg, ": EMOTE:") !== false) {
			$typeL = "emote";
		}

		if ($typeL === "mentor_help") {
			$typeL = "mentor";
		}

		if ($typeL === "admin_help") {
			$typeL = "adminhelp";
		}

		$msg	= regex_bullshit($msg);

		if ($typeL === "say" || $typeL === "emote") {
			$msg	= remove_say_tags($typeL, $msg);
		}

		$oldmsg	= $msg;
		//$msgdom = DOMDocument::loadHTML($msg, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$msgdom = DOMDocument::loadHTML('<span class="message">'. $msg .'</span>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$msg = $msgdom->saveHTML();

		print <<<E
	<p class="log-$typeL">
		<a class="ts" href="#l$line" name="l$line">$time</a>
		<span class="type">$typeL</span>
		$msg
	</p>

E;
	}

	function regex_bullshit($msg) {

		// remove player options links
		$msg	= preg_replace("/\(?<a href='\\?src=%admin_ref%;action=adminplayeropts;targetckey=[^']+' title='Player Options'>([^<]+)<\/a>\)*(?: ?:)?/i", '<span class="ckey">\1</span>', $msg);

		// make traitor tag look better
		$msg	= str_replace("[<span class='traitorTag'>T</span>]", "<span class='antag'>T</span>", $msg);

		// change coordinates
		$msg	= preg_replace("/\(<a href='\\?src=%admin_ref%;action=jumptocoords;target=[^']+' title='Jump to Coords'>([^<]+)<\/a> in ([^)]+)\)/i", '<span class="location">\2 <span>(\1)</span></span>', $msg);

		// beaker contents
		$msg	= preg_replace('#\(<b>Contents:</b> <i>(.*)<\/i>. <b>Temp:</b> <i>([0-9. K]+)</i>\)#i', '<span class="reagents">\1, \2</span>', $msg);
		$msg	= str_replace('(<b>Contents:</b> <i>nothing</i>)', '<span class="reagents empty">&mdash;</span>', $msg);

		// canisters
		$msg	= preg_replace_callback('#\(<b>Pressure:</b> <i>([0-9a-z. ]+)<\/i>. <b>Temp:</b> <i>([0-9&;a-z-]+)</i>, <b>Contents:</b> <i>(.*)<\/i>\)#i', 'do_canister_atmos', $msg);

		// deaths
		$msg	= preg_replace_callback('#\(<b>Damage:</b> <i>([0-9., ]+)<\/i>\)#i', 'do_damage_readout', $msg);

		$msg	= preg_replace_callback('#(writes on a piece of paper:)(.*)#im', 'fix_paper', $msg);

		return $msg;
	}

	function remove_say_tags($type, $msg) {

		if ($type === "say" && strpos($msg, "SAY: ") !== false) {
			$msg = str_replace("SAY: ", " says, \"", $msg) .'"';
		} elseif ($type === "emote") {
			$msg = str_replace("EMOTE: ", "", $msg);

		}

		return $msg;
	}


	function do_canister_atmos($matches) {

		$a = [];
		$m = preg_match('#([0-9.]+)% N2 / ([0-9.]+)% O2 / ([0-9.]+)% CO2 / ([0-9.]+)% PL#', $matches[3], $a);

		$atm	= "<span class='atmos'>";
		$gas	= ['n2', 'o2', 'co2', 'pl'];
		$m		= 0;
		array_shift($a);
		foreach ($gas as $n => $poo) {
			if ($a[$n] === "0") {
				continue;
			}
			$temp	= $a[$n];
			$temp2	= ceil($temp);
			$atm	.= "<span style='width: {$temp2}px; left: {$m}px;' class='atmos-$poo' title='$temp% $poo'></span>";
			$m += $temp2 + 1;

		}
		$atm	.= "</span>\n";
		return "$atm <span class='reagents'>$matches[1], $matches[2]</span>";
		//var_dump($a);
		//die();
	}



	function do_damage_readout($matches) {


		$a = [];
		$m = preg_match('#([0-9.]+), ([0-9.]+), ([0-9.]+), ([0-9.]+), ([0-9.]+)#', $matches[1], $a);

		/*
		print "<pre>";
		var_dump($matches, $m, $a);
		die();
		*/

		$out	= "<span class='damage'>";
		$dam	= ['brain', 'oxy', 'tox', 'burn', 'brute'];
		$m		= 0;
		array_shift($a);
		foreach ($dam as $n => $poo) {

			$temp	= $a[$n];
			$temp2	= ceil($temp);
			$out	.= " <span class='damage-$poo' title='$poo: $temp'>$temp2</span> ";
			$m += $temp2 + 1;

		}
		$out	.= "</span>\n";
		return $out;

	}

	function fix_paper($matches) {
		// writes on a piece of paper
		return $matches[1] ."<code class='paper'>". htmlspecialchars(str_ireplace("<br>", "\n", $matches[2])) ."</code>";

	}
