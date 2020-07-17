<?php

	// Secret sauce that reformats the logs into something more readable
	// Sorry this is awful and not commented; this was something I wrote
	// largely in nano (aaag) as-needed rather than like, with actual thinking
	// In the future: it can be improved! Wowzers!


	function pretty_log($line, $time, $type, $msg) {
		static $firstTimestamp	= null;

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

		if (class_exists("DOMDocument")) {
			$oldmsg	= $msg;
			//$msgdom = DOMDocument::loadHTML($msg, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$msgdom	= new DOMDocument();
			$msgdom->loadHTML('<span class="message">'. $msg .'</span>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$msg = $msgdom->saveHTML();
		} else {
			$msg	= '<span class="message">'. $msg .'</span>';
		}

		$timeReal	= $time;
		$timeTSS	= explode(".", $timeReal);
		$timeTS		= strtotime($timeReal) + floatval("0." . $timeTSS[1]);
		if (!$firstTimestamp) {
			$firstTimestamp = $timeTS;
		}

		$timeRel	= relative_time($timeTS - $firstTimestamp);

		print <<<E
	<p class="log-$typeL">
		<a class="ts" href="#l$line" name="l$line" title="$timeReal (+$timeRel)"><span class='realtime'>$time</span><span class='reltime'>$timeRel</span></a>
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

	// Remove kind of redundant "SAY:" and "EMOTE:" messages (since we try to display them like they are in-game...-ish)
	function remove_say_tags($type, $msg) {

		if ($type === "say" && strpos($msg, "SAY: ") !== false) {
			$msg = str_replace("SAY: ", " says, \"", $msg) .'"';
		} elseif ($type === "emote") {
			$msg = str_replace("EMOTE: ", "", $msg);

		}

		return $msg;
	}


	// Provides a fancy hover-over-able bar for canister atmos. Very janky and based on how the game outputs its log message.
	// Could be made to be more better but right now: isn't!
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


	// Displays damage color-coordinated with type for readability
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

	// Tries to make paper not ruin the entire page, but not terribly effectively
	// future: load with domdocument, display on hover? maybe? idk.
	function fix_paper($matches) {
		// writes on a piece of paper
		return $matches[1] ."<code class='paper'>". htmlspecialchars(str_ireplace("<br>", "\n", $matches[2])) ."</code>";

	}
