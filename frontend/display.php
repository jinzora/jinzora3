<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *        
	* JINZORA | Web-based Media Streamer   
	*
	* Jinzora is a Web-based media streamer, primarily desgined to stream MP3s 
	* (but can be used for any media file that can stream from HTTP). 
	* Jinzora can be integrated into a CMS site, run as a standalone application, 
	* or integrated into any PHP website.  It is released under the GNU GPL.
	* 
	* Jinzora Author:
	* Ross Carlson: ross@jasbone.com 
	* http://www.jinzora.org
	* Documentation: http://www.jinzora.org/docs	
	* Support: http://www.jinzora.org/forum
	* Downloads: http://www.jinzora.org/downloads
	* License: GNU GPL <http://www.gnu.org/copyleft/gpl.html>
	* 
	* Contributors:
	* Please see http://www.jinzora.org/modules.php?op=modload&name=jz_whois&file=index
	*
	* Code Purpose: This page contains all the album display related functions
	* Created: 9.24.03 by Ross Carlson
	*
	* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
	

	/**
	 * Pulls the correct PHP code for a given block.
	 * Attempts to use the given frontend,
	 * but falls back to code in frontend/blocks.
	 * 
	 * The usual call is: include(jzBlock("name"));
	 * We do this because doing the include inside this function
	 * can mess with variables required by the block code.
	 *
	 * @author Ben Dodson
	 * @ since 8/21/2006
	 */
	function jzBlock($block) {
		global $fe;
		if (false !== strstr($block,'..') || 
		    false !== strstr($block,'/') ||
		    false !== strstr($block,'\\')) {
			die("Security breach detected (jzBlock)");
		}
		
		if (file_exists($file = dirname(__FILE__).'/frontends/'.$fe->name.'/blocks/'.$block.'.php')) {
	  	return $file;
		} else {
		  return dirname(__FILE__).'/blocks/'.$block.'.php';
		}
	}
	
	function jzTemplate($smarty, $template) {	
		global $fe;
		if (false !== strstr($template,'..') || 
			false !== strstr($template,'/') || 
			false !== strstr($template,'\\')) {
			die("Security breach detected (jzTemplate)");
		}
		
		if (file_exists($file = dirname(__FILE__).'/frontends/'.$fe->name.'/templates/'.$template.'.tpl')) {
		  $smarty->display($file);
		}
		else if (file_exists($file = SMARTY_ROOT. 'templates/'.$fe->name.'/'. $template. '.tpl')) {
			$smarty->display($file);
		} else {
			$smarty->display(SMARTY_ROOT. 'templates/blocks/'. $template. '.tpl');
		}
	}

	
	
	/**
	 * Handles the code for a popup function. Similar to
	 * the block-handling function.
	 * 
	 * @author Ben Dodson
	 * @ since 8/21/2006
	 */
	function jzPopup($block) {
		global $include_path;
		if (false !== strstr($block,'..') || 
		    false !== strstr($block,'/') ||
		    false !== strstr($block,'\\')) {
			die("Security breach detected (jzBlock)");
		}
		
	  	return $include_path.'popups/'.$block.'.php';
	}
	
	class jzDisplay {
	
		/**
		* Constructor for the class.
		* 
		* @author Ben Dodson
		* @version 10/27/04
		* @since 10/27/04
		*/
		function jzDisplay() {
		
		}
				
		function startCache($func, $params, $age = false){

		  if (func_num_args() > 2) {
		    $moreargs = func_get_args();
		    $moreags = array_slice($moreargs,2);
		  } else {
		    $moreargs = false;
		  }
			return $this->_internalCacheFunc(true,$func,$params,$moreargs,$age);
		}
		
		function endCache(){
			return $this->_internalCacheFunc(false);
		}
		
	  // This function is internal to the cache functions.
	  // We use this so we can create a static variable across both functions.
	  function _internalCacheFunc($start, $func = false, $params = false, $moreargs = false, $age = false) {
	    global $cache_age_days,$gzip_page_cache,$enable_page_caching;
	    static $signature_stack = array();

	    if ($enable_page_caching == "false") {
	      return false;
	    }
			
	    if ($start) {
	      // START THE CACHE
	      $cacheFile = $this->createCachedPageName($func,$params,$moreargs);
				
				// Did they specify an age?
				if ($age && is_numeric($age)){
					$cache_age_days = $age;
				}
				
	      if (is_file($cacheFile) and (time() - filemtime($cacheFile)) < ($cache_age_days * 86400)){
					if ($gzip_page_cache == "true"){
						$fp = gzopen($cacheFile,'r');
						gzpassthru($fp);
					} else {
						include_once($cacheFile);
					}
					return true;
				} else {
					if (is_object($params)){
						writeLogData("messages","Cache: Building cache for: ". $params->getName(). " type: ". $func);
					}
					ob_start();
					array_push($signature_stack,$cacheFile);
	      }
	    } else {
	      // END THE CACHE
	      $cacheFile = array_pop($signature_stack);

	      if ($gzip_page_cache == "true"){
					$fp = gzopen($cacheFile, 'w');
					gzwrite($fp, ob_get_contents());
					gzclose($fp);
	      } else {
					$fp = fopen($cacheFile, 'w');
					fwrite($fp, ob_get_contents());
					fclose($fp);
	      }

	      ob_end_flush();
	    }
	  }

		function createCachedPageName($func,$params,$moreargs){
			global $web_root, $root_dir, $jzUSER, $security_key, $skin, $my_frontend, $enable_page_caching;
			
			if ($enable_page_caching == "false") {
	      return false;
	    }
			
			$signature = $skin . $my_frontend . $security_key . $func . serialize($params);
			
			if ($moreargs !== false) {
			  for ($i = 0; $i < sizeof($moreargs); $i++) {
			    $signature .= $moreargs[$i];
			  }
			}

			$name="";
			if (is_object($params)){
				$name = ".". md5($params->getName());
			}
			
			// Let's create the page
			$pName = $web_root. $root_dir. "/temp/cache/". md5($signature . "-". $jzUSER->getID()). $name. ".html";
			
			// Now do we need to kill this file?
			if ($_SESSION['jz_purge_file_cache'] == "true"){
				@unlink($pName);
			}
			
			return $pName;
		}
		
		function purgeCachedPage($node){		
			global $web_root, $root_dir, $jzUSER,$include_path;

			$name="";
			if (is_object($node)){
				$name = md5($node->getName());
			}
			if (0 == strlen($name)) {
				return;
			}
			$d = @dir($include_path . "temp/cache");
			if ($d !== false) {
			  while (false !== ($entry = $d->read())) {
			    if (stristr($entry,$name)){
			      @unlink($include_path."temp/cache/". $entry);
			    }
			  }				
			}
		}
		
		/*
		* Displays the shopping purchase button using the shopping service
		*
		* @author Ross Carlson
		* @since 12.02.05
		* @version 12.02.05
		* @param $node The node we are looking at
		**/
		function purchaseButton($node){
			global $enable_shopping, $jzSERVICES;
			
			// Is shopping enabled?
			if ($enable_shopping <> "true"){
				return;
			}
			
			// Let's create the shopping service			
			echo $jzSERVICES->createShoppingLink($node);
		}
		
		
		/*
		* Displays a button to allow the user to add this item to their favorites
		*
		* @author Ross Carlson
		* @since 12.17.05
		* @version 12.17.05
		* @param $node The node they are adding
		**/
		function addToFavButton($node, $return = false){
			global $img_add_fav, $enable_favorites;
			
			if ($enable_favorites <> "true"){return;}
			
			$arr = array();
			$arr['action'] = "popup";
			$arr['ptype'] = "addtofavorites";
			$arr['jz_path'] = $node->getPath("String");
							
			$retVal = '<a onClick="openPopup(' . "'". urlize($arr) ."'" . ',350,150); return false;" href="javascript:;">';
			$retVal .= $img_add_fav;
			$retVal .= "</a>";
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}
		
		/*
		* Displays the media mangement dropdown
		*
		* @author Ross Carlson
		* @since 4.16.05
		* @version 4.16.05
		* @param $array An array of the names of the tabs
		**/
		function displayTabs($array){
			?>
			<style>
				.tab{
					border: thin solid <?php echo jz_fg_color; ?>;
					position: absolute;
					top: 40;
					width: 140;
					text-align: center;
					color: <?php echo jz_font_color; ?>;
					font-weight: bold;
					padding: 3;
					cursor: pointer;
					cursor: hand;
				}
				.panel{
					position: absolute;
					top: 70;
					width: 95%;
					z-index: 1;
					visibility: hidden;
					overflow: auto;
				}
			</style>
			<script language="JavaScript">
				var currentPanel;
				
				function showPanel(panelNum) {
				//hide visible panel, show selected panel, 
				//set tab
				if (currentPanel != null) {
					hidePanel();
				}
				document.getElementById('panel'+panelNum).style.visibility = 'visible';
					currentPanel = panelNum;
					setState(panelNum);
				}
				
				function hidePanel() {
					//hide visible panel, unhilite tab
					document.getElementById('panel'+currentPanel).style.visibility = 'hidden';
					document.getElementById('tab'+currentPanel).style.backgroundColor = '<?php echo jz_pg_bg_color; ?>';
					document.getElementById('tab'+currentPanel).style.color = '<?php echo jz_font_color; ?>';
				}
				
				function setState(tabNum) {
					if (tabNum==currentPanel) {
						document.getElementById('tab'+tabNum).style.backgroundColor = '<?php echo jz_fg_color; ?>';
						document.getElementById('tab'+tabNum).style.color = '<?php echo jz_font_color; ?>';
					}	else {
						document.getElementById('tab'+tabNum).style.backgroundColor = '<?php echo jz_pg_bg_color; ?>';
						document.getElementById('tab'+tabNum).style.color = '<?php echo jz_font_color; ?>';
					}
				}
				
				function hover(tab) {
					tab.style.backgroundColor = '<?php echo jz_fg_color; ?>';
				}
			</script>
			<?php
			$i=1;
			$c=5;
			foreach ($array as $item){				
				echo '<div id="tab'. $i. '" class="tab" style="left: '. $c. ';" onClick="showPanel('. $i. ');" onMouseOver="hover(this);" onMouseOut="setState('. $i. ')">'. $item. '</div>';
				$c=$c+147;
				$i++;
			}
		}
		
		/*
		* Displays the media mangement dropdown
		*
		* @author Ross Carlson
		* @since 4.16.05
		* @version 4.16.05
		* @param $node The node we are looking at
		**/
		function systemToolsDropdown($node){
			global $this_page;
		  if (!is_object($node)) {
		    $node = new jzMediaNode();
		  }
			?> 
			<form action="<?php echo $this_page; ?>" method="GET" name="toolsform">
				<select class="jz_select" name="action" style="width:125px" onChange="openPopup(this.form.action.options[this.selectedIndex].value, 450, 450, false, 'SystemTools')">
					<?php
						// Now let's setup the values
						$url_array = array();
						$url_array['jz_path'] = $node->getPath("String");
						$url_array['action'] = "popup";
					?>
					<option value="">System Tools</option>
					<option value="<?php $url_array['ptype'] = "mediamanager"; echo urlize($url_array); ?>"><?php echo word("Media Manager"); ?></option>
					<option value="<?php $url_array['ptype'] = "usermanager"; echo urlize($url_array); ?>"><?php echo word("User Manager"); ?></option>
					<option value="<?php $url_array['ptype'] = "sitesettings"; echo urlize($url_array); ?>"><?php echo word("Settings Manager"); ?></option>
					<option value="<?php $url_array['ptype'] = "sitenews"; echo urlize($url_array); ?>"><?php echo word("Manage Site News"); ?></option>
					<option value="<?php $url_array['ptype'] = "nodestats"; unset($url_array['jz_path']); echo urlize($url_array); ?>"><?php echo word("Show Full Site Stats"); ?></option>
					<option value="<?php $url_array['ptype'] = "dupfinder"; echo urlize($url_array); ?>"><?php echo word("Duplicate Finder"); ?></option>
				</select>
			</form>
			<?php
		}
		
		
		/*
		* Displays the media mangement dropdown
		*
		* @author Ross Carlson
		* @since 4.16.05
		* @version 4.16.05
		* @param $node The node we are looking at
		**/
		function mediaManagementDropdown($node){
			global $jzUSER, $allow_filesystem_modify, $resize_images, $enable_podcast_subscribe, $root_dir;
			?>
			<form action="<?php echo $root_dir. "/popup.php"; ?>" method="GET" name="mediamanform">
				<select class="jz_select" name="action" style="width:125px" onChange="openPopup(this.form.action.options[this.selectedIndex].value, 400, 400, false, 'MediaManagement')">
					<?php
					// Now let's setup the values
					$url_array = array();
					$url_array['jz_path'] = $node->getPath("String");
					$url_array['action'] = "popup";
					?>
					<option value=""><?php echo word("Media Management"); ?></option>
				        <option value="<?php $url_array['ptype'] = "scanformedia"; echo urlize($url_array);  ?>"><?php echo word("Rescan Media"); ?></option>
					<?php
						// Can they add media?
						if (checkPermission($jzUSER,"upload",$node->getPath("String")) and $allow_filesystem_modify == "true") {
					?>
						<option value="<?php $url_array['ptype'] = "uploadmedia"; echo urlize($url_array);  ?>"><?php echo word("Add Media"); ?></option>
					<?php
						}
					?>
					<?php
						if ($node->getPType() == "album"){
					?>
						<option value="<?php $url_array['ptype'] = "bulkedit"; echo urlize($url_array);  ?>"><?php echo word("Bulk Edit"); ?></option>
					<?php
						}
					?>
					<option value="<?php $url_array['ptype'] = "addlinktrack"; echo urlize($url_array);  ?>"><?php echo word("Add Link Track"); ?></option>
					
					
					<?php
						if ($enable_podcast_subscribe == "true"){
					?>
					<option value="<?php $url_array['ptype'] = "addpodcast"; echo urlize($url_array);  ?>"><?php echo word("Podcast Manager"); ?></option>
					<?php
						}
					?>
					
					<option value="<?php $url_array['ptype'] = "setptype"; echo urlize($url_array);  ?>"><?php echo word("Set Page Type"); ?></option>
					<option value="<?php $url_array['ptype'] = "searchlyrics"; echo urlize($url_array);  ?>"><?php echo word("Retrieve Lyrics"); ?></option>
					<option value="<?php $url_array['ptype'] = "getmetadata"; echo urlize($url_array);  ?>"><?php echo word("Retrieve Meta Data"); ?></option>
					<?php
						if ($node->getPType() == "album"){
					?>
						<option value="<?php $url_array['ptype'] = "getalbumart"; echo urlize($url_array);  ?>"><?php echo word("Search for Album Art"); ?></option>
					<?php
						}
					?>		
					<?php
						if ($resize_images == "true"){
					?>															
					<option value="<?php $url_array['ptype'] = "resizeart"; echo urlize($url_array);  ?>"><?php echo word("Resize All Art"); ?></option>
					<?php
						}
					?>
					<option value="<?php $url_array['ptype'] = "autorenumber"; echo urlize($url_array);  ?>"><?php echo word("Auto Renumber"); ?></option>
					<?php
						if (($jzUSER->getSetting('admin') === true || $jzUSER->getSetting('home_admin') == true)
							and ($node->getPType() == "artist" or $node->getPType() == "album")) {
					?>
						<option value="<?php $url_array['ptype'] = "iteminfo"; echo urlize($url_array);  ?>"><?php echo word("Item Information"); ?></option>
					<?php
						}
					?>
					<option value="<?php $url_array['ptype'] = "retagger"; echo urlize($url_array);  ?>"><?php echo word("Retag Tracks"); ?></option>
					<?php
						if ($node->getPType() == "artist" or $node->getPType() == "album"){
							// Ok, is it already featured?
							if (!$node->isFeatured()){
								$url_array['ptype'] = "addfeatured"; 
								echo '<option value="'. urlize($url_array). '">'. word("Add to Featured"). '</option>';
							} else {
								$url_array['ptype'] = "removefeatured"; 
								echo '<option value="'. urlize($url_array). '">'. word("Remove from Featured"). '</option>';
							}
						}
					?>
					<?php
						$url_array['ptype'] = "artfromtags";
						echo '<option value="'. urlize($url_array). '">'. word("Pull art from Tag Data"). '</option>';
					?>
					
					<?php
						if ($node->getPType() == "album"){
					?>
					<option value="<?php $url_array['ptype'] = "pdfcover"; echo urlize($url_array);  ?>"><?php echo word("Create PDF Cover"); ?></option>
					<!--<option value="<?php $url_array['ptype'] = "burncd"; echo urlize($url_array);  ?>"><?php echo word("Burn CD"); ?></option>-->
					<?php
						}
					?>
				</select>
			</form>
			<?php
		}
		
		
		/*
		* Displays the browse dropdown boxes
		*
		* @author Ross Carlson
		* @since 4.16.05
		* @version 4.16.05
		**/
		function displayBrowseDropdown(){
			global $this_page, $hierarchy;
			
			$lvls = @implode("|",$hierarchy);
			?>
			<form action="<?php echo $this_page; ?>" method="GET">
			   <?php
			   $this->hiddenPageVars();
			  ?>
				<select class="jz_select" name="action" style="width:125px" onChange="openPopup(this.form.action.options[this.selectedIndex].value, 250, 400, false, 'MediaManagement')">
					<?php
						// Now let's setup the values
						$url_array = array();
						$url_array['action'] = "popup";
					?>
					<option value=""><?php echo word("Browse"); ?></option>
					<?php
					if (stristr($lvls,"genre")){
						echo '<option value="';
						$url_array['ptype'] = "genre"; 
						echo urlize($url_array). '">'. word("All Genres"). ' ('. number_format($_SESSION['jz_num_genres']). ')</option>'. "\n";
					}
					
					if (stristr($lvls,"artist")){
						echo '<option value="';
						$url_array['ptype'] = "artist"; 
						echo urlize($url_array). '">'. word("All Artists"). ' ('. number_format($_SESSION['jz_num_artists']). ')</option>'. "\n";
					}
					
					if (stristr($lvls,"album")){
						echo '<option value="';
						$url_array['ptype'] = "album"; 
						echo urlize($url_array). '">'. word("All Albums"). ' ('. number_format($_SESSION['jz_num_albums']). ')</option>'. "\n";
					}
					
					echo '<option value="';
					$url_array['ptype'] = "track"; 
					echo urlize($url_array). '">'. word("All Tracks"). ' ('. number_format($_SESSION['jz_num_tracks']). ')</option>'. "\n";
					?>
				</select>
			</form>
			<?php		
		}
		
		
		/*
		* Returns the HTML code for a fancy Overlib tooltip
		*
		* @author Ross Carlson
		* @since 4.05.05
		* @version 4.05.05
		* @param $path The path in the filesystem or the URL to the image
		**/
		function returnToolTip($body, $title){
			$overCode = "'". $body. "', CAPTION, '<nobr>". $title. "</nobr>', DELAY, 300, HAUTO, VAUTO, CAPCOLOR, '". jz_font_color. "', BORDER, 2, BGCOLOR, '". jz_pg_bg_color. "', TEXTCOLOR, '". jz_font_color. "', FGCOLOR, '". jz_fg_color. "'";
			return ' onmouseover="return overlib('. $overCode. ');" onmouseout="return nd();"';
		}
		
		/*
		* Returns the text for the deminesions of an image
		*
		* @author Ross Carlson
		* @since 3.18.05
		* @version 3.18.05
		* @param $path The path in the filesystem or the URL to the image
		**/
		function returnImageDimensions($path){
			$image = @imagecreatefromjpeg($path);
			if ($image){
				return imagesx($image). "x". imagesy($image);
			} else {
				return false;
			}
		}
		
		/*
		* Displays the discussion icon
		*
		* @author Ross Carlson
		* @since 3.18.05
		* @version 3.18.05
		* @param $node The item (node) we are viewing
		**/
		function displayDiscussIcon($node){
			global $img_discuss, $img_discuss_dis;
			
			$item = new jzMediaElement($node->getPath('String'));		
			
			$arr = array();
			$arr['action'] = "popup";
			$arr['ptype'] = "discussitem";
			$arr['jz_path'] = $item->getPath("String");
							
			echo '<a onClick="openPopup(' . "'". urlize($arr) ."'" . ',450,300); return false;" href="javascript:;">';
			if ($item->getDiscussion() == ""){
				echo $img_discuss_dis;
			} else {
				echo $img_discuss;
			}
			echo "</a>";
		}
		
		/*
		* Displays the previous artists/album this user has browsed
		*
		* @author Ben Dodson
		* @since 2/2/05
		* @version 2/2/05
		* @param $node The node we are viewing
		* @param $type The type of drop down to return, Artist or Album
		**/
		function displayPrevDropdown($node, $type){
			global $jzUSER, $this_page, $cms_mode;
			
			// Let's start the form
			echo '<form action="'. $this_page. '" method="GET">'. "\n";
			$this->hiddenPageVars();
			echo '<select style="width:125px" class="jz_select" name="'. jz_encode('jz_path'). '" onChange="form.submit();">';
			if ($type == "artist"){
				echo '<option value="">'. word("Previous Artists"). '</option>';
			}
			if ($type == "album"){
				echo '<option value="">'. word("Previous Albums"). '</option>';
			}
			
			// Now let's load the users history
			$oldHist = $jzUSER->loadData('history');
			
			// Now let's parse that our
			$hArr = explode("\n",$oldHist);
			$allArtists = "";
			for ($i=0; $i < count($hArr); $i++){
				// Now let's break that out
				$dArr = explode("|",$hArr[$i]);
				if ($dArr[0] == $type and !stristr($allArtists,"|". $dArr[1]) and ($node->getName() <> $dArr[1])){
					// Now let's display the option
					echo '<option value="'. jz_encode($dArr[2]). '">'. $dArr[1]. '</option>';
					$allArtists .= "|". $dArr[1];
				}
			}
			echo '</select>';
			echo '</form>';
		}

		/* Starts a settings table.
		 *
		 * @author Ben Dodson
		 * @since 2/2/05
		 * @version 2/2/05
		 * 
		 **/
		function openSettingsTable($action) {
		  echo '<table>';
		  echo '<form action="'.$action.'" method="POST">'."\n";
		}

		/* Closes a settings table.
		 *
		 * @author Ben Dodson
		 * @since 2/2/05
		 * @version 2/2/05
		 * @param writeable: if the file isn't writeable, don't show the update button.
		 **/
		function closeSettingsTable($writeable = true) {
		  echo '<tr><td colspan="2">';
		  echo '<table align="center"><tr width="50%"><td>';
		  if ($writeable) {
		    echo '<input type="submit" class="jz_submit" name="update_postsettings" value="Update">';
		  } else {
		    echo "&nbsp;";
		  }
		  echo '</td><td width="50%">';
		  //echo '<input type="submit" value="Close" name="close" onClick="window.close();opener.location.reload(true);" class="jz_submit">';
		  echo "&nbsp;";
		  echo '</td></tr></table></tr>';
		  echo '</form></table>'."\n";
		}

		/**
		 * Completely handles a field for a setting.
		 * This function will display a checkbox.
		 * for a certain variable.
		 * It also updates the $settings_file variable to modify the setting.
		 *
		 * @author Ben Dodson
		 * @version 3/10/05
		 * @since 3/10/05
		 **/ 
		function settingsCheckbox($label, $varname, &$settings_array, $show_complete = true) {
		  // See if the array needs to be updated (from a form submit)
		  $fieldname = "edit_" . $varname;
		  if (isset($_POST[$fieldname])) {
		    $settings_array[$varname] = "true";
		    if ($show_complete === false) {
		      return;
		    }
		  } else if (isset($_POST['update_postsettings'])) {
		    // Form was submitted and the box was unchecked; it's false.
		    $settings_array[$varname] = "false";
		    if ($show_complete === false) {
		      return;
		    }
		  }

		  echo '<tr><td align="right" valign="top" width="30%">'."\n";
		  echo $label . '&nbsp;';
		  echo '</td><td align="left" width="70%">';
		  echo '<input type="checkbox" name="'.$fieldname.'" class="jz_checkbox"';
		  if (isset($settings_array[$varname]) &&
		     ($settings_array[$varname] === true || $settings_array[$varname] == "true")) {
		    echo ' checked';
		  }
		  echo '>';
		  echo "</td></tr>\n";
		}

		
		/**
		 * Completely handles a field for a setting.
		 * This function will display a form field
		 * for a certain variable.
		 * It also updates the $settings_file variable to modify the setting.
		 *
		 * @author Ben Dodson
		 * @version 2/2/05
		 * @since 2/2/05
		 **/ 
		function settingsTextbox($label, $varname, &$settings_array, $show_complete = true) {
		  // See if the array needs to be updated (from a form submit)
		  $fieldname = "edit_" . $varname;
		  if (isset($_POST[$fieldname])) {
		    $settings_array[$varname] = $_POST[$fieldname];
		    if ($show_complete === false) {
		      return;
		    }
		  }

		  echo '<tr><td align="right" valign="top" width="30%">'."\n";
		  echo $label . '&nbsp;';
		  echo '</td><td align="left" width="70%">';
		  echo '<input name="'.$fieldname.'" class="jz_input"';
		  if (isset($settings_array[$varname])) {
		    echo ' value="' . htmlentities($settings_array[$varname]) . '"';
		  }
		  echo '>';
		  echo "</td></tr>\n";
		}

		/**
		 * Completely handles a field for a setting.
		 * This function will display a dropdown
		 * for a certain variable with given options.
		 * The options are in an array, IE array(field1, field2)
		 * It also updates the $settings_file variable to modify the setting.
		 *
		 * @author Ben Dodson
		 * @version 2/2/05
		 * @since 2/2/05
		 **/ 
		function settingsDropdown($label, $varname, $options, &$settings_array, $show_complete = true) {
		  // See if the array needs to be updated (from a form submit)
		  $fieldname = "edit_" . $varname;
		  if (isset($_POST[$fieldname])) {
		    $settings_array[$varname] = $_POST[$fieldname];
		    if ($show_complete === false) {
		      return;
		    }
		  }

		  echo '<tr><td align="right" valign="top" width="30%">'."\n";
		  echo $label . '&nbsp;';
		  echo '</td><td align="left" width="70%">';
		  echo '<select name="'.$fieldname.'" class="jz_input">';
		  foreach ($options as $type) {
		    echo '<option value="'.htmlentities($type).'"';
		    if ($settings_array[$varname] == $type) {
		      echo " selected";
		    }
		    echo '>' . $type . '</option>';
		  }
		  echo '</select>';
		  echo "</td></tr>\n";
		}

		/**
		 * Completely handles a field for a setting.
		 * This function will display a dropdown
		 * for a certain variable with given options.
		 * The options are the contents of directory $dir
		 * $type is either "dir" or "file"
		 *
		 * @author Ben Dodson
		 * @version 2/2/05
		 * @since 2/2/05
		 **/ 
		function settingsDropdownDirectory($label, $varname, $directory, $type = "dir", &$settings_array, $show_complete = true) {
		  // See if the array needs to be updated (from a form submit)
		  $fieldname = "edit_" . $varname;
		  if (isset($_POST[$fieldname])) {
		    $settings_array[$varname] = $_POST[$fieldname];
		    if ($show_complete === false) {
		      return;
		    }
		  }

		  echo '<tr><td align="right" valign="top" width="30%">'."\n";
		  echo $label . '&nbsp;';
		  echo '</td><td align="left" width="70%">';
		  echo '<select name="'.$fieldname.'" class="jz_input">';
		  $entries = readDirInfo($directory,$type);
		  
		  foreach ($entries as $entry) {
		    if ($entry == "CVS") {
		      continue;
		    }
		    if ($type == "file") {
		      $entry = str_replace(".php","",$entry);
		    }
		    echo '<option value="'.htmlentities($entry).'"';
		    if ($settings_array[$varname] == $entry) {
		      echo " selected";
		    }
		    echo '>';
		    echo $entry;
		    echo '</option>';
		  }
		  echo '</select>';
		  echo "</td></tr>\n";
		}


		/**
		 * Ugly PHP Scripty way to get a link
		 * for a jukebox action. Returns an open
		 * tag that performs a jukebox action
		 * (play/pause/etc.).
		 *
		 * @author Ben Dodson
		 * @since 1/7/08
		 **/
		function getOpenJukeboxActionTag($type) {
		  if (defined('NO_AJAX_JUKEBOX')) {
			  $arr = array();
			  $arr['action'] = "jukebox";
			  $arr['subaction'] = "jukebox-command";
			  $arr['command'] = $type;
			  $arr['ptype'] = "jukebox";
			  $arr['jz_path'] = isset($_GET['jz_path']) ? $_GET['jz_path'] : '';
			  
			  if (isset($_GET['frame'])){
			    $arr['frame'] = $_GET['frame'];
			  }			

			  return '<a href="'. urlize($arr). '"';
			} else {
			  // AJAX:
			  return '<a href="javascript:;" onClick="sendJukeboxRequest(\''.$type.'\'); return false;"';
			}
		}

		/**
		* Displays the Jukebox play button
		* 
		* 
		* @author Ross Carlson
		* @version 2/9/05
		* @since 2/9/05
		* @param $node the Node we are looking at
		* @param $type The type of button to display
		*/
		function displayJukeboxButton($type){
			global $img_jb_play, $img_jb_pause, $img_jb_stop, 
			  $img_jb_previous, $img_jb_next, $img_jb_random_play, 
			  $img_jb_clear,$img_jb_repeat,$img_jb_no_repeat; 

			$image = 'img_jb_'.$type;
			$aTag = $this->getOpenJukeboxActionTag($type);
			echo $aTag . '>'.$$image.'</a>';
		}
		
		/*
		 * Checks to see if the resample dropdown should be shown.
		 * Also sets the variables required for resampling.
		 * 
		 * @author Ben Dodson
		 * @since 8/16/2006
		 */
		function wantResampleDropdown($node) {
			global $allow_resample, $resampleRates, $this_page, $jzUSER, $no_resample_subnets;
			
			if ($allow_resample <> "true"){return false;}
			
			if (!checkPermission($jzUSER,'stream',$node->getPath("String"))) {
				return false;
			}			
			if (checkPlayback() == "jukebox") {
				return false;
			}			
			if (isset($no_resample_subnets) && $no_resample_subnets <> "" && preg_match("/^${no_resample_subnets}$/", $_SERVER['REMOTE_ADDR'])) {
				return false;
			}
			
			// Now let's see if they set the resample rate
			if (isset($_POST['jz_resample'])){
				// Ok, its set so let's set the session var for it
				$_SESSION['jz_resample_rate'] = $_POST['jz_resample'];
			}
			if (isset($_SESSION['jz_resample_rate'])){
				if ($_SESSION['jz_resample_rate'] == ""){
					unset($_SESSION['jz_resample_rate']);
				}
			}
			if ($jzUSER->getSetting('resample_lock')){
				$_SESSION['jz_resample_rate'] = $jzUSER->getSetting('resample_rate');
				return false;
			}
			
			return true;
		}
		
		/**
		* Displays the resample dropdown box and related code
		* 
		* 
		* @author Ross Carlson
		* @version 11/21/04
		* @since 11/21/04
		* @param $node The node that we are getting the rating for
		*/
		function displayResampleDropdown($node, $title = true, $return = false){
			global $allow_resample, $resampleRates, $this_page, $jzUSER,$fe;
			
			
			// First let's see if we should not show this
			if (!$this->wantResampleDropdown($node)) {
				return;
			}
			
			if ($return) {
		 		ob_start();
			}	
			
			$smarty = smartySetup();
			
			if ($title !== false){
				if ($title === true) {
					$title = "";
					$title .= '<font style="font-size:11px">';
					$title .= word("Resample Rate:");
					$title .= '</font><br>';
				}
			}
			$smarty->assign('title',$title);
			
			$arr = array();
			$arr['jz_path'] = $_GET['jz_path'];

			if (defined('NO_AJAX')) {
				$smarty->assign('onchange','form.submit()');
			} else {
				$smarty->assign('onchange',"return setResample(document.getElementById('resamplerate').value);");
			}
			
			$smarty->assign('form_action',urlize($arr));
			
			if (isset($_SESSION['jz_resample_rate'])){
				$smarty->assign('cur_rate',$_SESSION['jz_resample_rate']);
			} else {
				$smarty->assign('cur_rate','');
			}			
			
			$smarty->assign('resample_rates',explode("|",$resampleRates));
			
			jzTemplate($smarty, 'block-resample');
			
			if ($return) {
			  $var = ob_get_contents();
			  ob_end_clean();
			  return $var;
			}			
		}

			/**
			* The dropdown for the interface selector
			*
			* @author Ben Dodson
			* @version 3/17/05
			* @since 3/17/05
			*
			**/
			function interfaceDropdown() {
				global $this_page,$include_path;
				
				?>
				<form action=<?php echo $_SERVER['PHP_SELF'] ?> method="GET" name="interface">
					<?php 
						$this->hiddenVariableField("jz_path");
						//$this->hiddenVariableField("theme");
						$this->hiddenPageVars(); 
					?>
					<select class="jz_select" name="<?php echo jz_encode("set_frontend"); ?>" style="width:125px" onChange="submit();" >
						<option value=""><?php echo word('Interface') ?></option>
						<?php
							// Now let's get all the possibles
							$retArray = readDirInfo($include_path. "frontend/frontends","dir");
							sort($retArray);
							for ($c=0; $c < count($retArray); $c++){	
								$entry = $retArray[$c];
								// Let's make sure this isn't the local directory we're looking at 
								if ($entry == "." || $entry == ".." || $entry == "CVS" || $entry == "jukezora") { continue;}
								echo '<option value="'. jz_encode(str_replace(".php","",$entry)). '">'. str_replace(".php","",$entry). '</option>'. "\n";
							}
						?>
					</select>
				</form> 
				<?php
			}
		

		/**
		 * The dropdown for the language selector.
		 *
		 * @author Ben Dodson
		 * @since 3/17/05
		 * @version 3/17/05
		 *
		 **/
		function languageDropdown() {
		  global $web_root,$root_dir,$this_page;
		    ?>
		    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="GET" name="language">
				 <?php  
					$this->hiddenVariableField("jz_path");
					$this->hiddenPageVars();

					// Now let's get all the possibles			      
					$languages = getLanguageList();
					?>
				 <select class="jz_select" name="<?php echo jz_encode("set_language"); ?>" style="width:125px" onChange="submit()">
			  <option value="">Language</option>
			  <?php
		    foreach ($languages as $language) {
		      echo '<option value="'. jz_encode(str_replace(".php","",$language)). '">'. str_replace(".php","",$language). '</option>'. "\n";  
		    }
		    ?>
		   	</select>
		   	</form>
		 		<?php
		}
		

		/**
		 * The dropdown for the style selector.
		 *
		 * @author Ben Dodson
		 * @since 3/17/05
		 * @version 3/17/05
		 *
		 **/
		function styleDropdown() {
			global $this_page,$include_path, $cms_mode;
			
			// Not in CMS mode...
			if ($cms_mode == "true"){
				return;
			}
       
			?>
			<form action="<?php echo $this_page; ?>" method="GET" name="style">
				<?php 
					$this->hiddenVariableField('jz_path');
					$this->hiddenPageVars();
					$this->hiddenVariableField('frontend');
				?>
				<select class="jz_select" name="<?php echo jz_encode("set_theme"); ?>" style="width:125px" onChange="submit()">
				<option value=""><?php echo word("Style"); ?></option>			
				<?php
					// Now let's get all the possibles
					$retArray = readDirInfo($include_path. "style","dir");
					sort($retArray);
					for ($c=0; $c < count($retArray); $c++){	
					$entry = $retArray[$c];
					// Let's make sure this isn't the local directory we're looking at 
					if ($entry == "." || $entry == "..") { continue;}
						if (!stristr($entry,"images") and !stristr($entry,"cms-theme") and !stristr($entry,"CVS")){
							echo '<option value="'. jz_encode(str_replace(".php","",$entry)). '">'. str_replace(".php","",$entry). '</option>'. "\n";
						}
					}
				?>
				</select>
			</form>
			<?php
			return;
			?>
			<script>
				function setActiveStyleSheet(title) {
					 var i, a, main;
					 for(i=0; (a = document.getElementsByTagName("link")[i]); i++) {
						 if(a.getAttribute("rel").indexOf("style") != -1
								&& a.getAttribute("title")) {
							 a.disabled = true;
							 if(a.getAttribute("title") == title) a.disabled = false;
						 }
					 }
				}
				
				function selectStyle (vCookieName, vSelection) {
					//WRITE COOKIE
					//makeCookie(vCookieName, vSelection, 90, '/');
					//ACTIVE SELECTED ALTERNAT STYLE SHEET
					setActiveStyleSheet(vSelection)
				}
				
				if (document.cookie.indexOf('layout=')!=-1) {
					css = readCookie('layout');
					//ACTIVATE SELECTED STYLE SHEET
					setActiveStyleSheet(css)
				}
			</script>
			<select class="jz_select" onchange="var v=this.options[this.selectedIndex].value; if (v != '') selectStyle('style', v);" style="width:125px" >
				<option value=""><?php echo word("Style"); ?></option>
				<?php
					$styles = readDirInfo($include_path. "style", "dir");
					foreach ($styles as $style){
						echo '<option value="'. $style. '">'. ucwords($style). '</option>';
					}
				?>
			</select>
			<?php
		}

		/**
		* Displays the button to allow for an item to be rated
		* 
		* 
		* @author Ross Carlson
		* @version 11/20/04
		* @since 11/20/04
		* @param $node The node that we are getting the rating for
		* @param $return Should we return the code or echo (default to echo)
		*/
		function rateButton($node, $return = false){
			global $img_rate, $jzUSER, $img_rate_dis, $enable_ratings;
			
			// First off can they rate?
			if (!$jzUSER->getSetting('stream')){
				return;
			}
			if ($enable_ratings <> "true"){
				return;
			}
			
			$arr = array();
			$arr['action'] = "popup";
			$arr['ptype'] = "rateitem";
			$arr['jz_path'] = $node->getPath("String");
							
			$retVal = '<a onClick="openPopup(' . "'". urlize($arr) ."'" . ',350,150); return false;" href="javascript:;">';
			$retVal .= $img_rate;
			$retVal .= "</a>";
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}
		

	  function emailButton($node) {
	    global $img_email,$this_site;

	    $arr = array();
	    $arr['action'] = "playlist";
	    $arr['jz_path'] = $node->getPath("String");
	    if ($node->isLeaf()) {
	      $arr['type'] = "track";
	    }
	    $link = $this_site. urlencode(urlize($arr));
	    
	    $artist = getInformation($node,"artist");
	    $album = getInformation($node,"album");
	    // hack : make 'album' trackname if that's what we're playing.
	    if ($node->isLeaf()) {
	      $album = $node->getName();
	    }
	    $mailLink = "mailto:?subject=". $artist. " - ". $album. "&body=Click to play ". 
	      $artist. " - ". $album . ":%0D%0A%0D%0A".
	      $link. "%0D%0A%0D%0APowered%20by%20Jinzora%20%0D%0AJinzora%20::%20Free%20Your%20Media%0D%0Ahttp://www.jinzora.org";
	      ?>
	      <a class="jz_track_table_songs_href" href="<?php echo $mailLink; ?>"><?php echo $img_email; ?></a>
              <?php
	  }



		/**
		* Displays or returns the rating for an object
		* 
		* 
		* @author Ross Carlson
		* @version 11/20/04
		* @since 11/20/04
		* @param $node The node that we are getting the rating for
		* @param $return Return the data or display (true = return)
		*/
		function rating($node, $return = false){
			global $img_star_full, $img_star_half_empty, $img_star_left, $img_star_right, $img_star_full_empty, $include_path, $img_star_full_raw, $img_star_empty_raw;
			
			// Let's increment the counter
			$_SESSION['jz_stars_group']++;
			
			// Let's grab the rating from the node
			$rating = estimateRating($node->getRating());
			
			// First lets see if we should just return if this is nothing
			if (!$rating){return;}
						
			// Now let's start the rating icon
			$retVal .= $img_star_left;
			$total_rating = $rating;
		
			for ($i = floor($rating); $i > 0; $i--){
				$retVal .= $img_star_full;
			}
			if ($rating - floor($rating) > 0.25){
				$retVal .= $img_star_half_empty;
			}
			// Now we need to finish this off to make 5
			for ($i = ceil($rating); $i < 5; $i++) {
				$retVal .= $img_star_full_empty;
			}					
			
			// Now let's finish it off
			$retVal .= $img_star_right;
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}

		// strlen() that ignores html text
		function plaintextStrlen( $text ) {
			$stripped = preg_replace( '/<.*?>/', "", $text );
			return strlen( $stripped );
		}

		// substr() that ignores html text
		// TODO: change this so that the signature matches that of substr (e.g., add $begin)
		function plaintextSubstr( $text, $end ) {
		  if ($text === false) return false;

			// Only need to search starting from the first html tag
			$currentPos = strpos( $text, "<" );

			if( !$currentPos || $currentPos > $end ) {
				// Plain text (or html occurs after $end); just return a substring
				return substr( $text, 0, $end );
			}

			// Number of chars of non-body data we've seen so far
			$nonDisplayTextCounter = 0;

			// Used to keep track of whether we're looking at plain text or tag body text
			$inTagBody = false;

			$textFullLength = strlen( $text );
			while( $currentPos <= $textFullLength  ) {

				// CASE 1: Tag opening
				if( $text[$currentPos] == '<' ) {
					if( !$inTagBody ) {
						$inTagBody = true;
						$nonDisplayTextCounter++;
					} else {
						// This is only reachable for malformatted html, e.g. "<a = <stuff>"
						writeLogData( "messages", "Warning: found possibly malformatted open tag." );
					}

				// CASE 2: Tag closing
				} else if( $text[$currentPos] == '>' ) {
					if( $inTagBody ) {
						$inTagBody = false;
						$nonDisplayTextCounter++;
					} else {
						// Malformatted html (e.g. "<b> stuff >") or real greater-than symbol meaning
						writeLogData( "messages", "Warning: found possibly malformatted close tag." );
					}

				// Case 3: plain text, either body text or tag text
				} else {
					if( $inTagBody ) {
						$nonDisplayTextCounter++;
					}
				}

				// Stop once we find $end characters 
				if( ($currentPos-$nonDisplayTextCounter) == $end ) {
					break;
				}

				$currentPos++;
			}

			return substr( $text, 0, $currentPos);
}
		
		/**
		* Returns a shortened version of the given $text.
		* 
		* 
		* @author Ross Carlson
		* @version 11/17/04
		* @since 11/17/04
		* @param $text string The text that we want to truncate
		* @param $length int The length for the text to be truncated to
		*/
		function returnShortName($text, $length){
			if ($text === false) return false;
			if( $this->plaintextStrlen( $text ) <= $length ) {
		   	return $text;
			} else {
		    // Ok, we need to make sure we don't break within a tag
				$shortName = $this->plaintextSubstr( $text, $length );
				$retText = substr($text,0,strlen( $shortName ) );
		   	while (strrpos($retText,">") < strrpos($retText,"<")){
		     	// Ok, we were in a tag, so we need to increase until we aren't
		     	$length = $length -1;
		     	$retText = substr($text,0,$length);
		   	}
		    return trim($retText). "...";
			}
		}
	  
		/**
		* Displays a link for the node. (or a playlink for the path)
		* 
		* 
		* @author Ben Dodson
		* @version 11/4/04
		* @since 11/4/04
		*/
		function link($node, $text = false, $title = false, $class = false, $return = false, $linkonly = false, $playRandom = false, $playlist = false, $target = false) {		
		  if (!is_object($node)) {
		    return false;
		  }
			$arr = array();
			$arr['jz_path'] = $node->getPath("String");
			if ($node->isLeaf()) {
			  $this->playLink($node, $text, $title, $class, $return);
			  return;
			}
			if ($playRandom){
				$arr['mode'] = "random";
			}
			if ($playlist){
				$arr['action'] = "playlist";
			}
			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}
			
			if (!defined('NO_AJAX_LINKS')) {
			  $arr['maindiv'] = "true";
			}
			
			// Let's start the link
			if (!$linkonly){
			  if (defined('NO_AJAX_LINKS')) {
			    $linkText = '<a href="' . urlize($arr). '"';
			  } else {
			    $linkText = '<a href="javascript:maindiv(\''.urlencode(urlize($arr)).'\')"';
			  }
			} else {
			  if (defined('NO_AJAX_LINKS')) {
			    $linkText = urlize($arr);
			  } else {
			    $linkText = 'javascript:maindiv(\''.urlencode(urlize($arr)).'\')';
			  }
			  return $linkText;
			}
			
			// Did they pass text or do we need to figure it out?
			if (!$text) {
				$text = $node->getName();
				if (!$node->isLeaf())
					if ($node->getSubNodeCount() <> 0)
						$text .= " (" . $node->getSubNodeCount() . ")";
			}
			// Did they pass a title?
			if ($title){
				$linkText .= ' title="'. $title. '" alt="'. $title. '"';
			}
			
			// Did they pass a class?
			if ($class){
				$linkText .= ' class="'. $class. '"';
			}
			
			// Did they want a target?
			if ($target){
				$linkText .= ' target="'. $target. '"';
			}
			
			// Now let's finish out
			$linkText .= '>'. $text. '</a>';
			
			// Now let's echo it out
			if (!$return){
				echo $linkText;
			} else {
				return $linkText;
			}
		}
		
		/**
		* Displays a link for the node. (or a playlink for the path)
		* 
		* 
		* @author Ben Dodson
		* @version 11/30/04
		* @since 11/30/04
		* @param $node Object the node we are looking at
		* @param $test String The text for the link
		* @param $title String The title of the line
		* @param $class String the class for the link
		* @param $return Bolean Return or not (defalt to false)
		*/

		// returns an assoc array with keys 'href' and 'onclick'
		function getOpenAddToListTag($e) {
		  if ($e->isLeaf()) {
		    $type = 'track';
		  } else {
		    $type = 'node';
		  }
		  return "<a href=\"\" onclick=\"ajax_direct_call('".urlize(array('action'=>'addToPlaylist','jz_path'=>$e->getPath('String'),'type'=>$type))."',updatePlaylist_cb); return false;\" ";
		}
		function getOpenPlayTag($node, $random = false, $limit = 0) { 
		  global $jzUSER,$jzSERVICES,$jukebox;
		  if (!is_object($node)){return;}
		  
		  // do they have permissions or should we just do text?
		  if ($node instanceof jzMediaElement && !checkPermission($jzUSER,"play",$node->getPath("String"))) {
		    return null;
		  } 

		  $href = $node->getPlayHREF($random,$limit);
		  // Let's start the link
		  if (defined('NO_AJAX_JUKEBOX') || $jukebox == "false") {
		    $linkText = '<a href="' . $href. '"';
		    // Now are they using a popup player?
		    if (checkPlayback() == "embedded"){
		      // Ok, let's put the popup in the href
		      // We need to get this from the embedded player
		      $linkText .= $jzSERVICES->returnPlayerHref();
		    }
		  } else {
		    $linkText = '<a href="'. htmlentities($href) . '"';
		    $linkText .= "onclick=\"return playbackLink('".htmlentities($href)."')\"";
		  }

		  return $linkText;
		}
		  
		  

		function playLink($node, $text = false, $title = false, $class = false, $return = false, $random = false, $linkOnly = false, $clips = false, $limit = 0) {		
			global $jzUSER, $jzSERVICES,$jukebox;

			$linkText = $this->getOpenPlayTag($node,$random,$limit);
			if ($linkText == null) return null;

			// Did they pass a title?
			if ($title){
				$linkText .= ' title="'. $title. '" alt="'. $title. '"';
			}
			if ($class){
				$linkText .= ' class="'. $class. '"';
			}			
			
			// Now let's finish out
			$linkText .= '>'. $text. '</a>';
			
			// Now let's echo it out
			if (!$return){
				echo $linkText;
			} else {
				return $linkText;
			}
		}
		
		
		/**
		* Displays a podcast link for a node
		* 
		* 
		* @author Ross Carlson
		* @version 7/8/2005
		* @since 7/8/2005
		* @param $node Object the node we are looking at
		*/
		function podcastLink($node, $return = true) {		
			global $root_dir, $img_podcast, $enable_podcast, $web_dirs, $this_site;
			
			if (!is_object($node)){return;}
			if ($enable_podcast <> "true"){return;}
			
			$site = str_replace("http://","",$this_site);
			$site = str_replace("https://","",$site);
			
			if ($return){
				return '<a href="itpc://'. $site. $root_dir. '/podcast.rss?jz_path='. $node->getPath("String"). '">'. $img_podcast. '</a>';
			} else {
				echo '<a href="itpc://'. $site. $root_dir. '/podcast.rss?jz_path='. $node->getPath("String"). '">'. $img_podcast. '</a>';
			}
		}
		
		
		/**
		* Displays a download button for the node or track or playlist.
		* 
		* 
		* @author Ross Carlson
		* @version 12/01/04
		* @since 12/01/04
		*/
		function downloadButton($node, $return = false, $returnImage = false, $showSize = false, $linkOnly = false) {			
			global $img_download, $img_download_dis, $jzUSER, $jzSERVICES, $allow_resample_downloads, $allow_resample, $display_downloads;

			if ($display_downloads == "false") {
				return;
			}	
			if (!$jzUSER->getSetting('download')){
			  if ($return){
			    return $img_download_dis;
			  } else {
			    echo $img_download_dis;
			    return;
			  }
			}

			if (!($node->getType() == "jzPlaylist" || $node->isLeaf() || $node->getPType() == "album")) return;

			$arr = array();
			$arr['action'] = "download";
			
			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}

			if ($node->getType() == "jzPlaylist") {
			  $arr['type'] = "playlist";
			  $arr['jz_pl_id'] = $node->getID();
			} else {
			  $arr['jz_path'] = $node->getPath("String");	
			  if ($node->isLeaf()) {
			    $arr['type'] = "track";
			  }
			}
			// NOTE: be careful because this could be a downloadButton for a jzPlaylist.
			//$fileExt = substr($node->getPath("String"),-3);
			
			// Now let's get the stats so we'll know how big this album is
			if ($showSize){
				$size = $node->getStats("total_size_str");
			}
			
			// Now, do we need to make this a popup page?
			// This is used for transcoding downloading
			// First is this an album or a track?
			if ($node->getType() <> "jzPlaylist"){
				if (!$node->isLeaf()){
					// Ok, it's an album so we need to get the first track from it
					// We'll assume all the tracks are the same format
					$tracks = $node->getSubNodes("tracks",-1,false,1);
					// Now let's make sure we got data back
					if (is_object($tracks[0])){
						$trackPath = $tracks[0]->getPath("string");
					} else {
						$trackPath = NULL;
					}
				} else {
					$trackPath = $node->getPath("String");
				}
			}
			if ($jzSERVICES->isResampleable($trackPath) and $allow_resample_downloads == "true" and $allow_resample == "true"){
				$arr['action'] = "popup";
				$arr['ptype'] = "downloadtranscode";
				$arr['jz_path'] = $node->getPath("String");
				$popupAddon = ' target="_blank" onclick="openPopup(this, 400, 400); return false;" ';	
			} else {
				$popupAddon = "";
			}
			
			if ($linkOnly){
				return urlize($arr);
			}
			
			if (!$return){	
				$message = word("Download"). ": ". $node->getName();
				if (isset($size)){
					$message .= " : ". $size;
				}
				if ($returnImage) {
					return '<a title="'. $message. '" href="'. urlize($arr) . '" '. $popupAddon. '>' . $img_download .  '</a>';   
				}
				echo '<a title="'. $message. '" href="'. urlize($arr). '"'. $popupAddon. '>';
				echo $img_download;
				echo "</a>";
			} else {
				if ($returnImage) {
					return '<a title="'. $message. '" href="'. urlize($arr) . '" '. $popupAddon. '>'. $img_download. '</a>';   
				} else {
					return urlize($arr);
				}
			}
		}
		
		/**
		* Displays a clip play button for the node or track or playlist.
		* 
		* 
		* @author Ross Carlson
		* @version 3/07/05
		* @since 3/07/05
		* @param $node the Node we are looking at
		*/
		function clipPlayButton($node) {			 
			global $img_clip, $jzUSER, $jzSERVICES;
			
			if ($node->getType() == "jzPlaylist" || !$node->isLeaf()) {
				return;
			}

			$arr = array();
			$arr['action'] = "playlist";			

			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}
			
			$arr['jz_path'] = $node->getPath("String");	
			$arr['type'] = "track";
			$arr['clip'] = "true";

			echo '<a href="' . urlize($arr) . '"';

			// Now are they using a popup player?
			if (checkPlayback() == "embedded"){
				// Ok, let's put the popup in the href
				echo $jzSERVICES->returnPlayerHref();
			}

			echo '>';			
			echo $img_clip;
			echo "</a>";
		}
		
		
		/**
		* Displays a lofi play button for the node or track or playlist.
		* 
		* 
		* @author Ross Carlson
		* @version 3/07/05
		* @since 3/07/05
		* @param $node the Node we are looking at
		*/
		function lofiPlayButton($node, $limit = false, $text = false, $onclick = false) {			
			global $img_lofi, $jzUSER, $jzSERVICES;
			
			if ($jzUSER->getSetting('lofi') === false) {
			  //return;
			}

			$arr = array();
			$arr['action'] = "playlist";
			
			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}
			
			if ($node->getType() == "jzPlaylist") {
				$arr['type'] = "playlist";
				$arr['pl_id'] = $node->getID();
			} else {
				$arr['jz_path'] = $node->getPath("String");	
				if ($node->isLeaf()) {
					$arr['type'] = "track";
				} else {
				  if ($limit !== false) {
				    $arr['limit'] = $limit;
				  }
				}
			}
			echo '<a href="' . urlize($arr) . '"';
			if ($onclick){
				echo 'onclick="'. $onclick. '"';
			}
			// Now are they using a popup player?
			if (checkPlayback() == "embedded"){
				// Ok, let's put the popup in the href
				echo $jzSERVICES->returnPlayerHref();
			}
			
			echo '>';			
			echo $img_lofi;
			echo "</a>";
		}
		

		/**
		* Displays a play button for the node or track or playlist.
		* 
		* 
		* @author Ben Dodson
		* @version 1/15/05
		* @since 11/4/04
		* @param $node the Node we are looking at
		* @param $limit should we limit the items to play (default is false)
		* @param $text The text to display in the link
		*/
		function playButton($node, $limit = false, $text = false, $onclick = false, $return = false) {  
		  global $img_play, $img_play_dis, $jzUSER, $jzSERVICES,$jukebox;
		  
		  if (!is_object($node)) {
		    return false;
		  }
			if ($node->getType() == "jzMediaNode" || $node->getType() == "jzMediaTrack") {
			  $path = $node->getPath("string");
			} else {
			  $path = "";
			}
			// First we need to know if this is a view only user or not
			if (checkPermission($jzUSER,'play',$path) === false){
				if ($return){
					return $return;
				} else {
					echo $img_play_dis;
					return;
				}
			}
			$retVal='';
			
			$arr = array();
			$arr['action'] = "playlist";
			
			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}
			
			if ($node->getType() == "jzPlaylist") {
				$arr['type'] = "playlist";
				$arr['pl_id'] = $node->getID();
			} else {
				$arr['jz_path'] = $node->getPath("String");	
				if ($node->isLeaf()) {
					$arr['type'] = "track";
				} else {
				  if ($limit !== false) {
				    $arr['limit'] = $limit;
				  }
				}
			}
			if (!defined('NO_AJAX_JUKEBOX') && $jukebox != "false") {
			  $retVal .= '<a href="'. htmlentities(urlize($arr)) . '"';
			  $retVal .= " onClick=\"return playbackLink('".htmlentities(urlize($arr))."')\"";
			} else {
			  $retVal .= '<a href="' . urlize($arr) . '"';
			  if ($onclick){
			    $retVal .= ' onclick="'. $onclick. '" ';
			  }
			  // Now are they using a popup player?
			  if (checkPlayback() == "embedded"){
			    // Ok, let's put the popup in the href
			    $retVal .= $jzSERVICES->returnPlayerHref();
			  }
			}

			$retVal .= '>';			
			if ($text === false) {
			  $retVal .= $img_play;
			} else {
			  $retVal .= $text;
			}
			$retVal .= "</a>";
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}

		/*
		 * Track information button
		 *
		 * @author Ben Dodson
		 * @since 2/20/05
		 * @version 2/20/05
		 *
		 **/
		function infoButton($track) {
		  global $img_more;

		  $arr = array();
		  $arr['action'] = "popup";
		  $arr['ptype'] = "trackinfo";
		  $arr['jz_path'] = $track->getPath("String");
		  $link = urlize($arr);
		  echo '<a href="' . $link . '" target="_blank" onclick="openPopup(this, 375, 650); return false;">' . $img_more . '</a>';
		}

		/*
		 * Displays a button linking to the home page.
		 *
		 **/
		function homeButton() {
		  global $img_home;
		  echo '<a href="' . urlize(array()) . '">';
		  echo $img_home;
		  echo '</a>';
		}	
		
		/**
		* Displays a the radio button for the node and it's similar nodes
		* 
		* 
		* @author Ross Carlson
		* @version 01/15/05
		* @since 01/10/05
		*/
		function radioPlayButton($node, $limit = false, $text = false) {			
			global $img_play, $jzUSER, $img_play_dis, $jzSERVICES;
			
			if (checkPermission($jzUSER,'play',$node->getPath('String')) === false){
				echo $img_play_dis;
				return;
			}
			
			$simArray = $jzSERVICES->getSimilar($node);
			$simArray = seperateSimilar($simArray);
			
			if (sizeof($simArray['matches']) == 0) {
				return false;
			}
			
			$arr = array();
			$arr['action'] = "playlist";
			$arr['mode'] = "radio";
			if ($limit !== false) {
			  $arr['limit'] = $limit;
			}
										
			if ($node->getType() == "jzPlaylist") {
				$arr['type'] = "playlist";
			} else {
				$arr['jz_path'] = $node->getPath("String");
			}
			
			echo '<a href="' . urlize($arr) . '"';
			// Now are they using a popup player?
			if (checkPlayback() == "embedded"){
				// Ok, let's put the popup in the href
				echo $jzSERVICES->returnPlayerHref();
			}
			echo '>';
			if ($text === false) {
			  echo $img_play;
			}
			else {
			  echo $text;
			}
			echo "</a>";
			return true;
		}	
		
		/**
		* Displays a random play button for the node.
		* 
		* 
		* @author Ben Dodson
		* @version 1/15/05
		* @since 11/4/04
		*/
		function randomPlayButton($node, $limit = false, $text = false, $onclick = false, $return = false) {  
		  global $img_random_play, $img_random_play_dis, $jzUSER, $jzSERVICES,$jukebox;

		  if (!is_object($node)) {
		    return false;
		  }

		  if ($node->getType() == "jzMediaNode" || $node->getType() == "jzMediaTrack") {
		    $path = $node->getPath("string");
		  } else {
		    $path = "";
		  }
			// First we need to know if this is a view only user or not
			if (checkPermission($jzUSER,'play',$path) === false){
				if ($return){
					return $img_random_play_dis;
				} else {
					echo $img_random_play_dis;
					return;
				}
			}
			$retVal='';
			
			$arr = array();
			$arr['action'] = "playlist";
			$arr['mode'] = "random";
			if ($limit !== false) {
			  $arr['limit'] = $limit;
			}
			if (isset($_GET['frame'])){
				$arr['frame'] = $_GET['frame'];
			}
							
			if ($node->getType() == "jzPlaylist") {
				$arr['type'] = "playlist";
			} else {
				$arr['jz_path'] = $node->getPath("String");
			}
			
			// So links can be copy/pasted when not in jukebox:
			if ($jukebox == "false") {
			  $retVal .= '<a href="' . urlize($arr) . '"';
			  if ($onclick){
			    $retVal .= 'onclick="'. $onclick. '"';
			  }
			  // Now are they using a popup player?
			  if (checkPlayback() == "embedded"){
			    // Ok, let's put the popup in the href
			    $retVal .= $jzSERVICES->returnPlayerHref();
			  }
			} else {
			  $retVal .= '<a href="'. htmlentities(urlize($arr))  . '"';
			  $retVal .= " onClick=\"return playbackLink('".htmlentities(urlize($arr))."')\"";
			}
			$retVal .= '>';			
			if ($text === false) {
			  $retVal .= $img_random_play;
			} else {
			  $retVal .= $text;
			}
			$retVal .= "</a>";
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}
		
		/**
		 * Displays a button for the node's statistics
		 *
		 * @author Ben Dodson, Ross Carlson
		 * @since 1/29/05
		 * @version 1/29/05
		 *
		 **/
		function statsButton($node, $text = false, $return = false) {
		  global $img_more, $jzUSER;
		  
		  if (!$jzUSER->getSetting('admin')){return;}

		  $arr = array();
		  $arr['jz_path'] = $node->getPath("String");
		  $arr['action'] = "popup";
		  $arr['ptype'] = "nodestats";

		  if ($text === false) {
		    $text = $img_more;
		  }
		  $linkText = ' <a href="'. urlize($arr). '" onclick="openPopup(this, 450, 450); return false;">'. $text. '</a>';
		  if ($return) {
		    return $linkText;
		  }
		  else {
		    echo $linkText;
		  }

		}

		/**
		 * Displays a button to add the jz_list to the currently chosen playlist.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12/05
		 **/
		function addListButton($return = false){
			global $root_dir, $skin,$this_page;
			static $my_id = 0;

			$label = 'addbutton'.++$my_id;
			$retVar  = '<input type="button" style="display:none;" id="'.$label.'" value="true" name="' . jz_encode("addList") . '"/>';
						
			$onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';

			$retVar .= icon('add',array( 'title'=> word('Add to'),
						     'onclick'=> $onclick
						     )
					);


			if ($return){
				return $retVar;
			} else {
				echo $retVar;
			}
		}
		
		/**
		 * Displays a select box with all the playlists listed
		 *
		 * @author Ross Carlson
		 * @version 1/13/05
		 * @since 1/13/05
		 * @param $width the width, in pixels, of the select box
		 * @param onclick: whether or not to submit on select.
		 * @param type: the type of playlists to show: static|dynamic|all
		 **/
		function playlistSelect($width, $onchange = false, $type = "static", $session_pl = true, $varname = "jz_playlist", $return = false){
		  global $jzUSER;
			
			$display = new jzDisplay();
			$retVal = "";
			
			$retVal .= $display->openSelect($varname, $width, $onchange, true);
			if ($session_pl && ($type != "dynamic")) {
			  $retVal .= '<option value= "session">'. word(" - Session Playlist - "). '</option>'. "\n";
                        }
			$lists = $jzUSER->listPlaylists($type);
			foreach ($lists as $id=>$pname) {
			  $retVal .= '<option value="'.$id.'"';
			  if ($_SESSION['jz_playlist'] == $id) { $retVal .= " selected"; } 
			  $retVal .= '>' . $pname . '</option>'."\n";
			}
			$retVal .= $display->closeSelect(true);
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}

		/**
		 * Displays a button to play the jz_list as a playlist.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12/05
		 * @param $random Should the playlist we create be random?
		 **/
		function sendListButton($random = false, $return = true) {
			global $root_dir, $skin,$this_page;
			static $my_id = 0;
			
			$label = 'playnowbutton'.++$my_id;
			$retVal = '<input type="button" style="display:none;" id="'.$label.'" value="true" name="';
			if ($random){
				$retVal .= jz_encode("sendListRandom");
				$title = word("Randomize selected");
				$icon = 'random';
			} else {
				$retVal .= jz_encode("sendList");
				$title = word("Play selected");
				$icon = 'play';
			} 
			$retVal .= '"';

			$onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';

                        $retVal .= icon($icon,array( 'title'=> word($title),
                                                     'onclick'=> $onclick
                                                     )
                                        );


			if ($return) {
			  return $retVal;
			} else {
			  echo $retVal;
			}
		}
		
		/**
		 * A form button to play the current playlist.
		 *
		 * @author Ben Dodson
		 * @since 4/23/05
		 **/
		function playListButton($return = false) {
			global $jzSERVICES,$this_page;
			static $my_id = 0;
			
			$label = "playlistbutton" . ++$my_id;
			$retVal = '<input id="'.$label.'" style="display:none;" type="submit" name="'.jz_encode("playplaylist").'" value="'.jz_encode('normal').'">';
			if (!defined('NO_AJAX_JUKEBOX')) {
			  $onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';
			} else if (checkPlayback() == "embedded"){
			  // Ok, let's put the popup in the href
			  $onclick = $this->embeddedFormHandler('playlistForm');
			}
			
			$retVal .= icon('play',array( 'title'=> word('Play'),
						      'onclick'=> $onclick
						      )
					);
			if ($return){
			  return $retVal;
			} else {
			  echo $retVal;
			}
		}

		/**
		 * A form button to play the current playlist randomly.
		 *
		 * @author Ben Dodson
		 * @since 4/23/05
		 **/
		function randomListButton($return = false) {
		  	global $this_page;
			static $my_id = 0;

                        $label = "randomizebutton" . ++$my_id;
			$retVal = '<input type="submit" style="display:none;" name="'.jz_encode("playplaylist").'" value="'.jz_encode('random').'">';
			
			if (!defined('NO_AJAX_JUKEBOX')) {
			   $onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';
			} else if (checkPlayback() == "embedded"){
			  // Ok, let's put the popup in the href
			  $onclick = $this->embeddedFormHandler('playlistForm');
			}
			 
			$retVal .= icon('random',array( 'title'=> word('Play Random'),
                                                      'onclick'=> $onclick
                                                      )
                                        );

			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}
		
		/**
		 * A form button to download the current playlist.
		 *
		 * @author Ben Dodson
		 * @since 4/23/05
		 **/
		function downloadListButton($return = false) {
		  global $this_page;
		  static $my_id = 0;

		  $label = "downloadbutton" . ++$my_id;
		  $retVal = '<input type="submit" style="display:none;" id="'.$label.'" name="'.jz_encode("downloadlist").'" value="'.jz_encode('true').'">';
		  
		  $onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';
		  
		  $retVal .= icon('download',array( 'title'=> word('Download Playlist'),
						  'onclick'=> $onclick
						  )
				  );

		  if ($return){
		    return $retVal;
		  } else {
		    echo $retVal;
		  }
		}

		/**
		 * A form button to create a new playlist.
		 *
		 * @author Ben Dodson
		 * @since 4/23/05
		 **/
		function createListButton($return = false) {
		  global $this_page;
		  static $my_id = 0;

		  $label = "createbutton" . ++$my_id;
		  $retVal  = '<input type="submit" style="display:none;" id="'.$label.'" name="'.jz_encode("createlist").'" value="'.jz_encode('true').'"';
		  $retVal .= " onclick=\"variablePrompt('playlistForm','playlistname','".word('Please enter a name for your playlist.')."')\">";
		  
		  $onclick = 'submitPlaybackForm(document.getElementById(\''.$label.'\'), \'' . htmlentities($this_page)  . '\')';

		  $retVal .= icon('add',array( 'title'=> word('Create Playlist'),
						  'onclick'=> $onclick
						  )
				  );

		  if ($return){
		    return $retVal;
		  } else {
		    echo $retVal;
		  }
		}


		/**
		 * Displays the login/logout link.
		 *
		 * @author Ben Dodson
		 * @version 1/17/05
		 * @since 1/17/05
		 *
		 */
		function loginLink($logintext = false, $logouttext = false, $registration = true, $regtext = false, $return_link = false) {
		  global $jzUSER, $http_auth_enable;

		  if (isset($http_auth_enable) && $http_auth_enable == "true") {
		    if ($return_link) {
		      return "";
		    } else {
		      return;
		    }
		  }

		  $array = array();
		  if ($jzUSER->getID() == $jzUSER->lookupUID('NOBODY')) {
		    $array['action'] = "login";
		    if ($logintext === false) {
		      $text = word("Login");
		    } else {
		      $text = $logintext;
		    }
		  } else {
		    $array['action'] = "logout";
		    if ($logouttext === false) {
		      $text = word("Logout");
		    } else {
		      $text = $logouttext;
		    }
		  }
		  $string = "";
		  $string .= '<a class="jz_header_table_href" href="'. urlize($array) .'">' . $text . '</a>';

		  if ($jzUSER->getID() == $jzUSER->lookupUID('NOBODY')) {
		    $be = new jzBackend();
		    $data = $be->loadData('registration');
		    if ($data['allow_registration'] == "true") {
		      if ($regtext === false) {
						$regtext = word("Register");
		      }
		      $array['action'] = "register";
		      $string .= ' | ';
		      $string .= '<a class="jz_header_table_href" href="'. urlize($array) .'">' . $regtext . '</a>';
		    }
		  }

		  if ($return_link) {
		    return $string;
		  } else {
		    echo $string;
		  }

		}


		/**
		* Displays a link for the specified popup.
		* $type is one of: genre, artist, album
		* 
		* 
		* @author Ben Dodson
		* @version 11/7/04
		* @since 10/27/04
		*/
		function popupLink($type, $text = false, $return = false, $linkOnly = false){
		  global $img_slim_pop, $img_more,$this_page,$root_dir,$jzUSER,$img_playlist,$include_path, $img_tools;

			$args = array();
			$args['action'] = "popup";
			$tag = 'target="_blank"';
			
			$root = &new jzMediaNode();
			
			switch ($type) {
				case "genre":
					$args['ptype'] = "genre";
					// fill args here.
					$tag .= " onclick=\"openPopup(this, 400, 400, false, 'Genres'); return false;\"";
					if ($text === false) {
						if (!isset($_SESSION['jz_num_genres'])){
							$_SESSION['jz_num_genres'] = $root->getSubNodeCount("nodes",distanceTo("genre"));
						}
						$text = "Genres: " . number_format($_SESSION['jz_num_genres']);
					}
					break;
				
				case "artist":
					$args['ptype'] = "artist";
					$tag .= " onclick=\"openPopup(this, 400, 400, false, 'Artists'); return false;\"";
					if ($text === false) {
						if (!isset($_SESSION['jz_num_artists'])){
							$_SESSION['jz_num_artists'] = $root->getSubNodeCount("nodes",distanceTo("artist"));
						}
						$text = "Artists: " . number_format($_SESSION['jz_num_artists']);
					}
					break;
				
				case "album":
					$args['ptype'] = "album";
					$tag .= " onclick=\"openPopup(this, 400, 400, false, 'Albums'); return false;\"";
					if ($text === false) {
						if (!isset($_SESSION['jz_num_albums'])){
							$_SESSION['jz_num_albums'] = $root->getSubNodeCount("nodes",distanceTo("album"));
						}
						$text = "Albums: " . number_format($_SESSION['jz_num_albums']);
					}
					break;
				
				case "track":
						$args['ptype'] = "track";
					$tag .= " onclick=\"openPopup(this, 400, 400, false, 'Tracks'); return false;\"";
					if ($text === false) {
						if (!isset($_SESSION['jz_num_tracks'])){
							$_SESSION['jz_num_tracks'] = $root->getSubNodeCount("tracks",-1);
						}
						$text = "Tracks: " . number_format($_SESSION['jz_num_tracks']);
					}
					break;
				case "preferences":
					if ($jzUSER->getSetting('edit_prefs') == false) {
						return;
					}
					$args['ptype'] = "preferences";
					$tag .= " onclick=\"openPopup(this, 300, 400); return false;\"";
					if ($text === false) {
						$text = word("Prefs");
					}
					break;
				case "slimzora":
					$url = $root_dir . "/slim.php";
					if (isset($_GET['jz_path'])) {
						$url .= "?" . jz_encode("jz_path") ."=". jz_encode($_GET['jz_path']);
					}	
					$tag .= " onclick=\"openPopup(this, 300, 400, false, 'Slimzora'); return false;\"";
					if ($text === false) {
						$text = $img_slim_pop;
					}
					if ($linkOnly){
						return $url;
					}
					echo "<a class=\"jz_header_table_href\" href=\"".$url."\" $tag>$text</a>";
					return;
				case "jukezora":
					if ($text === false) {
						$text = "Jukezora";
					}
			    $linky = $include_path."jukezora.php";
					$jukezora_link = '<a class="jz_header_table_href" href="javascript:;" onClick="openPopup(\''.$linky.'\',320,600); return false;" title="Launch Jukezora">'.$text.'</a>';
					if ($return) { return $jukezora_link; } 
					else { 
							echo $jukezora_link;
					 }
					return;
			  break;
			  case "docs":
					$args['ptype'] = "docs";
					$tag .= " onclick=\"openPopup(this, 550, 600); return false;\"";
					$text = $img_more;
					break;
				
				case "admintools":
					if ($jzUSER->getSetting('admin') == true){
						$args['ptype'] = "admintools";
						$args['jz_path'] = $_GET['jz_path'];
						$tag .= " onclick=\"openPopup(this, 550, 600); return false;\"";
						$text = $img_tools;
					}
					break;
				
				case "plmanager":
						$args['ptype'] = "playlistedit";
						$tag .= "onClick=\"openPopup(this,600,600); return false;\"";
						if ($text === false) {
							$text = $img_playlist;
						}
						break;
					}
				
			
				if ($return) {
					if ($linkOnly){
						return urlize($args);
					} else {
						return "<a class=\"jz_header_table_href\" href=\"".urlize($args)."\" $tag>$text</a>";
					}
				} else {
					echo "<a class=\"jz_header_table_href\" href=\"".urlize($args)."\" $tag>$text</a>";
			 }
		}
		
		/**
		* Displays or returns an image.
		*  
		* @author Ben Dodson
		* @version 11/10/04
		* @since 11/10/04
		* @param $path: path to the image
		* @param $alt: alt text to display.
		* @param $width: width for resize
		* @param $height: height for resize
		* @method: how to resize:
		*   -limit: constrained resize that does not exceed the width or height, if either is given.
		*           if the image is smaller, do not enlarge.
		*
		*   -fit: constrained resize that locks to the specified width or height (only 1 should be given)
		*           if the image is smaller, enlarge it.
		*
		*   -fixed: resize to the given width and height and do not constrain.
		* @param gd: use GD if available for the resize (otherwise, just set the html tag)
		* @param save: if gd is set, save our resized image over our old one.
		*/
		function image($path, $alt = false, $width = false, $height = false, $method = "limit", $gd = false, $save = false, $align = false, $hspace = false, $vspace = false, $border = "0") {
			echo $this->returnImage($path,$alt,$width,$height,$method,$gd,$save,$align,$hspace,$vspace,$border);
		}
		
		/**
		* Resizes and image if it needs it
		*  
		* @author Ross Carlson
		* @version 2/24/05
		* @since 2/24/05
		* @param $path: path to the image
		* @param $width: the new width for the image
		* @param $height: the new height for the image
		*/
		function resizeImage($path, $destination_width, $destination_height){
			global $keep_porportions;
			
			// First we need to be sure that they have GD support
			if (gd_version() == 0){
				return false;
			}
			
			// Ok, now let's resize
			$image = $path;
			$new_image = $path. ".new";
			
			// Let's grab the source image that was uploaded to work with it
			$src_img = @imagecreatefromjpeg($image);
			
			if ($src_img){
				// Let's get the width and height of the source image
				$src_width = imagesx($src_img); $src_height = imagesy($src_img);
				
				// Let's set the width and height of the new image we'll create
				$dest_width = $destination_width; $dest_height = $destination_height;
				
				// Now if the picture isn't a standard resolution (like 640x480) we
				// need to find out what the new image size will be by figuring
				// out which of the two numbers is higher and using that as the scale
				// First let's make sure they wanted to keep the porportions or not
				if ($keep_porportions == "true"){
					if ($src_width > $src_height){
						/* ok so the width is the bigger number so the width doesn't change
						   We need to figure out the percent of change by dividing the source width
						   by the dest width */
						$scale = $src_width / $destination_width;
						$dest_height = $src_height / $scale;
					} else {
						/* ok so the width is the bigger number so the width doesn't change
						   We need to figure out the percent of change by dividing the source width
						   by the dest width */
						$scale = $src_height / $destination_height;
						$dest_width = $src_width / $scale;
					}
				} else {
					$dest_height = $destination_height;
					$dest_width = $destination_width;
				}
				
				// Now let's create our destination image with our new height/width
				if (gd_version() >= 2) {
					$dest_img = imageCreateTrueColor($dest_width, $dest_height);
				} else {
					$dest_img = imageCreate($dest_width, $dest_height);
				}
				
				/* Now let's copy the data from the old picture to the new one with the new settings */
				if (gd_version() >= 2) {
					imageCopyResampled($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
				} else {
					imageCopyResized($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
				}
				
				/* Now let's create our new image */
				@imagejpeg($dest_img, $new_image);
				
				/* Now let's clean up all our temp images */
				imagedestroy($src_img);
				imagedestroy($dest_img);
				
				// Now we need to kill the old image and move the new one into it's place
				unlink($image);
				rename($new_image,substr($new_image,0,-4));
				
				return true;
			} else {
				return false;
			}
		}
		
		/**
		* Same as the above, but returns it instead of displaying it.
		* Useful for use in links.
		*  
		* @author Ben Dodson
		* @version 11/10/04
		* @since 11/10/04
		*/
		function returnImage ($path, $alt = false, $width = false, $height = false, $method = "limit", $gd = false, $save = false, $align = false, $hspace = false, $vspace = false, $border = "0", $class = false, $linkOnly = false) {
			global $album_img_width, $album_img_height, $allow_filesystem_modify, $root_dir;
			
			$art = jzCreateLink($path,"image");

			if ($linkOnly){
				return $art;
			}
			
			$tag = "";
			if ($alt !== false)
				$tag .= "alt=\"$alt\" ";
				$tag .= "title=\"$alt\" ";
			
			if ($method == "fixed") {
			  if ($width !== false)
			    $tag .="width=\"$width\" ";
			  if ($height !== false)
			    $tag .= "height=\"$height\"";
			} else {	
			  $size = @getimagesize($path);
			  $displaywidth = $size[0];
			  $displayheight = $size[1];			

			  if ($size && $size[0] > 0 && $size[1] > 0) {
			    switch ($method) {
			    case "limit":
			      if ($width !== false && $width < $displaywidth) {
							$displayheight = (int)($displayheight * $width / $displaywidth);
							$displaywidth = $width;					
			      } if ($height && $height < $displayheight) {
							$displaywidth = (int)($displaywidth * $height / $displayheight);
							$displayheight = $height;
			      }
			      $tag .= "width=\"$displaywidth\" height=\"$displayheight\"";
			      break;
			    case "fit":
			      if ($width !== false) {
							$displayheight = (int)($displayheight * $width / $displaywidth);
							$displaywidth = $width;
			      } else if ($height !== false) {
							$displaywidth = (int)($displaywidth * $height / $displayheight);
							$displayheight = $height;
			      }
			      $tag .= "width=\"$displaywidth\" height=\"$displayheight\"";
			      break;
			    }
			  }
			}
			if ($align){
				$tag .= ' align="'. $align. '" '; }
			if ($vspace){
				$tag .=  'vspace="'. $vspace. '" '; }
			if ($hspace){
				$tag .= ' hspace="'. $hspace. '" '; }
			if ($class){
				$tag .= ' class="'. $class. '" '; }			
			// Now let's set the border
			$tag .= ' border="'. $border. '" ';
				
			return "<img src=\"" . $art . "\" $tag>";
		}
		
		
		/**
		* Shows a dropdown.
		* 
		* Example:
		* 		
		* $display = &new jzDisplay();
		* $display->dropdown("genre");
		* 
		* @author Ben Dodson
		* @version 10/27/04
		* @since 10/27/04
		*/
		function dropdown ($type, $on_submit=true, $select_name="jz_path", $root = false, $return = false) {
		  global $hierarchy, $quick_list_truncate;
			$TRUNC = 12;
			$var = $type . "-dropdown";
			
			$string = "";
			$width = "130px";
						
			$retVar = '<select name="'. jz_encode($select_name) . '"';
			if ($on_submit) {
			  $retVar .= ' onChange="submit()"';
			}
			$retVar .= ' class="jz_select" style="width: '. $width. ';">'. "\n";
			$retVar .= $this->optionList($type,false,true,$root);
			$retVar .= "</select>\n";
			
			// Now let's set the cached variable
			$_SESSION[$var] = $retVar;
			
			// Now let's display
			if ($return){
				return $retVar;
			} else {
				echo $retVar;
			}
		}

		/**
		* Echos a list of items of the given type.
		*
		* @author Ben Dodson		
		* @version 1/12/05
		* @since 1/12/05
		* @param $type the type of the dropdown (genre/artist/album/track)
		* @param $chosen the preselected value (default none)
		* @param $returnOnly Should we return the value vs echo it
		*/
		function optionList($type, $chosen = false, $returnOnly = false, $node = false) {
			global $hierarchy, $quick_list_truncate;
		
			$string = "";
			if ($node === false) {
				$node = &new jzMediaNode();
			}

			if ($type == "track" || $type == "tracks") {
				$rettype = "leaves";
				$dis = -1;
			} else {
				$rettype = "nodes";
				$dis = distanceTo($type);
			}
		
			if (!isset($_SESSION['jz_cache_'. $type])){
				$array = $node->getSubNodes($rettype,$dis);
				for ($ctr=0; $ctr < count($array); $ctr++){
					$title = $array[$ctr]->getName();
					$path = $array[$ctr]->getPath("String");
					$parent = $array[$ctr]->getParent();
					$_SESSION['jz_cache_'. $type][$ctr]['title'] = $title;
					$_SESSION['jz_cache_'. $type][$ctr]['path'] = $path;
					$_SESSION['jz_cache_'. $type][$ctr]['parent'] = $parent->getName();
				}
			}		  
			$array = $_SESSION['jz_cache_'. $type];
			
			if ($chosen === false) {
				$string .= '<option value="" selected>'. word("Please Choose..."). '</option>';
			}
		
			for ($ctr=0; $ctr < count($array); $ctr++){
				$title = $array[$ctr]['title'];
				if (strlen($array[$ctr]['title']) > $quick_list_truncate + 3) {
					$title = substr($array[$ctr]['title'],0,$quick_list_truncate). "...";
				}
				$string .= '<option value="'. jz_encode($array[$ctr]['path']) . '"';
				$string .= '>' . $title . '</option>'. "\n";
			}
			if ($returnOnly){
				return $string;
			} else {
				echo $string;
			}
		}

		/**
		 * Starts a 'select' box.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12/05
		 */
		function openSelect($name, $width = 100,$onchange = false, $return = false) {
		
		  $retVal  ='<select';
		  $retVal .= ' class="jz_select"';
		  $retVal .= ' name=' . $name;
		  $retVal .= ' style="width:'. $width . 'px;"';
		  if ($onchange) {
		    $retVal .= ' onChange="submit()"';
		  }
		  $retVal .= '>';
			
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}	
		}


		/**
		 * Closes a 'select' box.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12/05
		 */
		function closeSelect($return = false) {
			if ($return){
				return '</select>';
			} else {
			  echo '</select>';
			}
		}


	   /**
		 * Sets up the pages AJAX functions.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12/05
		 */
		function handleAJAX() {
		  global $include_path,$jukebox,$my_frontend;
		  // AJAX:
		  $ajax_list = array();

		  @include_once($include_path."frontend/frontends/${my_frontend}/ajax.php");
		  @include_once($include_path."frontend/frontends/${my_frontend}/ajax_scripts.php");
		  @include_once($include_path."frontend/ajax.php");
		  @include_once($include_path."frontend/ajax_scripts.php");

		  if ($jukebox == "true") {
		    include_once($include_path."jukebox/ajax.php");
		    include_once($include_path."jukebox/ajax_scripts.php");
		  }
		  
		  if (sizeof($ajax_list > 0)) { // This frontend has AJAX functions:
		    global $sajax_debug_mode, $sajax_export_list, $sajax_request_type, $sajax_remote_uri;
		    $sajax_debug_mode = 0;
		    include_once($include_path."lib/Sajax.php");
		    sajax_init();
		    for ($i = 0; $i < sizeof($ajax_list); $i++) {
		      sajax_export($ajax_list[$i]);
		    }
		    echo "\n<script>\n";
		    sajax_show_javascript();
		    echo "\n</script>\n";
		  }
		}
		
		
		/**
		 * Display the standard javascript
		 *
		 * @author Ross Carlson
		 * @version 5/04/05
		 * @since 5/04/05
		 */
		function displayJavascript(){
			global $root_dir;
			
			echo returnJavascript();
		}


		/**
		* Preheader stuff. Things this function handles:
		* title, css, javascript
		* 'morejs' allows you to add page-specific javascript to a page.
		*
		* **should this also set the page width?
		*
		* @author Ben Dodson
		* @version 11/29/04
		* @since 11/29/04
		*/
		function preheader ($title = false, $width = "100%", $align = "left", $js = true, $gzip = true, $cms_open = true, $minimal_theme = false) {
		  global 	$css, $cms_type, $site_title, $include_path, 
		  			$root_dir, $node, $live_update, $gzip_handler, 
		  			$jzSERVICES, $skin,$ajax_list,$jukebox,$my_frontend,
		  			$secure_links;

		  // Are they doing gzip compression?
		  if (($gzip_handler == "true" and $gzip == true) and $cms_type <> "mambo" and $cms_type <> "cpgnuke") {
		    @ob_start('ob_gzhandler');
		  }
		  
		  $fe = new jzFrontend();
			$display = new jzDisplay();
			$smarty = smartySetup();
			
			// Now let's see if we need to open a CMS or not?
			if ($cms_open) {
				$jzSERVICES->cmsOpen();
			}			
			$css = $jzSERVICES->cmsCSS();

			$showHeader = false;		
			if (!isset($cms_mode) || $cms_mode === false || $cms_mode == "false") {
				$showHeader = true;
				$smarty->assign('favicon', $include_path. 'style/favicon.ico');	
				$smarty->assign('site_title', $site_title);	
				if ($title !== false){
					$smarty->assign('site_title', $site_title. " - ". str_replace("</nobr>","",str_replace("<nobr>","",str_replace("<br>"," ",$title))));
				}				
				$smarty->assign('rss_link', $include_path. 'rss.php?type=most-played');	
			}	
			$smarty->assign('root_dir', $root_dir);	
			if (!$minimal_theme) {
			  $smarty->assign('css', $css);	
			}
			$smarty->assign('skin', $skin);	
			$smarty->assign('secure_links', $secure_links);				
			$smarty->assign('fav_icon', $root_dir . '/style/favicon.ico');	
			
			// Now let's display the template
			if ($showHeader){
				$smarty->display(SMARTY_ROOT. 'templates/slick/header-pre.tpl');	
			}
			
			// AJAX:
			$this->handleAJAX();

			// Required for overlibs / wherever colors are needed in raw HTML.
			$define_only = true;
			if ($minimal_theme){
				$define_only = true;
			}


			include($css);
			unset($define_only);

			
			//!! Stuff that requires database is safe beyond this point. !!//
			if ($live_update == "true" && !(isset($_GET['action']) && $_GET['action'] == "search")){
			  updateNodeCache($node);
			}
			if (!(isset($_GET['action']) && $_GET['action'] == "search")) {
				handlePageView($node);
			}
		}
		
		
		/** Displays a hidden field for the given variable
		 * if it is currently set via post/get vars, it keeps that value
		 * or it may be set to the given value.
		 *
		 * @author Ben Dodson
		 */
		function hiddenVariableField($type, $value = false, $encode = true, $return = false) {
		  if ($value !== false) {
				if ($encode) {
		    	$retVal = '<input type="hidden" name="' . htmlentities(jz_encode($type)) . '" value="' . htmlentities(jz_encode($value)) . '">';
				} else {
					$retVal = '<input type="hidden" name="' . htmlentities($type) . '" value="' . htmlentities($value) . '">';
				} 
		  } else if (isset($_POST[$type])) {
		  	if ($encode) {
					$retVal = '<input type="hidden" name="' . jz_encode($type) . '" value="' . jz_encode($_POST[$type]) . '">';
				} else {
					$retVal = '<input type="hidden" name="' . $type . '" value="' . $_POST[$type] . '">';
				}
		  } else if(isset($_GET[$type])) {
		  	if ($encode) {
					$retVal = '<input type="hidden" name="' . jz_encode($type) . '" value="' . jz_encode($_GET[$type]) . '">';
				} else {
					$retVal = '<input type="hidden" name="' . $type . '" value="' . $_GET[$type] . '">';
				}
		  }
			if ($return){
				return $retVal;
			} else {
				echo $retVal;
			}
		}

		 /**
			* turns CMS GET variables into hidden fields
			* This helps make a GET-based form possible with a CMS.
			*
			* @author Ben Dodson
			* @version 6/1/05
			* @since 6/1/05
			**/
			function hiddenPageVars($return = false) {
				global $cms_mode,$cms_type,$jzSERVICES,$this_page;
				/*
				$ar = $jzSERVICES->cmsGETVars();
				foreach ($ar as $id => $val) {
					$this->hiddenVariableField($id,$val,false);
				}
				*/
				// Make it pull straight from a 'good' base URL so we don't miss anything.
				$url = urlize();
				$url = explode("?",$url);
				$url = $url[1];
				$url = explode("&",$url);
				$ret = '';
				foreach ($url as $entry) {
					$t = explode("=",$entry);
					if ($return){
						$ret .= $this->hiddenVariableField(urldecode($t[0]),urldecode($t[1]),false,true);
					} else {
						$this->hiddenVariableField(urldecode($t[0]),urldecode($t[1]),false);
					}
				}
								
				if ($return){
					return $ret;
				}
			}


		/**
		 * Echos the extra code needed
		 * to make the embedded player
		 * work in a new window during
		 * a form submit.
		 *
		 * @author Ben Dodson
		 * @version 4/17/05
		 * @since 4/17/05
		 **/
		function embeddedFormHandler($formname = false) {
		  global $jzUSER, $jzSERVICES;
		  
		  if ($formname === false) {
		    $formname = "albumForm";
		  }
		  if (checkPlayback() == "embedded") {
		    // Ok, let's put the popup in the href
		    return $jzSERVICES->returnPlayerFormLink($formname);
		  }
		}

}
		
		
	     

	
	// EVERYTHING HERE IS OLD AND [HOPEFULLY] NOT NEEDED.
	// I left it because I didn't want to break anything, since
	// this is more of a proof of concept than anything else.
	
	
	// Now let's put in all the standard display functions...
	
	/**
	* returns the HTML code for the CMS stylesheet
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML code for the javascript includes
	*/
	function returnCMSCSS(){
		global $bgcolor1, $bgcolor3;

		return "<style type=\"text/css\">.jz_row1 { background-color:$bgcolor1; } .jz_row2 { background-color:$bgcolor3; }</style>";
	}
			
	/**
	* returns the HTML code for the stylesheet
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML code for the javascript includes
	*/
	function returnCSS(){
		global $css;
		
		return $css;
	}
	
	
	/**
	* returns the HTML code to close the head
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML code for the javascript includes
	*/
	function returnCloseHTMLHead(){
		return '</head>';
	}
	
	/**
	* returns the HTML code to open the HEAD tag
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML code for the javascript includes
	*/
	function returnHTMLHead($title){
		global $root_dir, $site_title;
		
		$site = $_SERVER["HTTP_HOST"];
		if ($_SERVER['HTTPS'] == "on"){ $site = "https://". $site; } else { $site = "http://". $site; }
		
		return '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><link rel="shortcut icon" href="'. $root_dir. '/style/favicon.ico">'. "\n".
			   '<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1"><title>'.  "\n".
			   $site_title. " - ". str_replace("</nobr>","",str_replace("<nobr>","",str_replace("<br>"," ",$title))). "</title>". "\n".
			   '<link rel="alternate" type="application/rss+xml" title="Jinzora Most Played" href="'. $root_dir. '/rss.php?type=most-played">'. "\n";
	}
	
	/**
	* returns the HTML code for the Javascript includes
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML code for the javascript includes
	*/
	function returnJavascript(){
		global $root_dir, $enable_ratings;
		
		$js = '<script type="text/javascript" src="'. $root_dir. '/lib/jinzora.js"></script>'.
			   '<script type="text/javascript" src="'. $root_dir. '/lib/overlib.js"></script>'.
			   '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>'.
			   '<script type="text/javascript" src="'. $root_dir. '/lib/jquery/jquery.js"></script>';
			   
		if ($enable_ratings == "true") {
			$js .= 	'<script type="text/javascript" src="'. $root_dir. '/lib/jquery/rater.js"></script>'.
				    '<link rel="stylesheet" type="text/css" href="'. $root_dir. '/lib/jquery/rater/rater.css.php" />';
		}
		
		return $js;
	}
		
	/**
	* returns the HTML code to block right clicking
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @return returns HTML (javascript) code to block right clicks
	*/
	function displaySecureLinks(){
		global $secure_links;
		
		if ($secure_links == "true"){
			$retVal = '<SCRIPT LANGUAGE="JavaScript1.1">'. "\n";
			$retVal .= 'function noContext(){return false;}'. "\n";
			$retVal .= 'document.oncontextmenu = noContext;'. "\n";
			$retVal .= '// -->'. "\n";
			$retVal .= '</script>'. "\n";
			
			return $retVal;
		 } else {
		 	return false;
		 }
	}
	
	/**
	* returns the HTML for a drop down list of any type.
	* 
	* @author Ben Dodson, Ross Carlson
	* @version 6/9/04
	* @since 6/9/04
	* @param $onclick Should the select box submit on click?
	* @param $boxname What is the name of the select box
	* @param $width Width in pixels
	* @param $type Type of dropdown
	* @param $path Restraining path (defaults to empty). Can be string or array.
	*/
	// TODO:
	// 1) Test this; where is it being called from? 
	//    Why is /display.php's functions still being called?
	
	function returnSelect($onclick, $boxname, $width, $type = "genre", $node = false){
		global $hierarchy, $quick_list_truncate;
		
		if ($node === false) {
			$node = &new jzMediaNode();
		}
		$i = 0;
		while ($i < sizeof($hierarchy)) {
			if ($hierarchy[$i] == $type) {
				if ($type == "track") {
					$rettype = "leaves";
				} else {
					$rettype = "nodes";
				}
				$array = $node->getSubNodes($rettype,$i - $node->getLevel(),false,0);
				
				if ($onclick){
					$retVal = '<select name="'. $boxname. '" onChange="submit()" class="jz_select" style="width: '. $width. 'px;">'. "\n";
				} else {
					$retVal = '<select name="'. $boxname. '" class="jz_select" style="width: '. $width. 'px;">'. "\n";
				}
				
				$retVal .= '<option value="" selected>'. word("Please Choose..."). '</option>';
				for ($ctr=0; $ctr < count($array); $ctr++){
					$fulltitle = $array[$ctr]->getName();
					$title = $fulltitle;
					if (strlen($title) > $quick_list_truncate + 3) {
						$title = substr($title,0,$quick_list_truncate). "...";
					}
					$retVal .= '<option value="'. $fulltitle. '">'. $title. '</option>'. "\n";
				}
				$retVal .= '</select>'. "\n";
				return $retVal;
				
			}
		}
		return;
	}

	
	/**
	* returns the HTML for the drop down list of Albums
	* 
	* @author Ross Carlson
	* @version 6/9/04
	* @since 04/29/04
	* @param $onclick Should the select box submit on click?
	* @param $boxname What is the name of the select box
	* @param $width Width in pixels
	*/
	// If this fails miserably,
	// we have the code in /display.php.
	function returnAlbumSelect($onclick, $boxname, $width){
		return returnSelect($onclick,$boxname,$width,"album");
	}
	
	/**
	* returns the HTML for the drop down list of Artists
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @param $onclick Should the select box submit on click?
	* @param $boxname What is the name of the select box
	* @param $width Width in pixels
	*/
	function returnArtistSelect($onclick, $boxname, $width){
		return returnSelect($onclick,$boxname,$width,"artist");
	}


	/**
	* returns the HTML for the drop down list of Genres
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @param $onclick Should the select box submit on click?
	* @param $boxname What is the name of the select box
	* @param $width Width in pixels
	*/
	function returnGenreSelect($onclick, $boxname, $width){
		return returnSelect($onclick,$boxname,$width,"genre");
	}
	
	/* This function displays the login page then authenticaes the user for admin level access */
	function displayLogin(){
		global $main_table_width, $cellspacing, $this_page, $url_seperator;

		// Let's show the header
		displayHeader(word("Login"));
		$formAction = $this_page;
		?>		
			<form action="<?php echo $formAction; ?>" method="post">
				<input type="hidden" name="returnPage" value="<?php echo $_GET['return']; ?>">
				<table width="<?php echo $main_table_width; ?>%" cellpadding="<?php echo $cellspacing; ?>" cellspacing="0" border="0">
					<tr>
						<td width="50%" align="right">
							<font size="2">
							<?php echo word("Username"); ?>
							</font>
						</td>
						<td width="50%">
							<input class="jz_input" type="text" name="username">
						</td>
					</tr>
					<tr>
						<td width="50%" align="right">
							<font size="2">
							<?php echo word("Password"); ?>
							</font>
						</td>
						<td width="50%">
							<input class="jz_input" type="password" name="admin_pass">
						</td>
					</tr>
					<tr>
						<td width="50%" align="right">
							
						</td>
						<td width="50%">
							<font size="2">
							<input class="jz_checkbox" type="checkbox" name="remember_me"> <?php echo word("Remember me"); ?>
							</font>
						</td>
					</tr>
					<tr>
						<td width="100%" colspan="2" align="center">
							<input class="jz_submit" type="submit" name="submit_login" value="<?php echo word("Login"); ?>">
						</td>
					</tr>
				</table>
			</form>
		<?php
	}
?>
