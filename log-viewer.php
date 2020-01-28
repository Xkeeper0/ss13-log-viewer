<?php

	require_once("includes.php");


	// No logs? Get logs.
	if (!isset($_GET['view']) || !isset($_GET['server'])) {
		$logs	= get_logs();
		print list_available_logs($logs);
		die();

	}

	$ms	= preg_match('#^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}\.html$#', $_GET['view']);
	if (!$ms) {
		die("i dont know what the fuck you did but it wasn't correct");

	} elseif (!in_array($_GET['server'], $config['servers'])) {
		die("You are putting things where they do not belong good sir.");

	}

	if (isset($_GET['redownload'])) {
		$file	= get_log($_GET['server'], $_GET['view'], true);
		if ($file) {
			header("Location: ?server=". $_GET['server'] ."&view=". $_GET['view'] ."");
			die();
		}

	}

	$file	= get_log($_GET['server'], $_GET['view']);
	if (!$file) {
		die("Error opening log. Sorry. Shit broke. Oh well.");
	}


	require("html/log_header.php");

	if (strpos($file, "Zamujasa/CREWCREDITS") === false) {
		echo "<div style='font-size: 200%;'>It looks like this log might be incomplete. This might be because the round wasn't over when it was downloaded (or it might still be going on, who knows). <a href='?redownload=1&amp;server=". $_GET['server'] ."&amp;view=". $_GET['view'] ."'>Redownload?</a></div>";
	}

?>

	<div id="controls">
		<button id="filter-button" disabled>Filter log types...</button>
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
	];

	foreach ($types as $type) {
		print "<label class='opt opt-$type'><input type='checkbox' name='$type' checked disabled> $type</label>\n";
	}


?>
		</div>
		<br>
		<label id="show-ckeys" class="faded"><input type="checkbox" disabled> Show ckeys</label>
		<br>
		<br>
		<strong>Filter by text:</strong><br>
		<span style="font-size: 70%;">one term per line<br>+term: match must include<br>-term: ignore<br>term: must match <em>any</em></span><br>
		<form method="get">
			<input type="hidden" name="server" value="<?php print $_GET['server']; ?>">
			<input type="hidden" name="view" value="<?php print $_GET['view']; ?>">
			<textarea name="search-string" style="display: block; height: 7em;" placeholder="ckey1
ckey2
+with the
-Shitty Bill"><?php if (isset($_GET['search-string'])) echo htmlspecialchars($_GET['search-string']); ?></textarea>
			<input type="submit" value="Filter">
		</form>
		<a href='?'>&larr; back to list</a>
	</div>
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
				if ($term{0} === "+") {
					$has_required = 1;
					if (stripos($line, substr($term, 1)) === false) {
						$print	= false;
						break;
					}

					// Continue to see if another term matches somewhere
					continue;

				} elseif ($term{0} === "-" && stripos($line, substr($term, 1)) !== false) {
					// Has ignored term; ignore line
					$print = false;
					break;
				}

				if ($term{0} === "+" || $term{0} === "-") {
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
			$m		= preg_match('/^\[([0-9:]+)\] \[([^]]+)\] (.*)(?:<br>)$/i', trim($line), $bits);

			if ($m) {
				pretty_log($n, $bits[1], $bits[2], $bits[3]);
			} else {
				print "<p>$line</p>";
			}
		}
	}

	require("html/log_footer.php");
