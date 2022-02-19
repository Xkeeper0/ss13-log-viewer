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
		
		if ($typeL === "tgui" && strpos($msg, "Using: /obj/item/paper") !== false && strpos($msg, "Action: save") !== false) {
			$parts = explode("<br>", $msg, 3);
			if (count($parts) > 2) {
				$json = json_decode(explode("Action: save ", $parts[2])[1]);
				$msg = $parts[0] . "<br>" . $parts[1] . "<br>" . "Action: save <br><div style='border:1px black solid;'>" . $json->text . "</div>";
			}
		}
		else if ($typeL === "tgui") {
			$parts = explode("<br>", $msg, 2);
			if (count($parts) > 1)
				$msg = $parts[0] . "<br><code>" . str_replace("\n", "<br>", htmlentities(str_replace("<br>", "\n", $parts[1])))  . "</code>";
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
			@$msgdom->loadHTML('<span class="message">'. $msg .'</span>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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
		$msg	= preg_replace("/<a href=(?:'|\")\\?src=%admin_ref%;action=jumptocoords;target=[^'\"]+(?:'|\") title='Jump to Coords'>([^<]+)<\/a>/i", '<span class="location"><span>(\1)</span></span>', $msg);

		// beaker contents
		$msg	= preg_replace('#\(<b>Contents:</b> <i>(.*)<\/i>. <b>Temp:</b> <i>([0-9. K]+)</i>\)#i', '<span class="reagents">\1, \2</span>', $msg);
		$msg	= str_replace('(<b>Contents:</b> <i>nothing</i>)', '<span class="reagents empty">&mdash;</span>', $msg);

		// canisters
		$msg	= preg_replace_callback('#\(<b>Pressure:</b> <i>([0-9a-z. ]+)<\/i>. <b>Temp:</b> <i>([0-9&;a-z-]+)</i>, <b>Contents:</b> <i>(.*)<\/i>#i', 'do_canister_atmos', $msg);

		// canvases
		$msg	= preg_replace_callback('#canvas\{\[(0x[0-9a-f]+)\], ([0-9-]+), ([0-9-]+), ([^}]+)\}#i', 'do_canvas', $msg);


		// deaths
		$msg	= preg_replace_callback('#\(<b>Damage:</b> <i>([0-9., ]+)<\/i>\)#i', 'do_damage_readout', $msg);

		$msg	= preg_replace_callback('#(writes on a piece of paper:)(.*)#im', 'fix_paper', $msg);

		return $msg;
	}

	// Remove kind of redundant "SAY:" and "EMOTE:" messages (since we try to display them like they are in-game...-ish)
	function remove_say_tags($type, $msg) {

		if ($type === "say" && strpos($msg, "SAY: ") !== false) {
			//$msg	= str_replace("SAY: ", " says, \"", $msg) .'"';
			$msg	= preg_replace("/SAY: (.*) (<span class=\"location)/i", 'says, "\1" \2', $msg);

		} elseif ($type === "emote") {
			$msg = str_replace("EMOTE: ", "", $msg);

		}

		return $msg;
	}

	// Provides a fancy hover-over-able bar for canister atmos. Very janky and based on how the game outputs its log message.
	// Could be made to be more better but right now: isn't!
	function do_canvas($matches) {
		static $canvases = [];
		static $colors = [			// BYOND HTML colors https://www.byond.com/docs/ref/#/{{appendix}}/html-colors
			'black'		=> "#000000",
			'silver'	=> "#C0C0C0",
			'gray'		=> "#808080",
			'grey'		=> "#808080",
			'white'		=> "#FFFFFF",
			'maroon'	=> "#800000",
			'red'		=> "#FF0000",
			'purple'	=> "#800080",
			'fuchsia'	=> "#FF00FF",
			'magenta'	=> "#FF00FF",
			'green'		=> "#00C000",
			'lime'		=> "#00FF00",
			'olive'		=> "#808000",
			'gold'		=> "#808000",
			'yellow'	=> "#FFFF00",
			'navy'		=> "#000080",
			'blue'		=> "#0000FF",
			'teal'		=> "#008080",
			'aqua'		=> "#00FFFF",
			'cyan'		=> "#00FFFF",
			];

		$canvas		= "c". $matches[1];
		if (!isset($canvases[$canvas])) {
			$canvases[$canvas]	= imagecreatetruecolor(32, 32);
			imagefilledrectangle($canvases[$canvas], 0, 0, 32, 32, 0xFFFFFF);
		}

		if (isset($colors[$matches[4]])) {
			// covertly pretend it was html all along
			$matches[4]	= $colors[$matches[4]];
		}
		if ($matches[4][0] === "#") {
			$color	= imagecolorallocate($canvases[$canvas],
			hexdec(substr($matches[4], 1, 2)),
			hexdec(substr($matches[4], 3, 2)),
			hexdec(substr($matches[4], 5, 2)));
		} else {
			$color = 0xFF0000;
		}

		if ($matches[2] == -1 && $matches[3] == -1) {
			imagefilledrectangle($canvases[$canvas], 0, 0, 32, 32, $color);
		} else {
			imagesetpixel($canvases[$canvas], $matches[2], 31 - $matches[3], $color);
		}
		ob_start();
		imagepng($canvases[$canvas]);
		return "<img src=\"data:image/png;base64, ". base64_encode(ob_get_clean()) ."\" title=\"$matches[1]\" style=\"vertical-align: middle;\"> $matches[1]";
	}


	// Provides a fancy hover-over-able bar for canister atmos. Very janky and based on how the game outputs its log message.
	// Could be made to be more better but right now: isn't!
	function do_canister_atmos($matches) {

		$a		= [];
		$m		= preg_match('#O2: ([0-9.]+)%, N2: ([0-9.]+)%, CO2: ([0-9.]+)%, Plasma: ([0-9.]+)%, Farts: ([0-9.]+)%#', $matches[3], $a);
		$gas	= ['o2', 'n2', 'co2', 'pl', 'farts'];
		if (!$m) {
			// This is the old atmos readout
			$m		= preg_match('#([0-9.]+)% N2 / ([0-9.]+)% O2 / ([0-9.]+)% CO2 / ([0-9.]+)% PL#', $matches[3], $a);
			$gas	= ['n2', 'o2', 'co2', 'pl'];
		}

		$atm	= "<span class='atmos'>";
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
		return "$atm <span class='reagents' title='$matches[3]'>$matches[1], $matches[2]</span>";
		//var_dump($a);
		//die();
	}


	// Displays damage color-coordinated with type for readability
	function do_damage_readout($matches) {


		$a = [];
		$m = preg_match('#((?:[0-9.]+)?), ([0-9.]+), ([0-9.]+), ([0-9.]+), ([0-9.]+)#', $matches[1], $a);

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
			$temp2	= $temp == "" ? "null" : ceil($temp);
			$out	.= " <span class='damage-$poo' title='$poo: $temp'>$temp2</span> ";
			if (is_numeric($temp2))
				$m += $temp2 + 1;

		}
		$out	.= "</span> ";
		return $out;

	}

	// Tries to make paper not ruin the entire page, but not terribly effectively
	// future: load with domdocument, display on hover? maybe? idk.
	function fix_paper($matches) {
		// writes on a piece of paper
		return $matches[1] ."<code class='paper'>". htmlspecialchars(str_ireplace("<br>", "\n", $matches[2])) ."</code>";

	}
