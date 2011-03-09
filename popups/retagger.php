<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');

/**
* Displays the Item Retagger tool
* 
* @author Ross Carlson, Ben Dodson
* @version 01/27/05
* @since 01/27/05
* @param $node The node we are looking at
*/
global $jzSERVICES, $jzUSER, $node;

if (!checkPermission($jzUSER, "admin", $node->getPath("String"))) {
	echo word("Insufficient permissions.");
	return;
}

$title = word("Retag files");
if ($node->getName() <> "") {
	$title = word("Retag files in") . ": " . $node->getName();
}
$this->displayPageTop("", $title, false);
$this->openBlock();

// Did they submit the form?
if (isset ($_POST['updateTags'])) {
	// Let's not timeout
	set_time_limit(0);

	// Ok, now let's see what they wanted to retag
	$reGenre = false;
	$reArtist = false;
	$reAlbum = false;
	$reTrack = false;
	$reNumber = false;
	$reAlbumArt = false;

	if (isset ($_POST['reGenre']) && $_POST['reGenre'] == "on") {
		$reGenre = true;
	}
	if (isset ($_POST['reArtist']) && $_POST['reArtist'] == "on") {
		$reArtist = true;
	}
	if (isset ($_POST['reAlbum']) && $_POST['reAlbum'] == "on") {
		$reAlbum = true;
	}
	if (isset ($_POST['reTrack']) && $_POST['reTrack'] == "on") {
		$reTrack = true;
	}
	if (isset ($_POST['reNumber']) && $_POST['reNumber'] == "on") {
		$reNumber = true;
	}
	if (isset ($_POST['reAlbumArt']) && $_POST['reAlbumArt'] == "on") {
		$reAlbumArt = true;
	}

	// Now let's see what grouping were on
	$length = 50;
	if (!isset ($_SESSION['jz_retag_group'])) {
		$_SESSION['jz_retag_group'] = 0;
	} else {
		$_SESSION['jz_retag_group'] = $_SESSION['jz_retag_group'] + $length;
	}

	// Ok, now let's get on with it, first let's setup the div for displaying the data	
	echo word("Retagging files, please stand by...") . "<br><br>";
	flushdisplay();

	echo '<div id="group"></div>';
	echo '<div id="track"></div>';
	echo '<div id="trackname"></div>';
	echo '<div id="tracknum"></div>';
	echo '<div id="genre"></div>';
	echo '<div id="artist"></div>';
	echo '<div id="album"></div>';
	echo '<div id="status"></div>';
	echo '<div id="percent"></div>';
?>
			<SCRIPT LANGUAGE=JAVASCRIPT><!--\
				gr = document.getElementById("group");
				t = document.getElementById("track");
				g = document.getElementById("genre");
				ar = document.getElementById("artist");
				al = document.getElementById("album");
				tnu = document.getElementById("tracknum");
				tn = document.getElementById("trackname");
				s = document.getElementById("status");
				p = document.getElementById("percent");
				-->
			</SCRIPT>
			<?php


	// Ok, now we need to move track by track and get all it's data
	$allTracks = $node->getSubNodes("tracks", -1);
?>
			<SCRIPT LANGUAGE=JAVASCRIPT><!--\
				gr.innerHTML = '<nobr><?php echo word("Files"); ?>: <?php echo $_SESSION['jz_retag_group']. " - ". ($_SESSION['jz_retag_group'] + $length). "/". count($allTracks); ?></nobr>';
				-->
			</SCRIPT>
			<?php

	flushdisplay();

	$track = array_slice($allTracks, $_SESSION['jz_retag_group'], $length);
	$total = count($track);
	$success = 0;
	$failed = 0;
	$totalCount = 0;
	$start = time();

	// Now let's get the art for this track
	for ($i = 0; $i < count($track); $i++) {
		$parent = $track[$i]->getParent();

		if ($reAlbumArt && ($albumArt = $parent->getMainArt("200x200")) !== false) {
			if (!stristr($albumArt, "ID3:")) {
				// Ok, let's get the properties of it
				if ($fd = fopen($albumArt, 'rb')) {
					$APICdata = fread($fd, filesize($albumArt));
					fclose($fd);
					list ($APIC_width, $APIC_height, $APIC_imageTypeID) = GetImageSize($albumArt);
					$imagetypes = array (
						1 => 'gif',
						2 => 'jpeg',
						3 => 'png'
					);
					$pArr = explode("/", $albumArt);
					$pic_name = $pArr[count($pArr) - 1];
					if (isset ($imagetypes[$APIC_imageTypeID])) {
						$pic_data = $APICdata;
						$pic_ext = returnFileExt($albumArt);
						$pic_name = $pic_name;
						$pic_mime = 'image/' . $imagetypes[$APIC_imageTypeID];
					}
				}
			}
		}
		// First lets set the art
		if ($pic_data) {
			$meta['pic_data'] = $pic_data;
			$meta['pic_ext'] = $pic_ext;
			$meta['pic_name'] = $pic_name;
			$meta['pic_mime'] = $pic_mime;
		}

		if ($track[$i]->getPath() == "") {
			continue;
		}
		// Ok, now we need to figure out the data from the path
		$path = $track[$i]->getPath();
		$filename = $track[$i]->getDataPath("String");

		if (!fopen($filename, 'r+')) {
			writeLogData("messages", "ERROR: Could not open file for retagging: $filename");
			continue;
		}

		$tName = $path[count($path) - 1];
		$fName = $tName;
		// now let's split the exension and number IF it's there
		$tArr = explode(".", $tName);
		unset ($tArr[count($tArr) - 1]);
		$tName = implode(".", $tArr);
		if (is_numeric(substr($tName, 0, 2))) {
			$tNum = substr($tName, 0, 2);
			$tName = substr($tName, 3);
			// Now we need to clean off the dashes
			trim($tName);
			if (substr($tName, 0, 1) == "-") {
				$tName = trim(substr($tName, 1));
			}
			if (substr($tName, 0, 1) == "_") {
				$tName = trim(substr($tName, 1));
			}
		} else {
			$tNum = "01";
		}
		// Now let's convert underscores to spaces
		$tName = str_replace("_", " ", $tName);

		// Now let's get the rest and convert underscores to dashes

		if ($_POST['edit_reAlbum_custom'] <> "") {
			$album = str_replace("_", " ", $_POST['edit_reAlbum_custom']);
		} else {
			$album = getInformation($track[$i], "album");
			if (!isNothing($album)) {
				$disk = getInformation($track[$i], "disk");
				if (!isNothing($disk)) {
					$album .= " (" . $disk . ")";
				}
			}
			// TODO: Do we want the Disk information here too?
		}
		if ($_POST['edit_reArtist_custom'] <> "") {
			$artist = str_replace("_", " ", $_POST['edit_reArtist_custom']);
		} else {
			$artist = getInformation($track[$i], "artist");
		}
		if ($_POST['edit_reGenre_custom'] <> "") {
			$genre = str_replace("_", " ", $_POST['edit_reGenre_custom']);
		} else {
			$genre = getInformation($track[$i], "genre");
		}

		// Ok, we've got the data let's build our meta array
		if ($reGenre && !isNothing($genre)) {
			$meta['genre'] = $genre;
		}
		if ($reArtist && !isNothing($artist)) {
			$meta['artist'] = $artist;
		}
		if ($reAlbum && !isNothing($album)) {
			$meta['album'] = $album;
		}
		if ($reTrack && !isNothing($tNum)) {
			$meta['number'] = $tNum;
		}
		if ($reNumber && !isNothing($tName)) {
			$meta['title'] = $tName;
		}

		// Now let's display
?>
				<SCRIPT LANGUAGE=JAVASCRIPT><!--\
					t.innerHTML = '<nobr><?php echo word("Track"); ?>: <?php echo str_replace("'","",$fName); ?></nobr>';
					tn.innerHTML = '<nobr><?php echo word("Track Name"); ?>: <?php echo str_replace("'","",$tName); ?></nobr>';
					tnu.innerHTML = '<nobr><?php echo word("Track Num"); ?>: <?php echo str_replace("'","",$tNum); ?></nobr>';
					g.innerHTML = '<nobr><?php echo word("Genre"); ?>: <?php echo str_replace("'","",$genre); ?></nobr>';
					ar.innerHTML = '<nobr><?php echo word("Artist"); ?>: <?php echo str_replace("'","",$artist); ?></nobr>';
					al.innerHTML = '<nobr><?php echo word("Album"); ?>: <?php echo str_replace("'","",$album); ?></nobr>';
					-->
				</SCRIPT>
				<?php

		flushdisplay();

		// Now let's get the progress
		$progress = round(($i / $total) * 100);
		$totalCount++;
		$progress = $totalCount . "/" . $total . " - " . $progress . "%";
		// now let's write it
		if ($track[$i]->setMeta($meta)) {
?>
					<SCRIPT LANGUAGE=JAVASCRIPT><!--\
						s.innerHTML = '<nobr><?php echo word("Status"); ?>: <?php echo word("Success"); ?></nobr>';
						p.innerHTML = '<nobr><?php echo word("Progress"); ?>: <?php echo $progress; ?></nobr>';
						-->
					</SCRIPT>
					<?php

			$success++;
		} else {
?>
					<SCRIPT LANGUAGE=JAVASCRIPT><!--\
						//s.innerHTML = '<nobr><?php echo word("Status"); ?>: <?php echo word("Failed"); ?></nobr>';
						p.innerHTML = '<nobr><?php echo word("Progress"); ?>: <?php echo $progress; ?></nobr>';
						-->
					</SCRIPT>
					<?php

			$failed++;
		}

		flushdisplay();
		unset ($meta);
	}

	// Now are we done or do we continue?
	if (count($allTracks) < $_SESSION['jz_retag_group']) {
		// Now let's update the nodes cache
?>
				<SCRIPT LANGUAGE=JAVASCRIPT><!--\
					t.innerHTML = '<nobr><?php echo word("Updating Track Caching..."); ?></nobr>';
					tn.innerHTML = '&nbsp;';
					tnu.innerHTML = '&nbsp;';
					g.innerHTML = '&nbsp;';
					ar.innerHTML = '&nbsp;';
					al.innerHTML = '&nbsp;';
					s.innerHTML = '&nbsp;';
					p.innerHTML = '&nbsp;';
					gr.innerHTML = '&nbsp;';
					-->
				</SCRIPT>
				<?php

		flushdisplay();
		//$node->updateCache(true, false, false, true);
		updateNodeCache($node, true, false, true);
?>
				<SCRIPT LANGUAGE=JAVASCRIPT><!--\
					t.innerHTML = '<nobr><?php echo word("Process Complete!"); ?></nobr>';
					tn.innerHTML = '&nbsp;';
					tnu.innerHTML = '&nbsp;';
					g.innerHTML = '&nbsp;';
					ar.innerHTML = '&nbsp;';
					al.innerHTML = '&nbsp;';
					s.innerHTML = '&nbsp;';
					p.innerHTML = '&nbsp;';
					gr.innerHTML = '&nbsp;';
					-->
				</SCRIPT>
				<?php

		unset ($_SESSION['jz_retag_group']);
		echo "<center>";
		$this->closeButton();
		exit ();
	} else {
?>
				<SCRIPT LANGUAGE=JAVASCRIPT><!--\
					t.innerHTML = '<nobr><?php echo word("Proceeding, please stand by..."); ?></nobr>';
					tn.innerHTML = '&nbsp;';
					tnu.innerHTML = '&nbsp;';
					g.innerHTML = '&nbsp;';
					ar.innerHTML = '&nbsp;';
					al.innerHTML = '&nbsp;';
					s.innerHTML = '&nbsp;';
					p.innerHTML = '&nbsp;';
					gr.innerHTML = '&nbsp;';
					-->
				</SCRIPT>
				<?php

		flushdisplay();
		// Now we need to setup our bogus form
		$arr = array ();
		$arr['action'] = "popup";
		$arr['ptype'] = "retagger";
		$arr['jz_path'] = $node->getPath("String");
		echo '<form name="retagger" action="' . urlize($arr) . '" method="POST">';
		echo '<input type="hidden" name="reGenre" 				value="' . $_POST['reGenre'] . '">';
		echo '<input type="hidden" name="reGenre_filesystem" 	value="' . $_POST['reGenre_filesystem'] . '">';
		echo '<input type="hidden" name="edit_reGenre_custom" 	value="' . $_POST['edit_reGenre_custom'] . '">';
		echo '<input type="hidden" name="reArtist" 				value="' . $_POST['reArtist'] . '">';
		echo '<input type="hidden" name="reArtist_filesystem" 	value="' . $_POST['reArtist_filesystem'] . '">';
		echo '<input type="hidden" name="edit_reArtist_custom" 	value="' . $_POST['edit_reArtist_custom'] . '">';
		echo '<input type="hidden" name="reAlbum" 				value="' . $_POST['reAlbum'] . '">';
		echo '<input type="hidden" name="reAlbum_filesystem" 	value="' . $_POST['reAlbum_filesystem'] . '">';
		echo '<input type="hidden" name="edit_reAlbum_custom" 	value="' . $_POST['edit_reAlbum_custom'] . '">';
		echo '<input type="hidden" name="reTrack" 				value="' . $_POST['reTrack'] . '">';
		echo '<input type="hidden" name="reNumber" 				value="' . $_POST['reNumber'] . '">';
		echo '<input type="hidden" name="reAlbumArt" 				value="' . $_POST['reAlbumArt'] . '">';
		echo '<input type="hidden" name="updateTags" 			value="' . $_POST['updateTags'] . '">';
		echo '</form>';
?>
				<SCRIPT language="JavaScript">
				document.retagger.submit();
				</SCRIPT>

				<?php

		exit ();
	}
}

