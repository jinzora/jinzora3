<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	// Let's figure out the path stuff so we'll know how/where to include from$form_action = "index.php?install=step3";
	$form_action = setThisPage() . "install=step3";
	$recheck_action = setThisPage() . "install=step2";

	// Choose ezPublish Siteaccess, based on choosen installer language:
	switch ($_POST['jz_lang_file']){
		case "dutch";
			$siteaccess = "nl";
		break;
		// Every other installer language defaults to English Siteaccess:
		case
			$siteaccess = "en";
		break;
	}

	// Now let's include the left
	include_once($include_path. 'install/leftnav.php');
	$fatal = false;
?>

<script language="JavaScript">
<!--
var siteaccess;
var nodeid;
var helpurl;
function popuphelp(siteaccess,nodeid){
	helpurl='http://www.jinzorahelp.com/' + siteaccess + '/layout/set/program/content/view/base/' + nodeid;
	newwindow2=window.open(helpurl,'helpwindow','height=760,width=556');
	if (window.focus) {newwindow.focus()}
}
// -->
</script>

<div id="main">
	<a href="http://www.jinzora.com" target="_blank"><img src="<?php echo $include_path; ?>install/logo.gif" border="0" align="right" vspace="5" hspace="0"></a>
	<h1><?php echo $word_package_verify; ?></h1>
	<p>
	<?php echo $word_package_verify_note; ?>
	<form action="<?php echo $form_action; ?>" name="setup3" method="post">
		<?php
			$PostArray = $_POST;
			foreach ($PostArray as $key => $val) {
			  if (!stristr($key,"submit")){
			  	echo '<input type="hidden" name="' . htmlentities($key) . '" value="' . htmlentities($val) .'">'. "\n";
			  }
		   }
		?>
		<div class="go">
			<span class="goToNext">
				<?php echo $word_checking_requirements; ?>
			</span>
		</div>
		<table width="100%" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td class="td" width="30%" align="left">
					<?php echo $word_php_version; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						$fatal = false;
						// First let's check the PHP Version
						if (phpversion() < 5.3){
							echo '<font color="red">4.2+ required, '. phpversion(). ' found - fatal error!</font>';
							?>
							&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $php_version_error; ?>');" onmouseout="return nd();">?</a>
							<?php
							$fatal = true;
						} else {
							echo '<font color="green">'. phpversion(). ' found (5.3 or higher required)</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left">
					<?php echo $word_php_session_support; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						$fatal = false;
						// First let's check the PHP Version
						$jz_sess_test_var = 2;
						$jz_sess_test_var = $_SESSION['jz_sess_test'] + 1;
						if (!function_exists('session_name') or $jz_sess_test_var <> 1){
							echo '<font color="red">PHP Session Support not found/functioning - fatal!</font>';
							?>
							&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $php_session_error; ?>');" onmouseout="return nd();">?</a>
							<?php
							$fatal = true;
						} else {
							echo '<font color="green">PHP Session Support Enabled!</font>';
						}
					?>
				</td>
			</tr>
		   </table>
		<div class="go">
			<span class="goToNext">
				<?php echo $word_checking_optional; ?>
			</span>
		</div>		
		<table width="100%" cellspacing="0" cellpadding="0" border="0">	
			<tr>
				<td class="td" width="30%" align="left" valign="top">
				PHP MySQL Support:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
						ob_start();
						phpinfo(INFO_MODULES);
						$module_info = ob_get_contents();
						ob_end_clean();
						if (stristr($module_info,"MySQL Support")){
							$mySQL = true;
						} else {
							$mySQL = false;
						}
						if (!$mySQL){
							echo '<font color="red">Native MySQL Support not found.</font>';
						} else {
							echo '<font color="green">MySQL Support found!</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
				PHP SQLite Support:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
				if (function_exists("sqlite_query")) {
				  $pg = true;
				} else {
				  $pg = false;
				}
						if (!$pg){
							echo '<font color="orange">Not found - only necessary if you want to use SQLite.</font>';
						} else {
							echo '<font color="green">SQLite Support found!</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top" nowrap>
				PHP PostgreSQL Support:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
				if (function_exists("pg_connect")) {
				  $pg = true;
				} else {
				  $pg = false;
				}
						if (!$pg){
							echo '<font color="orange">Not found - only necessary if you want to use PostgreSQL.</font>';
						} else {
							echo '<font color="green">Postgres Support found!</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top" nowrap>
				PHP MSSQL Support:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
				if (function_exists("mssql_connect")) {
				  $pg = true;
				} else {
				  $pg = false;
				}
						if (!$pg){
							echo '<font color="orange">Not found - only necessary if you want to use Microsft SQL.</font>';
						} else {
							echo '<font color="green">MSSQL Support found!</font>';
						}
					?>
				</td>
			</tr>
            <tr>
				<td class="td" width="30%" align="left" valign="top">
				DBX Support:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
				if (function_exists("dbx_query")) {
				  $pg = true;
				} else {
				  $pg = false;
				}
						if (!$pg){
							echo '<font color="orange">Not found - only necessary for DBX databases.</font>';
						} else {
							echo '<font color="green">DBX Support found!</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					PHP Register Globals:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
						if (ini_get('register_globals') == "1"){
							echo '<font color="red">On - <strong>HUGE Possible Security Risk</strong></font>';
							?>
							&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_reg_global_error; ?>');" onmouseout="return nd();">?</a>
							<?php
						} else {
							echo '<font color="green">Off</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					Magic Quotes:
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
					// Now let's check for magic quotes
					$magic_quotes_err = false;
					if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime()) {
						echo '<font color="red">On - magic_quotes_runtime should be disabled.</font>';
						$magic_quotes_err = true;
					} elseif (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
						echo '<font color="red">On - magic_quotes_gpc should be disabled.</font>';
						$magic_quotes_err = true;
					}
					if ($magic_quotes_err == true) {
						?>
						&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_magic_quotes_error; ?>');" onmouseout="return nd();">?</a>
							<?php
					} else {
						echo '<font color="green">Off</font>';
					}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					<?php echo $word_gd; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
						ob_start();
						phpinfo(INFO_MODULES);
						$module_info = ob_get_contents();
						ob_end_clean();
						if (preg_match("/\bgd\s+version\b[^\d\n\r]+?([\d\.]+)/i",$module_info,$matches)) {
							$gd_version_number = $matches[1];
						} else {
							$gd_version_number = 0;
						}
						if ($gd_version_number == 0){
							echo $word_gd_error;
							?>
							&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_gd_error_note; ?>');" onmouseout="return nd();">?</a>
							<?php
						} else {
							echo '<font color="green">GD found!</font>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					<?php echo $word_iconv; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
						ob_start();
						phpinfo(INFO_MODULES);
						$module_info = ob_get_contents();
						ob_end_clean();
						if (stristr($module_info,"iconv library version")){
							// Now let's get the iconv version
							$iconv = substr($module_info,strpos($module_info,"iconv library version"));
							$iconv = substr($iconv,strpos($iconv,'class="v">')+strlen('class="v">'),10);
							$iconv = trim(substr($iconv,0,strpos($iconv,"<")));
						} else {
							$iconv = 0;
						}
						if ($iconv == 0){
							echo $word_iconv_error;
							?>
							&nbsp; <a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_iconv_error_note; ?>');" onmouseout="return nd();">?</a>
							<?php
						} else {
							echo '<font color="green">Iconv found!</font>';
						}
					?>
				</td>
			</tr>


			<tr>
				<td class="td" width="30%" align="left" valign="top">
					<?php echo $word_pdf; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check for GD support
						ob_start();
						phpinfo(INFO_MODULES);
						$module_info = ob_get_contents();
						ob_end_clean();
						if (stristr($module_info,"pdf")){
							// Now let's get the iconv version
							$pdf = true;
						} else {
							$pdf = false;
						}
						if (!$pdf){
							echo '<font color="orange">'. $word_pdf_error. '</font>';
						} else {
							echo '<font color="green">PDF support found!</font>';
						}
					?>
				</td>
			</tr>
		</table>
			
			
		<div class="go">
			<span class="goToNext">
				<?php echo $word_checking_permissions; ?>
			</span>
		</div>
		<table width="100%" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					settings.php
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check to see if things are writeable
						$file = $include_path. "settings.php";
						$error = true;
						if (!is_file($file)){
							if (@touch($file)){
								unlink($file);
								$error = false;
							}
						} else {
							if (is_writable($file)){
								$error = false;
							}
						}
						if ($error){
							echo '<font color="red">'. $word_not_writable. '</font>';
							?>
							<a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_settings_perm_error; ?>');" onmouseout="return nd();">?</a>
							<?php
							echo '<br>';
						} else {
							echo '<font color="green">'. $word_writable. '</font><br>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					data dir
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						// Now let's check all the directories
						$dirs = array("data/artists","data/cache","data/cache/discussions","data/cache/featured","data/cache/nodes","data/cache/request","data/cache/tracks","data/counter","data/database","data/database/discussions","data/discussions","data/downloads","data/featured","data/featured/albums","data/featured/artists","data/images","data/ratings","data/tracks","data/users","data/viewed");

						// Now let's test each dir
						$fileError = false;
						foreach($dirs as $dir){
							$file = $include_path. $dir;
							if (!is_writable($file)){
								$fileError = true;
								echo $file. " - not writable!<br>";
							}
						}
						if ($fileError){
							$error = true;
						}

						if ($error){
							$fatal = true;
							echo '<font color="red">'. $word_not_writable. ' ('. $word_fatal_error. ')</font>';
							?>
							<a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_data_perm_error; ?>');" onmouseout="return nd();">?</a>
							<?php
							echo '<br>';
						} else {
							echo '<font color="green">'. $word_writable. '</font><br>';
						}
					?>
				</td>
			</tr>
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					temp dir
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						$file = $include_path. "temp/test.txt";
						$error = true;
						if (!is_file($file)){
							if (@touch($file)){
								unlink($file);
								$error = false;
							}
						} else {
							if (is_writable($file)){
								$error = false;
							}
						}
						if ($error){
							$fatal = true;
							echo '<font color="red">'. $word_not_writable. ' ('. $word_fatal_error. ')</font>';
							?>
							<a class="helpbox2" href="javascript:void(0);" onmouseover="return overlib('<?php echo $word_temp_perm_error; ?>');" onmouseout="return nd();">?</a>
							<?php
							echo '<br>';
						} else {
							echo '<font color="green">'. $word_writable. '</font><br>';
						}
					?>
				</td>
			</tr>
		</table>
		<div class="go">
			<span class="goToNext">
				<?php echo $word_checking_files; ?>
			</span>
		</div>
		<?php
			// Now let's make sure ALL the files exist
			$fileMiss = false;
			$cArray = file($include_path. 'install/filelist.txt');
			for ($i=0; $i < count($cArray); $i++){
				if (!is_file(trim(getcwd(). "/". $include_path. $cArray[$i]))){
					$fatal = true;
					$fileMiss = true;
					$missing[] = $cArray[$i];
				}
			}
		?>
		<table width="100%" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					<?php echo $word_checking. " ". $i. " ". $word_files; ?>
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<?php
						if (!$fileMiss){
							echo '<font color="green">'. $word_all_files_found. '</font>';
						} else {
							echo '<font color="red"><strong>'. $word_files_missing. '</strong>';
							echo '<br>Missing:<br>';
							foreach($missing as $file){
								echo $file. "<br>";
							}
							echo '</font>';
						}
					?>
				</td>
			</tr>
		</table>
		<div class="go">
			<span class="goToNext">
				<?php echo $word_recommended_settings; ?>
			</span>
		</div>
		<?php $recheck = false; ?>
		<table width="100%" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td class="td" width="30%" align="left" valign="top">
					PHP Settings:<br>
					(php.ini)
				</td>
				<td class="td" width="1">&nbsp;</td>
				<td class="td" width="70%" align="left">
					<table width="100%" cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td width="50%" class="td">
								<strong>Setting</strong>
							</td>
							<td width="25%" align="center" class="td">
								<strong>Actual</strong>
							</td>
							<td width="25%" align="center" class="td">
								<strong>Recommend</strong>
							</td>
						</tr>
						<tr>
							<td width="50%" class="td">
								max_execution_time:
							</td>
							<td width="25%" align="center" class="td">
								<?php
			  if (ini_get('max_execution_time') > 299 || ini_get('max_execution_time') <= 0) {
										echo '<font color="green">';
									} else {
										echo '<font color="red">';
										$recheck = true;
									}
									echo ini_get('max_execution_time'). "</font><br>\n"
								?>

							</td>
							<td width="25%" align="center" class="td">
								300+
							</td>
						</tr>
						<tr>
							<td width="50%" class="td">
								memory_limit:
							</td>
							<td width="25%" align="center" class="td">
								<?php
									if (ini_get('memory_limit') >= 32){
										echo '<font color="green">';
									} else {
										echo '<font color="red">';
										$recheck = true;
									}
									echo ini_get('memory_limit'). "</font><br>\n"
								?>

							</td>
							<td width="25%" align="center" class="td">
								32M+
							</td>
						</tr>
						<tr>
							<td width="50%" class="td">
								post_max_size:
							</td>
							<td width="25%" align="center" class="td">
								<?php
									if (ini_get('post_max_size') >= 32){
										echo '<font color="green">';
									} else {
										echo '<font color="red">';
										$recheck = true;
									}
									echo ini_get('post_max_size'). "</font><br>\n";
								?>
							</td>
							<td width="25%" align="center" class="td">
								32M+
							</td>
						</tr>
						<tr>
							<td width="50%" class="td">
								file_uploads:
							</td>
							<td width="25%" align="center" class="td">
								<?php
									if (ini_get('file_uploads') > 0){
										echo '<font color="green">';
									} else {
										echo '<font color="red">';
										$recheck = true;
									}
									echo ini_get('file_uploads'). "</font><br>\n";
								?>
							</td>
							<td width="25%" align="center" class="td">
								1 (on)
							</td>
						</tr>
						<tr>
							<td width="50%" class="td">
								upload_max_filesize:
							</td>
							<td width="25%" align="center" class="td">
								<?php
									if (ini_get('upload_max_filesize') >= 32){
										echo '<font color="green">';
									} else {
										echo '<font color="red">';
										$recheck = true;
									}
									echo ini_get('upload_max_filesize'). "</font><br>\n";
								?>
							</td>
							<td width="25%" align="center" class="td">
								32M+
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div class="go">
			<span class="goToNext">
				<?php
					if (!$fatal){
						echo '&nbsp; <input type="submit" name="submit_step3" value="'. $word_proceed_license. '" class="submit">';
						echo '</form>';
					}
					if ($fatal){
						echo $word_fatal_errors;
					}
					if ($fatal or $recheck){
						echo '</form>';
						echo '<form action="'. $recheck_action. '" name="setup2" method="post">';
						$PostArray = $_POST;
							foreach ($PostArray as $key => $val) {
							  if (!stristr($key,"submit")){
								echo '<input type="hidden" name="' . htmlentities($key) . '" value="' . htmlentities($val) .'">'. "\n";
							  }
						   }
						echo '<br>&nbsp; <input type="submit" name="reload" value="'. $word_recheck_req. '" class="submit">';
						echo '</form>';
					}
				?>
			</span>
		</div>

	</div>
<?php
	// Now let's include the top
	include_once($include_path. 'install/footer.php');
?>
