<?php if (!defined(JZ_SECURE_ACCESS)) die('Security breach detected.');
global $include_path, $jzUSER, $jzSERVICES, $cms_mode, $enable_audioscrobbler, $as_override_user, $as_override_all, $http_auth_enable, $allow_lang_choice, $allow_interface_choice, $allow_style_choice;

$this->displayPageTop("", word("User Preferences"));
$this->openBlock();
// Now let's show the form for it
if (isset ($_POST['update_settings'])) {
	if (strlen($_POST['field1']) > 0 && $_POST['field1'] != "jznoupd") {
		if ($_POST['field1'] == $_POST['field2']) {
			// update the password:
			$jzUSER->changePassword($_POST['field1']);
		}
	}

	$arr = array ();
	$arr['email'] = $_POST['email'];
	$arr['fullname'] = $_POST['fullname'];
	if ($allow_interface_choice == "true") {
	  $arr['frontend'] = $_POST['def_interface'];
	}
	if ($allow_style_choice == "true") {
	  $arr['theme'] = $_POST['def_theme'];
	}
	if ($allow_lang_choice == "true") {
	  $arr['language'] = $_POST['def_language'];
	}
	$arr['playlist_type'] = $_POST['pltype'];
	$arr['asuser'] = $_POST['asuser'];
	$arr['aspass'] = $_POST['aspass'];
	$jzUSER->setSettings($arr);

	if (isset ($_SESSION['theme'])) {
		unset ($_SESSION['theme']);
	}
	if (isset ($_SESSION['frontend'])) {
		unset ($_SESSION['frontend']);
	}
	if (isset ($_SESSION['language'])) {
		unset ($_SESSION['language']);
	}
?>
			<script language="javascript">
			opener.location.reload(true);
			-->
			</SCRIPT>
		<?php


	//$this->closeWindow(true);
	//return;
}

if (isset($http_auth_enable) && $http_auth_enable == "true") {
  $edit_pwd = false;  
} else if ($cms_mode == "false") {
  $edit_pwd = true;
} else {
  $edit_pwd = false;
}

$url_array = array ();
$url_array['action'] = "popup";
$url_array['ptype'] = "preferences";
echo '<form action="' . urlize($url_array) . '" method="POST">';
?>
	<table width="100%" cellpadding="3">
<?php	if ($edit_pwd == true) { ?>
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Password"); ?>:
			</td>
			<td width="70%">
				<input type="password" name="field1" class="jz_input" value="jznoupd"><br>
				<input type="password" name="field2" class="jz_input" value="jznoupd">
			</td>
		</tr><?php } else { ?>
				<input type="hidden" name="field1" value="jznoupd">
				<input type="hidden" name="field2" value="jznoupd"> <?php } ?>
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Full Name"); ?>:
			</td>
			<td width="70%">
				<input name="fullname" class="jz_input" value="<?php echo $jzUSER->getSetting('fullname'); ?>">
			</td>
		</tr>
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Email"); ?>:
			</td>
			<td width="70%">
				<input name="email" class="jz_input" value="<?php echo $jzUSER->getSetting('email'); ?>">
			</td>
		</tr>
		
		
		<?php

// Did they enable audioscrobbler?
if ($enable_audioscrobbler == "true" and ($as_override_user == "" or $as_override_all == "false")) {
?>
				<tr>
					<td width="30%" valign="top" align="right">
						<?php echo word("AS User"); ?>:
					</td>
					<td width="70%">
						<input name="asuser" class="jz_input" value="<?php echo $jzUSER->getSetting('asuser'); ?>">
					</td>
				</tr>
				<tr>
					<td width="30%" valign="top" align="right">
						<?php echo word("AS pass"); ?>:
					</td>
					<td width="70%">
						<input type="password" name="aspass" class="jz_input" value="<?php echo $jzUSER->getSetting('aspass'); ?>">
					</td>
				</tr>
				<?php

}
if ($allow_interface_choice == "true") {
?>
		
		
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Interface"); ?>:
			</td>
			<td width="70%">
				<select name="def_interface" class="jz_select" style="width:135px;">
					<?php

// Let's get all the interfaces
$retArray = readDirInfo($include_path . "frontend/frontends", "dir");
sort($retArray);
for ($i = 0; $i < count($retArray); $i++) {
	echo '<option ';
	if ($retArray[$i] == $jzUSER->getSetting("frontend")) {
		echo ' selected ';
	}
	echo 'value="' . $retArray[$i] . '">' . $retArray[$i] . '</option>' . "\n";
}
?>
				</select>
			</td>
		</tr>
<?php
}
if ($allow_style_choice == "true") {
?>
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Theme"); ?>:
			</td>
			<td width="70%">
				<select name="def_theme" class="jz_select" style="width:135px;">
					<?php

// Let's get all the interfaces
$retArray = readDirInfo($include_path . "style", "dir");
sort($retArray);
for ($i = 0; $i < count($retArray); $i++) {
	if ($retArray[$i] == "images") {
		continue;
	}
	echo '<option ';
	if ($retArray[$i] == $jzUSER->getSetting('theme')) {
		echo ' selected ';
	}
	echo 'value="' . $retArray[$i] . '">' . $retArray[$i] . '</option>' . "\n";
}
?>
				</select>
			</td>
		</tr>
<?php
}
if ($allow_lang_choice == "true") {
?>
		<tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Language"); ?>:
			</td>
			<td width="70%">
				<select name="def_language" class="jz_select" style="width:135px;">
					<?php

// Let's get all the interfaces
$languages = getLanguageList();
for ($i = 0; $i < count($languages); $i++) {
	echo '<option ';
	if ($languages[$i] == $jzUSER->getSetting('language')) {
		echo ' selected ';
	}
	echo 'value="' . $languages[$i] . '">' . $languages[$i] . '</option>' . "\n";
}
?>
				</select>
			</td>
		</tr>
<?php
}
?>				    <tr>
			<td width="30%" valign="top" align="right">
				<?php echo word("Playlist Type"); ?>:
			</td>
			<td width="70%">
				<select name="pltype" class="jz_select" style="width:135px;">
				    <?php

$list = $jzSERVICES->getPLTypes();
foreach ($list as $p => $desc) {
	echo '<option value="' . $p . '"';
	if ($jzUSER->getSetting('playlist_type') == $p) {
		echo " selected";
	}
	echo '>' . $desc . '</option>';
}
?>
				    </select>
			</td>
		</tr>
	</table>
	<br><center>
		<input type="submit" name="update_settings" value="<?php echo word("Update Settings"); ?>" class="jz_submit">
		<?php $this->closeButton(); ?> 
	</center>
	<br>
	</form>
	<?php


$this->closeBlock();
?>