// Now let's give them a form so they can pick what to auto-tag
$arr = array ();
$arr['action'] = "popup";
$arr['ptype'] = "retagger";
$arr['jz_path'] = $node->getPath("String");
echo '<form name="retagger" action="' . urlize($arr) . '" method="POST">';
?>
		<?php echo word("This tool will rewrite the ID3 tags on your MP3 files based on their structure in the filesystem or with the values you specify below.  You may select which values will be updated by check them below."); ?>
		<br><br>
		<table width="100%">
			<tr>
				<td valign="top">
					<input type="checkbox" <?php if (getInformation($node,"genre") !== false) echo "checked"; ?> name="reGenre"> <?php echo word("Genre"); ?>
				</td>
				<td>
					<input onClick="document.retagger.edit_reGenre_custom.value='';" value="filesystem" type="radio" checked name="reGenre_filesystem"> <?php echo word("Filesystem Data"); ?><br>
					<input value="custom" type="radio" name="reGenre_filesystem"> 
					<input type="text" name="edit_reGenre_custom" value="" size="30" class="jz_input">
				</td>
			</tr>
			<tr>
				<td valign="top">
					<input type="checkbox" <?php if (getInformation($node,"artist") !== false) echo "checked"; ?> name="reArtist"> <?php echo word("Artist"); ?>
				</td>
				<td>
					<input onClick="document.retagger.edit_reArtist_custom.value='';" value="filesystem" type="radio" checked name="reArtist_filesystem"> <?php echo word("Filesystem Data"); ?><br>
					<input value="custom" type="radio" name="reArtist_filesystem"> 
					<input type="text" name="edit_reArtist_custom" value="" size="30" class="jz_input">
				</td>
			</tr>
			<tr>
				<td valign="top">
					<input type="checkbox" <?php if (getInformation($node,"album") !== false) echo "checked"; ?> name="reAlbum"> <?php echo word("Album"); ?>
				</td>
				<td>
					<input onClick="document.retagger.edit_reAlbum_custom.value='';" value="filesystem" type="radio" checked name="reAlbum_filesystem"> <?php echo word("Filesystem Data"); ?><br>
					<input value="custom" type="radio" name="reAlbum_filesystem"> 
					<input type="text" name="edit_reAlbum_custom" value="" size="30" class="jz_input">
					<br><br>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<input type="checkbox" checked name="reTrack"> <?php echo word("Track Name"); ?>
				</td>
				<td>
					&nbsp; &nbsp; &nbsp; <?php echo word("Filesystem Data"); ?><br>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<input type="checkbox" checked name="reNumber"> <?php echo word("Track Number"); ?>
				</td>
				<td>
					&nbsp; &nbsp; &nbsp; <?php echo word("Filesystem Data"); ?><br>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<input type="checkbox" checked name="reAlbumArt"> <?php echo word("Album Art"); ?>
				</td>
				<td>
					&nbsp; &nbsp; &nbsp; <?php echo word("Filesystem Data"); ?><br>
				</td>
			</tr>
		</table>
		<br><center><input type="submit" name="updateTags" value="<?php echo word("Retag Tracks"); ?>" class="jz_submit"></center>
		</form>
		<?php


$this->closeBlock();
?>
