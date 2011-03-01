<?php if (!defined(JZ_SECURE_ACCESS)) die('Security breach detected.');


/**
* Scans the users system for newly added media
* 
* @author Ross Carlson
* @version 01/18/05
* @since 01/18/05
* @param $node The node we are looking at
*/

global $backend;

if ($backend == "id3-cache" || $backend == "id3-database") {
	$node = new jzMediaNode(); // Root only, just to be sure...
}

// First let's display the top of the page and open the main block
$title = $node->getName();
if ($title == "") {
	$title = word("Root Level");
}
$this->displayPageTop("", "Scanning for new media in: " . $title);
$this->openBlock();

// Let's show them the form
if (!isset ($_POST['edit_scan_now'])) {
	$url_array = array ();
	$url_array['action'] = "popup";
	$url_array['ptype'] = "scanformedia";
	$url_array['jz_path'] = $_GET['jz_path'];
	$i = 0;
?>
			<form action="<?php echo urlize($url_array); ?>" method="post">
			   <?php


	if (!($backend == "id3-cache" || $backend == "id3-database")) {
?>
				<input name="edit_scan_where" value="only" checked type="radio"> <?php echo word("This level only"); ?><br>
				<input name="edit_scan_where" value="all" type="radio"> <?php echo word("All sub items (can be very slow)"); ?><br><br>
			     <?php


	} else {
?>
 				<input name="edit_scan_where" value="all" type="hidden">
                             <?php


	}
?>
                                <input name="edit_force_scan" value="true" type="checkbox"> <?php echo word("Re-read tag data (slow)"); ?><br>
				<br>
				&nbsp; &nbsp; &nbsp; <input type="submit" name="edit_scan_now" value="<?php echo word("Scan Now"); ?>" class="jz_submit">
			</form>		
			<?php


	exit ();
}

// Ok, let's do it...		
echo "<b>" . word("Scanning") . ":</b>";
echo '<div id="importStatus"></div>';
?>
		<script language="javascript">
		d = document.getElementById("importStatus");
		-->
		</SCRIPT>
		<?php


set_time_limit(0);
flushdisplay();

// Now how to scan?
if ($_POST['edit_scan_where'] == "only") {
	$recursive = false;
} else {
	$recursive = true;
}

// Let's scan...
if (isset ($_POST['edit_force_scan'])) {
	$force_scan = true;
} else {
	$force_scan = false;
}

updateNodeCache($node, $recursive, true, $force_scan);

echo "<br><br><b>" . word("Complete!") . "</b>";
$this->closeBlock();
flushdisplay();

// Now let's close out
echo "<br><br><center>";
$this->closeButton();
?>
