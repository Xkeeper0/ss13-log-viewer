<?php

	require_once("includes.php");

	$server		= v($_GET['server']);
	$log		= v($_GET['view']);

	// -----------------------------------------------------------------------
	// If no log is specified, get a list of logs and then exit:

	// No logs? Get logs.
	if (!$log) {
		$logs	= get_logs();
		print list_available_logs($logs);
		die();
	}

	// -----------------------------------------------------------------------
	// If given a log to view, on the other hand:
	$ms	= preg_match('#^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}\.html$#', $log);
	if (!$ms) {
		die("You are doing something weird.");

	} elseif (!isset($config['servers'][$server])) {
		die("That... isn't a server we have listed. What?");

	}

	if (isset($_GET['redownload'])) {
		$file	= get_log($server, $log, true);
		if ($file) {
			header("Location: ?server=". $server ."&view=". $log ."");
			die();
		}

	}

	$file	= get_log($server, $log);
	if (!$file) {
		die("Error opening log. Sorry. It broke. Oh well.");
	}

	$title = $server . " - " . $log;
	require("html/log_header.php");

	// This is arbitrarily chosen as a "round ended" semaphore, but in theory it could be anything.
	// I just chose this because I wrote it and it always print(s/ed) when the round 'ends'.
	if (strpos($file, "Zamujasa/CREWCREDITS") === false) {
		echo "<div style='font-size: 200%; margin-right:300px;'>It looks like this log might be incomplete. This might be because the round wasn't over when it was downloaded (or it might still be going on, who knows). <a href='?redownload=1&amp;server=". $_GET['server'] ."&amp;view=". $_GET['view'] ."'>Redownload?</a></div>";
	}

?>

	<div id="controls">
		<button id="filter-button" disabled>Show/Hide Options</button>
		<div id="options">
			<br>
			<div id="filters" class="faded">
				<label class='opt'><input type='checkbox' id="filter-all" checked disabled> ALL</label>

<?php

	$types	= [
		'station',
		'admin',
		'debug',
		'diary',
		'adminhelp',
		'mentor',
		'ooc',
		'say',
		'whisper',
		'emote',
		'pdamsg',
		'combat',
		'vehicle',
		'pathology',
		'bombing',
		'telepathy',
		'tgui',
		'computers',
	];

	foreach ($types as $type) {
		print "<label class='opt opt-$type'><input type='checkbox' name='$type' ". ($type !== "tgui" ? "checked" : "") ." disabled> $type</label>\n";
	}


?>
		</div>
		<br><label id="show-ckeys" class="faded"><input type="checkbox" checked disabled> Show ckeys</label>
		<br><label id="show-reltime" class="faded"><input type="checkbox" disabled> Relative timestamps</label>
		<br>
		<br>
		<strong>Filter by text:</strong><br>
		<span style="font-size: 70%;">one term per line<br><strong>!term</strong>: match must include<br><strong>-term</strong>: ignore<br><strong>term</strong>: must match <em>any</em></span><br>
		<form method="get">
			<input type="hidden" name="server" value="<?php print $_GET['server']; ?>">
			<input type="hidden" name="view" value="<?php print $_GET['view']; ?>">
			<textarea name="search-string" style="display: block; height: 7em;" placeholder="ckey1
ckey2
+with the
-Shitty Bill"><?php if (isset($_GET['search-string'])) echo htmlspecialchars($_GET['search-string']); ?></textarea>
			<input type="submit" value="Filter">
		</form>
		<br>
		</div>
		<a href='?' style="display: block; text-align: center;">&larr; back to list</a>
	</div>
	<div id="log" class="show-realtime hide-tgui">
<?php



	//$file	= file_get_contents($file);

	$lines	= explode("\n", $file);


	$search	= [];
	if (isset($_GET['search-string'])) {
		$s		= trim(str_replace("\r", "", $_GET['search-string']));
		$sterm	= explode("\n", $s);
		foreach ($sterm as $term)
			if ($term = trim($term)) $search[] = $term;
	}


	$n		= 0;
	$fuck	= 2;
	$print	= true;
	foreach ($lines as $line) {
		$n++;

		if (!empty($search)) {
			$has_required	= 0;
			$matched_any	= 0;
			$print = false;
			foreach ($search as $term) {
				if ($term[0] === "!") {
					$has_required = 1;
					if (stripos($line, substr($term, 1)) === false) {
						$print	= false;
						break;
					}

					// Continue to see if another term matches somewhere
					continue;

				} elseif ($term[0] === "-" && stripos($line, substr($term, 1)) !== false) {
					// Has ignored term; ignore line
					$print = false;
					break;
				}

				if ($term[0] === "+" || $term[0] === "-") {
					$term	= substr($term, 1);
					$print	= ($print === null ? true : false);
				}

				if (stripos($line, $term) !== false) {
					$print	= true;
				}
			}
		}

		if (
			stripos($line, "Current round begins") !== false
			|| stripos($line, "called the emergency shuttle") !== false
			|| stripos($line, "the emergency shuttle has arrived at centcom") !== false
		) {
			$print = true;
		}


		if ($print) {

			$bits	= [];
			if ($mOld = preg_match('/^\[([0-9:]+)\] \[([^]]+)\] (.*)(?:<br>)$/i', trim($line), $bits)) {
				pretty_log($n, $bits[1], $bits[2], $bits[3]);
			} elseif ($mNew = preg_match('/^\[([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+)\] \[([^]]+)\] (.*)(?:<br>)$/i', trim($line), $bits)) {
				pretty_log($n, $bits[1], $bits[2], $bits[3]);
			} else {
				print "<p>$line</p>";
			}
		}
	}
?>
	</div>
<?php
	require("html/log_footer.php");
