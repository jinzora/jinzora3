<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	/**
	* - JINZORA | Web-based Media Streamer -  
	* 
	* Jinzora is a Web-based media streamer, primarily desgined to stream MP3s 
	* (but can be used for any media file that can stream from HTTP). 
	* Jinzora can be integrated into a CMS site, run as a standalone application, 
	* or integrated into any PHP website.  It is released under the GNU GPL.
	* 
	* - Resources -
	* - Jinzora Author: Ross Carlson <ross@jasbone.com>
	* - Web: http://www.jinzora.org
	* - Documentation: http://www.jinzora.org/docs	
	* - Support: http://www.jinzora.org/forum
	* - Downloads: http://www.jinzora.org/downloads
	* - License: GNU GPL <http://www.gnu.org/copyleft/gpl.html>
	* 
	* - Contributors -
	* Please see http://www.jinzora.org/team.html
	* 
	* - Code Purpose -
	* Creates many of the different blocks that are used by the Slick interface
	*
	* @since 01.11.05
	* @author Ross Carlson <ross@jinzora.org>
	*/
	
	class jzBlocks extends jzBlockClass {
	  
		/**
		* Constructor for the class.
		* 
		* @author Ben Dodson
		* @version 12/22/04
		* @since 12/22/04
		*/
		function jzBlocks() {
		
		}
		
		function slickFillerBlock(){
			global $jzUSER, $skin;
			return;
			?>
			<table width="100%" height="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td class="jz_block_td" <?php if ($skin == "slick"){ echo 'background="style/slick/filler-background.gif"';} ?>>&nbsp;</td>
				</tr>
			</table>
			<?php
		}
		
		function slickMediaBrowser($node,$showMainGrid){
			global $show_artist_alpha, $show_album_alpha;
			
			// did they want to show all art?
			if (isset($_POST['action'])){
				if ($_POST['action'] == "viewallart"){
					$this->showAllArt($node);
					return;
				}
			}
			if (isset($_GET['action'])){
				if ($_GET['action'] == "viewallart"){
					$this->showAllArt($node);
					return;
				}
			}
	
			if ($show_artist_alpha == "true" && $node->getLevel() == 0 && distanceTo("artist") !== false) {
				$this->alphabeticalList($node, "artist");
			}
			if ($show_album_alpha == "true" && $node->getLevel() == 0 && distanceTo("album") !== false) {
				$this->alphabeticalList($node, "album");
			}
			if ($node->getLevel() > 0 || $showMainGrid  || isset($_GET['jz_letter'])) {
				$this->nodeGrid($node);
			}
		}
		
		
		function slickJukeboxBlock(){
			global $jzUSER, $jukebox_display, $cms_mode;
			
			// Ok, now let's put in the Jukebox block if they are in jukebox mode
			if (checkPermission($jzUSER,"jukebox_queue") && $jukebox_display != "small" && $jukebox_display != "off" and $cms_mode == "false"){
				?>
					<table width="100%" cellpadding="1">
						<tr>
							<td valign="top" width="100%">
						 <?php
								$this->blockHeader("Jukebox"); // - ". $link);
								$this->blockBodyOpen();
								echo '<div id="jukebox">';
								include(jzBlock('jukebox'));
								echo '</div>';
								$this->blockBodyClose();
							?>
						</td>
					</tr>
				</table>
				<?php	
				//$this->blockSpacer();
			}
		}
		
		
		function slickHeaderBlock($node = false, $title = false){
			global 	$cms_mode, $genre_drop, $artist_drop, $this_page,
					$album_drop, $song_drop, $quick_drop, $jzUSER, $allow_resample;

			// Now are the forums post or get?
			// We have to post for CMSes
			if ($cms_mode == "true"){
				$mode = "POST";
			} else {
				$mode = "GET";
			}						
			if (!$node){
				$node = new jzMediaNode();
			}
			
			// Now let's display the header for the block
			if (!$title){
				$title = "Browse";
				if ($node->getName() <> ""){
					$parent = $node->getParent();
					if ($parent->getName() <> ""){
						$title .= " :: ". $parent->getName();
					}
					$title .= " :: ". $node->getName();
				}
			}						
			
			// Let's startup Smarty
			$smarty = smartySetup();
			
			// Now let's assign our variables to smarty
			$smarty->assign('title', $title);
			$smarty->assign('breadcrumbs', $this->breadCrumbs());
			$smarty->assign('jz_bg_color', jz_bg_color);
			$smarty->assign('this_page', $this_page);
			$smarty->assign('mode', $mode);			
			$smarty->assign('show_genre', false);
			if ($genre_drop != "false" && ($d = distanceTo("genre")) !== false && $d > 0){
				$smarty->assign('show_genre', true);
			}
			$smarty->assign('genre_drop', $genre_drop);
			$smarty->assign('show_artist', false);
			if ($artist_drop != "false" && ($d = distanceTo("artist")) !== false && $d > 0){
				$smarty->assign('show_artist', true);
			}
			$smarty->assign('artist_drop', $artist_drop);
			$smarty->assign('show_album', false);
			if ($album_drop != "false" && ($d = distanceTo("album")) !== false && $d > 0){
				$smarty->assign('show_album', true);
			}
			$smarty->assign('album_drop', $album_drop);
			$smarty->assign('song_drop', $song_drop);
			$smarty->assign('quick_drop', $quick_drop);
			$smarty->assign('show_resample', $allow_resample);
				
			// Now let's display the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-header.tpl');
		}
		
		/**
		* Displays the left navigation
		*
		* @author Ross Carlson
		* @since 2.27.06
		* @version 2.27.06
		* @param $node The node we are viewing
		*
		**/
		function slickLeftNavigation($node){		

			// Now let's display the blocks
			$smarty = smartySetup();
			
			// Now let's assign our variables to smarty
			//$smarty->assign('title', $title);
				
			// Now let's display the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/leftnav.tpl');
			
			// TODO - need to Smarty this
			$this->smallJukeboxBlock();
		}
		/**
		* Displays the Options block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockOptions($node){
			global $jzUSER, $show_options, $enable_ratings, $enable_discussion, $allow_interface_choice, 
						 $allow_lang_choice, $allow_style_choice, $this_page;	
			
			if ($show_options <> "true"){return;}
			
			$display = new jzDisplay();
			if ($display->startCache("blockOptions","")){
				return;
			}
				
			$smarty = smartySetup();
			$smarty->assign('this_page', $this_page);
			$smarty->assign('enable_discussion', $enable_discussion);
			$smarty->assign('enable_ratings', $enable_ratings);
			$smarty->assign('allow_interface_choice', $allow_interface_choice);
			$smarty->assign('allow_lang_choice', $allow_lang_choice);
			$smarty->assign('allow_style_choice', $allow_style_choice);
			$smarty->assign('word_options', word("Options"));
			if ($jzUSER->getSetting('stream') and ($enable_discussion == "true" or $enable_ratings == "true")){
				$smarty->assign('show_group_options', true);
			} else {
				$smarty->assign('show_group_options', false);
			}
			
			$url_array = array();
			$url_array['jz_path'] = $node->getPath("String");
			$url_array['action'] = "popup";
			$url_array['ptype'] = "rateitem"; 
			$smarty->assign('rate_popup_link', urlize($url_array));
			$url_array['ptype'] = "discussitem"; 
			$smarty->assign('discuss_popup_link', urlize($url_array));
			$url_array['ptype'] = "requestmanager"; 
			$smarty->assign('request_popup_link', urlize($url_array));
			$smarty->assign('word_rate_item', word("Rate Item"));
			$smarty->assign('word_discuss_item', word("Discuss Item"));
			$smarty->assign('word_request_manager', word("Request Manager"));
				
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-options.tpl');
			
			// Now lets finish out the cache
			$display->endCache();
		}
		
		/**
		* Displays the Browsing block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockBrowsing(){
			global $show_user_browsing, $hierarchy, $this_page; 
			
			if ($show_user_browsing <> "true"){return;}
			
			// Let's startup our Objects
			$smarty = smartySetup();
			$display = new jzDisplay();

			$smarty->assign('word_browsing', word("Browsing"));			
			$smarty->assign('hidden_page_vars', $display->hiddenPageVars(true));			
			$smarty->assign('word_browse', word("Browse"));			
			$smarty->assign('this_page', $this_page);			
			
			$url_array = array();
			$url_array['action'] = "popup";
			$lvls = @implode("|",$hierarchy);
			
			$smarty->assign('genre_browse', "");
			if (stristr($lvls,"genre")){
				$url_array['ptype'] = "genre"; 
				$smarty->assign('genre_browse', '<option value="'. urlize($url_array). '">'. word("All Genres"). ' ('. number_format($_SESSION['jz_num_genres']). ')</option>'. "\n");			
			}			
			$smarty->assign('artist_browse', "");
			if (stristr($lvls,"artist")){
				$url_array['ptype'] = "artist"; 
				$smarty->assign('artist_browse', '<option value="'. urlize($url_array). '">'. word("All Artists"). ' ('. number_format($_SESSION['jz_num_artists']). ')</option>'. "\n");			
			}
						$smarty->assign('album_browse', "");
			if (stristr($lvls,"album")){
				$url_array['ptype'] = "album"; 
				$smarty->assign('album_browse', '<option value="'. urlize($url_array). '">'. word("All Albums"). ' ('. number_format($_SESSION['jz_num_albums']). ')</option>'. "\n");			
			}			
			$url_array['ptype'] = "track"; 
			$smarty->assign('track_browse', '<option value="'. urlize($url_array). '">'. word("All Tracks"). ' ('. number_format($_SESSION['jz_num_tracks']). ')</option>'. "\n");
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-browsing.tpl');
		}
		
		/**
		* Displays the Playlist block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockPlaylists(){
			global $jzUSER, $hierarchy, $skin, $root_dir, $secure_urls;
			
			if (!$jzUSER->getSetting('stream')){return;}
			
			// Let's startup our Objects
			$smarty = smartySetup();
			$display = new jzDisplay();
			
			$arr = array();
			$arr['jz_path'] = $_GET['jz_path'];
			$smarty->assign('playlist_form_link', urlize($arr));
			
			$smarty->assign('cur_path', $_GET['jz_path']);
			$smarty->assign('skin', $skin);
			$smarty->assign('word_playlists', word("Playlists"));
			$lvls = @implode("|",$hierarchy);
			$smarty->assign('selected_playlist', $_SESSION['jz_playlist']);			
			$smarty->assign('root_dir', $root_dir);			
			$url_array = array();
			$url_array['action'] = "popup";
			$url_array['ptype'] = "playlistedit"; 
			$smarty->assign('playlist_edit_link', urlize($url_array));
			$secure = false;
			if ($secure_urls == "true"){
				$secure = true;
			}			
			$smarty->assign('playlist_hidden_action', $display->hiddenVariableField('action','playlistAction', $secure, true));
			$smarty->assign('playlist_hidden_path', $display->hiddenVariableField('path',$_GET['jz_path'], $secure, true));			
			
			$smarty->assign('playlist_play_button', $display->playListButton(true));
			$smarty->assign('playlist_play_random_button', $display->randomListButton(true));
			if ($jzUSER->getSetting('download')) {
				$smarty->assign('playlist_download_button', $display->downloadListButton(true));
			} else {
				$smarty->assign('playlist_download_button', "");
			}
			$smarty->assign('playlist_create_button', $display->createListButton(true));
			$smarty->assign('playlist_manager_button', $display->popupLink('plmanager',false,true));

			$lists = $jzUSER->listPlaylists("all");
			$i=0;
			foreach ($lists as $id=>$pname) {
			 	$lArr[$i]['value'] = $id;
				$lArr[$i]['name'] = $pname;
				
				if ($_SESSION['jz_playlist'] == $id) {
					$lArr[$i]['selected'] = "selected";
				} else {
					$lArr[$i]['selected'] = "";
				}
				
				$i++;
			}
			$smarty->assign('playlists', $lArr);
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-playlists.tpl');
		}
		
		/**
		* Displays the Google Ads Block
		*
		* @author Ross Carlson
		* @since 8.9.06
		* @version 8.9.06
		*
		**/
		function blockGoogleAds(){
			global $show_google_ads, $css, $include_path, $google_ad_client_id, $google_ad_channel;
			
			if ($show_google_ads <> "true"){return;}
			
			$define_only = true;
			include($include_path. $css);		
			
			$smarty = smartySetup();					
			$smarty->assign('google_color_border', substr(jz_bg_color,1));
			$smarty->assign('google_color_bg', substr(jz_bg_color,1));
			$smarty->assign('google_color_link', substr(jz_link_color,1));
			$smarty->assign('google_color_text', substr(jz_font_color,1));
			$smarty->assign('google_color_url', substr(jz_font_color,1));
			$smarty->assign('google_ad_client_id', $google_ad_client_id);
			$smarty->assign('google_ad_channel', $google_ad_channel);			
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-google-ads.tpl');
		}
		
		/**
		* Displays the Shoutbox block
		*
		* @author Ross Carlson
		* @since 8.9.06
		* @version 8.9.06
		*
		**/
		function blockShoutbox(){
			global $jzUSER, $root_dir, $show_shoutbox;
			
			if ($show_shoutbox <> "true"){return;}
			
			$smarty = smartySetup();	
			
			$url_array['action'] = "popup";
			$url_array['ptype'] = "purgeShoutbox";
			$smarty->assign('purge_link', '<a href="'. urlize($url_array). '" onclick="openPopup(this, 200, 200); return false;">Purge</a>');		
			$smarty->assign('username', $jzUSER->getName());			
			$smarty->assign('root_dir', $root_dir);			
			$smarty->assign('admin', $jzUSER->getSetting('admin'));		
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-shoutbox.tpl');
		}
		
		/**
		* Displays the Search block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockSearch(){
			global $jzUSER,$jukebox,$this_page,$cms_mode,$this_page;
			
			if (!$jzUSER->getSetting('powersearch')){ return;}
			
			$display = new jzDisplay();
			/*
			if ($display->startCache("blockSearch","")){
				return;
			}*/
			
			// Let's startup our Objects
			$smarty = smartySetup();	
			
			$url_search = array();
			$url_search['action'] = "powersearch";
			$smarty->assign('search_url', urlize($url_search));
			$smarty->assign('word_search', word("Search"));
			$smarty->assign('this_page', $this_page);
			
			if ($jukebox == "true" && !defined('NO_AJAX_JUKEBOX')) {
				$smarty->assign('searchOnSubmit', 'onSubmit="return searchKeywords(this,\'' . htmlentities($this_page) . '\');"');
			} else {
			  $smarty->assign('searchOnSubmit', "");
			}
			$smarty->assign('word_all_media', word("All Media"));
			$smarty->assign('artistSearch', "");
			if (distanceTo("artist") !== false){
				$smarty->assign('artistSearch', '<option value="artists">'. word("Artists"). '</option>');
			}
			$smarty->assign('albumSearch', "");
			if (distanceTo("album") !== false) {
				$smarty->assign('albumSearch', '<option value="albums">'. word("Albums"). '</option>');
			}
			$smarty->assign('word_tracks', word("Tracks"));
			$smarty->assign('word_lyrics', word("Lyrics"));
			$smarty->assign('word_go', word("Go"));
			$smarty->assign('user_stream', $jzUSER->getSetting('stream'));
			if ($cms_mode == "true") {
				$smarty->assign('method','GET');
			} else {
				$smarty->assign('method','POST');
			}
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-search.tpl');
			
			// Now lets finish out the cache
			//$display->endCache();
		}
		
		/**
		* Displays the Whos is where block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockWhoIsWhere(){
			global $jzUSER, $status_blocks_refresh, $show_who_is_where;
			
			// Does the cache file exist?
			$display = new jzDisplay();
			if ($display->startCache("blockWhoIsWhere","")){
				return;
			}
			
			if ($show_who_is_where == "true" || 
					($show_who_is_where == "admin" && $jzUSER->getSetting('admin') === true) ||
					($show_who_is_where == "user" && $jzUSER->getID() != $jzUSER->lookupUID(NOBODY))){} else {
				return;
			}	
			// Let's startup our Objects
			$smarty = smartySetup();	
					
			$smarty->assign('status_blocks_refresh', $status_blocks_refresh * 1000);
			$smarty->assign('word_who_is_where', word("Who is Where"));
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-whoiswhere.tpl');
			
			// Now lets finish out the cache
			$display->endCache();
		}
		
		/**
		* Displays the Now Streaming
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockNowStreaming(){
			global $show_now_streaming, $jzUSER, $status_blocks_refresh;
			
			// Does the cache file exist?
			$display = new jzDisplay();
			if ($display->startCache("blockNowStreaming","")){
				return;
			}
			
			// Let's startup our Objects
			$smarty = smartySetup();
			
			if ($show_now_streaming == "true" || 
					($show_now_streaming == "admin" && $jzUSER->getSetting('admin') === true) || 
					($show_now_streaming == "user" && $jzUSER->getID() != $jzUSER->lookupUID(NOBODY))){} else {
				return;
			}
			$smarty->assign('status_blocks_refresh', $status_blocks_refresh * 1000);
			$smarty->assign('word_now_streaming', word("Now Streaming"));
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-nowstreaming.tpl');
			
			// Now lets finish out the cache
			$display->endCache();
		}
		
		/**
		* Displays the big logo block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockLogo(){
			global $skin, $root_dir;
			
			// Let's startup our Objects
			$smarty = smartySetup();
			
			// Let's set the variables
			$arr = array();
			$arr['jz_path'] = "";
			
			$smarty->assign('home_link', urlize($arr));
			$smarty->assign('main_logo', $root_dir. '/style/'. $skin. '/big-logo.gif');
			
			// Now let's include the templates
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-logo.tpl');
		}
		
		/**
		* Displays the user prefreneces and tools block
		*
		* @author Ben Dodson, Ross Carlson
		* @since 2.28.06
		* @version 2.28.06
		*
		**/
		function blockUser(){
			global $jzUSER, $img_home, $cms_mode, $show_slimzora, $img_slim_pop, $img_more, 
						 $img_tools, $img_prefs, $img_login,$jz_path, $help_access;
			
			// Let's startup our Objects
			$smarty = smartySetup();
			$display = new jzDisplay();
			
			// Let's set the variables
			$arr = array();
			$arr['jz_path'] = "";
			
			// Let's assign our variables
			$smarty->assign('img_prefs', $img_prefs);
			$smarty->assign('help_access', $help_access);			
			$smarty->assign('img_login', $img_login);	
			$smarty->assign('home_link', urlize($arr));
			$smarty->assign('home_image', $img_home);
			$smarty->assign('cms_mode', $cms_mode);
			$smarty->assign('show_slimzora', $show_slimzora);
			$smarty->assign('img_slim_pop', $img_slim_pop);
			$smarty->assign('slimzora_link', $display->popupLink('slimzora',false,true,true));
			$smarty->assign('img_docs', $img_more);
			$smarty->assign('docs_link', $display->popupLink('docs',false,true,true));
			$smarty->assign('img_tools', $img_tools);
			$smarty->assign('is_admin', checkPermission($jzUSER,'admin',$jz_path));
			$smarty->assign('admin_tools_link', $display->popupLink('admintools',false,true,true));
			$smarty->assign('word_user', word("User"));
			$smarty->assign('user_name', $display->returnShortName($jzUSER->getName(),10));
			$smarty->assign('login_link', $display->loginLink(word('Login'),word('Logout'),true,false,true));
			$smarty->assign('pref_link', $display->popupLink('preferences',false,true,true));
			$smarty->assign('edit_prefs', $jzUSER->getSetting('edit_prefs'));
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-user.tpl');
		}
		
		/**
		* Displays the block for the small jukebox
		*
		* @author Ben Dodson, Ross Carlson
		* @since 12.18.05
		* @version 12.18.05
		*
		**/
		function smallJukeboxBlock(){
			global $jzUSER, $jukebox_display;
			
			if (checkPermission($jzUSER,"jukebox_queue") && ($jukebox_display == "small" or $jukebox_display == "minimal")) {
				$this->leftNavBlockSpacer();
				$this->leftNavBlockOpen();
				?> 
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td width="100%">
							<div id="smallJukebox">
							<?php $this->smallJukebox(); ?>
							</div>
						</td>
					</tr>
				</table>
				<?php
				$this->leftNavBlockClose();
			}
		}		
		
		/**
		* Displays the block for the security warning
		*
		* @author Ben Dodson, Ross Carlson
		* @since 12.18.05
		* @version 12.18.05
		*
		**/
		function showSecurityWarning(){						
			if ($this->checkForSecure()){
				$smarty = smartySetup();
				$smarty->assign('path', getcwd());

				// Now let's include the template
				$smarty->display(SMARTY_ROOT. 'templates/slick/security-warning.tpl');
				exit();
			}
		}
	
		/**
		* Displays the block for the tracks on the Album page
		*
		* @author Ben Dodson, Ross Carlson
		* @since 9/6/05
		* @version 9/6/05
		*
		**/ 
		function artistAlbumArtBlock($node){
			global $show_album_art, $sort_by_year, $album_name_truncate;
			
			$blocks = new jzBlocks();
			$display = new jzDisplay();
			
			// Does the cache file exist?
			if ($display->startCache("artistAlbumArtBlock",$node->getPath())){
				return;
			}
			
			// Let's startup Smarty
			$smarty = smartySetup();
			
			// Now let's assign our variables to smarty
			$smarty->assign('title', word("Album Art"));			
		
			// Now let's display the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/artist-album-art-block.tpl');

			// Now lets finish out the cache
			$display->endCache();
		}
							
							
	/**
	* Displays the block for the tracks on the Album page
	*
	* @author Ben Dodson, Ross Carlson
	* @since 9/6/05
	* @version 9/6/05
	*
	**/
	function albumTracksBlock($node = false){
		global $album_name_truncate, $img_play, $img_random_play, $img_play_dis, $img_random_play_dis, $jzUSER, $enable_ratings, $show_album_clip_play, $img_clip;

		$artist = false;
		if( $node != false ) {
			$art = $node->getParent();
			$artist = $art->getName();
		}
		
		if (!defined('NO_AJAX_LINKS') && $node === false) {
		  $node = new jzMediaNode($_SESSION['jz_path']);
		}
		if (!is_object($node)) {
		  return;
		}
		$display = new jzDisplay();

		// now let's set the title for this block
		$title = returnItemShortName($node->getName(),$album_name_truncate);
		
		// Now let's get the year
		$year = $node->getYear();
		$dispYear = "";
		if (!isnothing($year)){
			$dispYear = " (". $year. ")";
		}
		
		// Now let's setup our buttons for later
		$playButtons = "";
		$playButtons .= $display->playLink($node,$img_play,false,false,true). $display->playLink($node,$img_random_play,false,false,true,true);
		// Now let's make sure they can stream
		if (!$jzUSER->getSetting('stream')){
			$playButtons = $img_play_dis. $img_random_play_dis;
		}
		if ($show_album_clip_play == "true"){
			$playButtons .= $display->playLink($node,$img_clip,false,false,true,false,false,true);
		}
		if ($jzUSER->getSetting('download')){
			$playButtons .= $display->downloadButton($node, false, true, true);
		} else {
			$playButtons .= $display->downloadButton($node, true, true, true);
		}
		$playButtons .= $display->podcastLink($node);
		if ($enable_ratings == "true"){
			$playButtons .= $display->rateButton($node, true);		
		}
		$playButtons .= "&nbsp;";
		
		$this->blockHeader("Tracks: ". $title. $dispYear, $playButtons);
		$this->blockBodyOpen();

		// Now let's see if this is a multi-disc album
		$disks = $node->getSubNodes("nodes");
		$all_tracks = array();

		if (count($disks) > 0){
			// Yep, it's a multi...
			foreach ($disks as $disk) {
				$disktracks = $disk->getSubNodes("tracks",-1);
				sortElements($disktracks,"number");

				ob_start();
				$display->playButton($disk);
				$display->link($disk,"&nbsp;<strong>". $disk->getName()."</strong><br>");

				$header = ob_get_contents();
				ob_end_clean();
				

				// Now let's store the album name
				$all_tracks[] = $header;
				
				// Now let's display the tracks for this album
				foreach ($disktracks as $t) {
				  $all_tracks[] = $t;
				}
			}
		}
		
		// Now let's read all the tracks for this album
		$tracks = $node->getSubNodes("tracks");
		$all_tracks += $tracks;

		$this->trackTable($all_tracks, "album");

		$this->blockBodyClose();
		//$this->blockSpacer();
	}
	
	
	function albumOtherAlbumBlock($node = false){
		global $num_other_albums, $show_album_art, $jzUSER, $album_name_truncate;
		
		if (!defined('NO_AJAX_LINKS') && $node === false) {
		  $node = new jzMediaNode($_SESSION['jz_path']);
		}
		$display = new jzDisplay();
	
		$parent = $node->getNaturalParent(); 
		$nodes = $parent->getSubNodes("nodes",false,true,$num_other_albums * 2,true); // randomized, only with art.
		if ((count($nodes) > 1) and $show_album_art <> "false"){									
			// Let's startup Smarty
			$smarty = smartySetup();
			
			// Now let's assign our variables to smarty
			$smarty->assign('title', word("Other Albums from"). " ". $parent->getName());
	
			// Now let's display the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-other-albums.tpl');
		}
	}
	
	
	
	
	
	/**
	* Displays the block for the artist profile on the Artist page
	*
	* @author Ben Dodson, Ross Carlson
	* @since 9/13/05
	* @version 9/13/05
	*
	**/
	function artistProfileBlock($node = false){
		global $jzUSER, $album_name_truncate, $img_play, $img_random_play, $img_play_dis, $img_random_play_dis;
		
		$display = new jzDisplay();
		// Does the cache file exist?
		if ($display->startCache("artistProfileBlock",$node)){
			return;
		}

		$art = $node->getMainArt("150x150");
		$desc_truncate = "700";
		$desc = $display->returnShortName($node->getDescription(),$desc_truncate);
		
		if ($desc == ""){
			$profile .= "<center><br>";
			if ($art !== false) {
				$profile .= $display->returnImage($art,$node->getName(),150,150,"limit");
			}
			$profile .= "<br><br></center>";
		} else {
			// Now let's show the art
			if ($art !== false) {
				$profile .= $display->returnImage($art,$node->getName(),150,150,"limit",false,false,"left","5","5");
			}
			$desc = str_replace( "\n", "<p>", $desc );
			$profile .= $desc;
		}
		
		if (($desc == "") and $art == false){
			return;
		}
		
		if ($display->plaintextStrlen( $node->getDescription()) > $desc_truncate){
			$url_array = array();
			$url_array['jz_path'] = $node->getPath("String");
			$url_array['action'] = "popup";
			$url_array['ptype'] = "readmore";
			$profile .= ' <a href="'. urlize($url_array). '" onclick="openPopup(this, 450, 450); return false;">read more</a>';
		}
		
		if (!defined('NO_AJAX_LINKS') && $node === false) {
		  $node = new jzMediaNode($_SESSION['jz_path']);
		}
		$nodes = $node->getSubNodes("nodes");
		
		// Let's create our buttons for later
		if ($jzUSER->getSetting('stream')) {
			$playButtons = $display->playLink($node,$img_play,false,false,true). " ".  $display->playLink($node,$img_random_play,false,false,true,true). "&nbsp;";
		} else {
			$playButtons = $img_play_dis. $img_random_play_dis;
		}
		
		// Let's startup Smarty
		$smarty = smartySetup();
		
		// Now let's assign our variables to smarty
		$smarty->assign('title', word("Artist"). ": ". $node->getName(). " &nbsp; ");
		$smarty->assign('rating', $display->rating($node,true));		 
		$smarty->assign('playButtons', $playButtons);
		$smarty->assign('profile', $profile);

		// Now let's display the template
		$smarty->display(SMARTY_ROOT. 'templates/slick/artist-profile-block.tpl');
		
		// Now lets finish out the cache
		$display->endCache();
	}
	
	/**
	* Displays the block for the album on the Artist page
	*
	* @author Ben Dodson, Ross Carlson
	* @since 9/13/05
	* @version 9/13/05
	*
	**/
	function artistAlbumsBlock($node = false){
		global $jzUSER, $album_name_truncate, $img_play, $img_random_play, $img_play_dis, $img_random_play_dis, $sort_by_year, 
					 $web_root, $root_dir, $this_page, $show_album_clip_play, $img_clip;
		
		$display = new jzDisplay();
		
		// Are they sorting?
		if (isset($_GET['sort'])){
			$_SESSION['jz_purge_file_cache'] = "true";
			$jzUSER->setSetting("sort",$_GET['sort']);
		}
		$mysort = $jzUSER->getSetting("sort");

		// Does the cache file exist?
		if ($display->startCache("artistAlbumsBlock",$node, $mysort)){
			return;
		}
		
		if (!defined('NO_AJAX_LINKS') && $node === false) {
		  $node = new jzMediaNode($_SESSION['jz_path']);
		}
		
		// Let's create our buttons for later
		// Now let's create the sort link
		if ($node->getSubNodeCount() > 1){
			$url_array = array();
			$url_array['jz_path'] = $node->getPath("String");
			$url_array['sort'] = "alpha";
			$url_name = urlize($url_array);
			$url_array['sort'] = "year";
			$url_year =  urlize($url_array);
			$form  = '<form action="'. $this_page. '" method="GET">'. "\n";
			$form .= '<input type="hidden" name="'. jz_encode("jz_path"). '" value="'. jz_encode($node->getPath("String")). '">';			
			$form .= $display->hiddenPageVars(true);				
			$form .= '<select style="width:52px; height:15px; font-size:9px;" name="'. jz_encode("sort"). '" class="jz_select" onChange="form.submit();">'. "\n";
			$form .= '<option ';
			if ($mysort == "year"){ $form .= " selected "; }
			$form .= ' value="'. jz_encode("year"). '">Year</option>';
			$form .= '<option ';
			if ($mysort == "alpha"){ $form .= " selected "; }
			$form .= 'value="'. jz_encode("alpha"). '">Name</option>';
			$form .= '</select></form>';
			$playButtons .= $form. " ";
		}
		
		if ($jzUSER->getSetting('stream')) {
			$playButtons .= $display->playLink($node,$img_play,false,false,true). " ". 
			$display->playLink($node,$img_random_play,false,false,true,true). "&nbsp;";
		} else {
			$playButtons .= $img_play_dis. $img_random_play_dis. "&nbsp;";
		}
		
		// Let's startup Smarty
		$smarty = smartySetup();
		
		// Now let's assign our variables to smarty
		$smarty->assign('title', word("Albums"). ": ". $node->getName(). " (". $node->getSubNodeCount(). ")");
		$smarty->assign('playButtons', $playButtons);
		$smarty->assign('formaction', urlize());
		$smarty->assign('formhandler', $display->embeddedFormHandler());
		$smarty->assign('action', jz_encode("action"));
		$smarty->assign('action_value', jz_encode("mediaAction"));
		$smarty->assign('jz_path', jz_encode("jz_path"));
		$smarty->assign('jz_path_value', htmlentities(jz_encode($node->getPath("String"))));
		$smarty->assign('jz_list_type', jz_encode("jz_list_type"));
		$smarty->assign('jz_list_type_value', jz_encode("nodes"));

		// Now let's display the template
		$smarty->display(SMARTY_ROOT. 'templates/slick/artist-albums-block.tpl');
		
		// Now lets finish out the cache
		$display->endCache();
	}
	
	/**
	* Displays the block for the album on the Album page
	*
	* @author Ben Dodson, Ross Carlson
	* @since 9/6/05
	* @version 9/6/05
	*
	**/
	function albumAlbumBlock($node = false){
		global $album_name_truncate, $img_play, $cms_mode, $img_random_play, $img_play_dis, $img_random_play_dis, $jzUSER,$short_date, $enable_ratings, $show_album_clip_play, $img_clip;
	
		$display = new jzDisplay();
		// Does the cache file exist?
		if ($display->startCache("albumAlbumBlock",$node)){
			return;
		}
		
		if (!defined('NO_AJAX_LINKS') && $node === false) {
		  $node = new jzMediaNode($_SESSION['jz_path']);
		}
		
		$artSize = 100; $desc_truncate = 700;
		$desc = $node->getDescription();
		// Now let's purge the extra returns that might be at the beginning
		while (substr($desc,0,4) == "<br>" or substr($desc,0,6) == "<br />"){
			if (substr($desc,0,4) == "<br>"){
				$desc = substr($desc,5);
			}
			if (substr($desc,0,6) == "<br />"){
				$desc = substr($desc,7);
			}
		}
		
		if ($desc == ""){
			$artSize = 200;
		}
		$art = $node->getMainArt($artSize."x".$artSize);		
		if ($art == false and $desc == ""){
			return;
		}
	
		// now let's set the title for this block
		$title = returnItemShortName($node->getName(),$album_name_truncate);
		
		// Now let's get the year
		$year = $node->getYear();
		$dispYear = "";
		if (!isnothing($year)){
			$dispYear = " (". $year. ")";
		}
		
		// Now let's setup our buttons for later
		$playButtons = "";
		$playButtons .= $display->playLink($node,$img_play,false,false,true). $display->playLink($node,$img_random_play,false,false,true,true);
		// Now let's make sure they can stream
		if (!$jzUSER->getSetting('stream')){
			$playButtons = $img_play_dis. $img_random_play_dis;
		}
		if ($show_album_clip_play == "true"){
			$playButtons .= $display->playLink($node,$img_clip,false,false,true,false,false,true);
		}
		if ($jzUSER->getSetting('download')){
			$playButtons .= $display->downloadButton($node, false, true, true);
		} else {
			$playButtons .= $display->downloadButton($node, true, true, true);
		}
		$playButtons .= $display->podcastLink($node);
		if ($enable_ratings == "true"){
			$playButtons .= $display->rateButton($node, true);		
		}
		$playButtons .= " &nbsp; ";
		
		// Let's open the block
		$this->blockHeader(word("Album"). ": ". $title. $dispYear. "&nbsp;",$playButtons);
		$this->blockBodyOpen();
			
		?>
		<table width="100%" cellpadding="2" cellspacing="0" border="0">
			<tr>
				<td width="100%" <?php if ($desc == ""){ echo 'align="center"'; $align = "";} else { $align = "left";} ?>>
					<?php
						// If there is no description let's make the art bigger
						$rating = $display->rating($node,true);
						if ($rating <> "" and $desc == ""){
							echo $rating. "<br>";
						}
						
						if ($jzUSER->getSetting('stream')) {
							$display->playLink($node,$display->returnImage($art,$node->getName(),$artSize,$artSize,"fit",false,false,$align,"5","5"));
						} else {
							$display->Image($art,$node->getName(),$artSize,$artSize,"fit",false,false,$align,"5","5");
						}
						if ($cms_mode == "false"){
							echo '<span class="jz_artistDesc">';
						}
						if ($rating <> "" and $desc <> ""){
							echo $rating. "<br>";
						}

						$desc = str_replace( "\n", '<p>', $desc );
						echo $display->returnShortName($desc,$desc_truncate);
						if (strlen($desc) > $desc_truncate){
							$url_array = array();
							$url_array['jz_path'] = $node->getPath("String");
							$url_array['action'] = "popup";
							$url_array['ptype'] = "readmore";

							echo ' <a href="'. urlize($url_array). '" onclick="openPopup(this, 450, 450); return false;">read more</a>';
						}
						if (!isNothing($node->getDateAdded()) && $node->getDateAdded() <> "0"){
							echo "<br>". word("Added"). ": ". date($short_date,$node->getDateAdded());
						}
						if ($cms_mode == "false"){
							echo '</span>';	
						}
					?>
				</td>
			</tr>
		</table>
		<?php		
		
		// let's close the block
		$this->blockBodyClose();	
		$this->blockSpacer();
		
		// Now lets finish out the cache
		$display->endCache();
	}
		
		/**
		* Shows the site news
		* 
		* @author Ross Carlson
		* @version 01/26/05
		* @since 01/26/05
		* @param $node the Node we are viewing
		*/
		function slickSiteNews($node){
		
			
		}
		
		/**
		* Shows the Slick formated chart system
		* 
		* @author Ross Carlson
		* @version 01/26/05
		* @since 01/26/05
		* @param $node The node we are viewing so we can filter
		*/
		function showSlickCharts($node,$types = false){
			global $album_name_truncate, $img_tiny_play, $display_charts, $chart_timeout_days; 
			
			$be = new jzBackend();
			if ($be->hasFeature('charts') === false) {
			  return;
			}
						
			$display = new jzDisplay();
			if ($display->startCache("showSlickCharts",$node, $chart_timeout_days)){
				return;
			}
			
			// First let's make sure they even want the charts
			if ($display_charts <> "true"){return;}
			
			$smarty = smartySetup();
			
			$title = word("Charts");
			if ($node->getName() <> ""){
				$title = word("Charts"). " :: ". $node->getName();
			}
			$smarty->assign('title', $title);			
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-charts.tpl');
			
			// Now lets finish out the cache
			$display->endCache();
		}		
		
		
		/**
		* Draws the block space for the featured artist/album
		* 
		* @author Ross Carlson
		* @version 01/30/05
		* @since 01/30/05
		* @param object $node The node that we are looking at so we can filter
		*/
		function showFeaturedBlock($node, $return = false){
		
			// First we need to know if this is going to display or not
			$featuredArtists = $node->getFeatured(distanceTo("artist",$node));
			$featuredAlbums = $node->getFeatured(distanceTo("album",$node));
			
			if ($featuredArtists === false) { $featuredArtists = array(); }
			if ($featuredAlbums === false) { $featuredAlbums = array(); }
			
			$retData = true;
			if (count($featuredArtists) == 0 and count($featuredAlbums) == 0){ $retData = false; }

			if ($retData == false or $return == true){
				return $retData;
			}

			// Now let's show a featured artist
			if (count($featuredArtists) <> 0){
				$this->showFeatured($featuredArtists, 300);
				$this->blockSpacer();
			}
			
			// Now let's show a featured album
			if (count($featuredAlbums) <> 0){
				$this->showFeatured($featuredAlbums, 300);
				$this->blockSpacer();
			}
		}
	  
	  	/**
		* Draws the Featured Artist/Album Block
		* 
		* @author Ross Carlson
		* @version 01/19/05
		* @since 01/19/05
		* @param object $node The node that we are looking at so we can filter
		*/
		function showFeatured($featured, $truncate = 150, $slimDisplay = false){
			global $album_name_truncate, $img_tiny_play, $artist_truncate, $album_name_truncate, $cms_mode;
			
			// Let's set the featured width
			$featWidth = 250;
				
			// Should we just return?
			if (!is_array($featured)){return;}
			
			// Let's make sure there are featured items
			// Now let's grab the featured artists
			shuffle($featured);
			$item = $featured[0];
			if ($item == ""){return;}
			
			// Let's setup our objects
			$display = new jzDisplay();
			$smarty = smartySetup();
									
			$title = word("Editors Pick"). ": ";
			$title2 = "<strong>". $display->playLink($item, $img_tiny_play, $item->getName(), false, true). $display->link($item, $display->returnShortName($item->getName(),$artist_truncate), word("Browse"). ": ". $item->getName(), false, true). "</strong>";
			$smarty->assign('art',"");
			if (($art = $item->getMainArt("75x75")) <> false) {
				$smarty->assign('art', $display->link($item, $display->returnImage($art,$item->getName(),75,75,"limit",false,false,"left","3","3"), false, false, true));
			}
			$smarty->assign('title', $title);
			$smarty->assign('title2', $title2);
			
			// Should we display the artist?
			if ($item->getPType() == "album"){
				$parent = $item->getParent();
				$smarty->assign('artist_play_button', $display->playLink($parent, $img_tiny_play, $parent->getName(), false, true));
				$smarty->assign('artist', $display->link($parent, $parent->getName(), $parent->getName(), false, true));
			}
						
			$desc_truncate = $truncate;
			$desc = $item->getDescription();
			$smarty->assign('description', $display->returnShortName($desc,$desc_truncate));
			$smarty->assign('read_more',"");
			if (strlen($desc) > $desc_truncate){
				$url_array = array();
				$url_array['jz_path'] = $item->getPath("String");
				$url_array['action'] = "popup";
				$url_array['ptype'] = "readmore";
				$smarty->assign('read_more', '<a href="'. urlize($url_array). '" onclick="openPopup(this, 450, 450); return false;"> - '. word("read more"). '</a>');
			}
			
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-editors-pick.tpl');
		}
			  
		/**
		* Draws the Jinzora Radio Block using the data from the current node
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		* @param object $node The object to create the radio from
		*/
		function slickRadioBlock($node){
			global $show_radio,$jzUSER;
			
			// First do they even want this?
			if ($show_radio <> true || !checkPermission($jzUSER,'play',$node->getPath("String"))){return;}
			if ($node->getAncestor("artist") === false) {
			  return;
			}
			$node = $node->getAncestor("artist");
			// Let's startup Smarty
			$smarty = smartySetup();
			$smarty->assign('title', word("Jinzora Radio"));			
			$smarty->display(SMARTY_ROOT. 'templates/slick/radio-block.tpl');
		}
		
		/**
		* Draws the Jinzora similar albums block
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		* @param string $artist The artist that we are getting similar artist data from
		* @param bolean $limit Should we limit how many results we get back
		*/
		function slickSimilarAlbumBlock($element, $limit = false){
			global $show_similar;
			
			// First do they even want this?
			if ($show_similar <> true){return;}
			
			$display = new jzDisplay();
			if ($display->startCache("slickSimilarAlbumBlock",$element)){
				return;
			}

			$this->similarAlbumBlock($element, $limit);	
			
				// Now lets finish out the cache
			$display->endCache();
		}
	
		/**
		* Draws the Jinzora similar artist block
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		* @param string $artist The artist that we are getting similar artist data from
		* @param string $onlyMatches Should we only display artists that are actually in your collection
		* @param bolean $limit Should we limit how many results we get back
		*/
		function slickSimilarArtistBlock($artist, $onlyMatches = false, $limit = 10){
			global $jzSERVICES, $album_name_truncate, $img_tiny_play, $show_similar;
			
			// First do they even want this?
			if ($show_similar <> true){return;}
			
			$display = new jzDisplay();
			if ($display->startCache("slickSimilarArtistBlock",$artist)){
				return;
			}

			$this->similarArtistBlock($artist, $onlyMatches, $limit);
			
			// Now lets finish out the cache
			$display->endCache();
		}
		
		/**
		* Draws the Jinzora Recommends block
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		*/
		function jinzoraRecommends(){
		  global $jzUSER;
			// Let's create and open our block
			$this->blockHeader("Recommended");
			$this->blockBodyOpen();
			
			// Now let's return the suggestions for this user
			$recoArray = $jzUSER->getRecommendations();
			
			?>
			<nobr>
	
			</nobr>
			<?php
			$this->blockBodyClose();
		}
		
		/**
		* Draws the Jinzora Popular Artists Block
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		*/
		function popularArtists(){
			
			// Let's open and create our block
			$this->blockHeader("Popular Artists");
			$this->blockBodyOpen();	
			?>
			<nobr>
			<a href="">Miles Davis</a><br>
			<a href="">David Sanborn</a><br>
			<a href="">Dave Bruebeck</a><br>
			<a href="">Dave Matthews Band</a><br>
			<a href="">Dave Koz</a><br>
			<a href="">Miles Davis</a><br>
			</nobr>
			<?php
			$this->blockBodyClose();
		}
		
		/**
		* Draws the block that displays all tracks from an artist on the artist page
		* 
		* @author Ross Carlson
		* @version 01/13/05
		* @since 01/13/05
		* @param $node The node of the item we are viewing
		*/
		function displaySlickAllTracks($node){		
			$arr = array();
			$arr['jz_path'] = $node->getPath("String");
			$viewAll = '<a href="'. urlize($arr). '">'. word("View Sampler"). '</a>';
			$this->blockHeader($node->getName(). " ". word("Sampler"), $viewAll);
			$this->blockBodyOpen();
			$this->displayAllTracks($node);
			$this->blockBodyClose();
		}
		
		/**
		* Draws the block that displays a random sampling of tracks from an artist
		* 
		* @author Ross Carlson
		* @version 01/13/05
		* @since 01/13/05
		* @param $node The node of the item we are viewing
		*/
		function displaySlickSampler($node){	
			$arr = array();
			$arr['jz_path'] = $node->getPath("String");
			$arr['action'] = "viewalltracks";
			$viewAll = '<a href="'. urlize($arr). '">'. word("View All Tracks"). '</a>';

			// Let's setup Smarty
			$smarty = smartySetup();
			$smarty->assign('viewAll', $viewAll);
			$smarty->assign('title', $node->getName(). " ". word("Sampler"));
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-sampler.tpl');
		}
		
		/**
		* Draws the spacer table for the left navigation blocks
		*/
		function leftNavBlockSpacer(){
			echo '<table cellpadding="1"><tr><td height="1"></td></tr></table>';
		}
		
		/**
		* Draws the opening table for the left navigation blocks
		*/
		function leftNavBlockOpen(){
			echo '<table width="148" cellpadding="4" cellspacing="0"><tr><td width="100%" class="jz_block_td">';
		}
		
		/**
		* Draws the closing table for the left navigation blocks
		*/
		function leftNavBlockClose(){
			echo '</td></tr></table>';
		}		
	  
	  /**
		* Draws the header for the blocks
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		* @param string $title The title for the block
		* @param string $right The data that should go in the top right of the block
		*/
		function blockHeader($title = "", $right = "", $title2 = ""){
			global $root_dir;
			?>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td height="23" class="jz_main_block_topl">&nbsp;</td>
					<td width="50%" class="jz_main_block_topm" nowrap>
						<span class="headertextshadow">
							<strong>
								<?php echo $title; ?>
								<font color="<?php echo jz_bg_color; ?>" class="headertext">
									<?php echo $title; ?>
								</font>
							</strong>
						</span>
						<?php
							if ($title2 <> ""){ echo $title2; }
						?>
					</td>
					<td width="50%" align="right" class="jz_main_block_topm" nowrap>
						<?php echo $right; ?>
					</td>
					<td class="jz_main_block_topr">&nbsp;</td>
				</tr>
			</table>
			<?php
		}
		
		/**
		* Draws the opening of the table in a block, comes right after the header
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		*/
		function blockBodyOpen(){
			echo '<table width="100%" cellspacing="0" cellpadding="2"><tr><td colspan="4" class="jz_block_td">';
		}
		
		/**
		* Draws the close of the table for a block
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		*/
		function blockBodyclose(){
			echo '</td></tr></table>';
		}
		
		/**
		* Draws a small spacer row between blocks
		* 
		* @author Ross Carlson
		* @version 01/11/05
		* @since 01/11/05
		*/
		function blockSpacer(){
			echo '<table width="100%" cellpadding="2" cellspacing="0" border="0"><tr><td width="100%" height="5"></td></tr></table>';
		}
		
		
		/**
		 * Displays a table of the given video tracks.
		 *
		 * @author Ross Carlson
		 * @version 2.26.06
		 * @since 2.26.06
		 * @param array $tracks The array of objects of each track
		 * @param $purpose The type of this track table. One of:
		 * generic|album|search|sample|sample-all
		 */
	  function videoTable($tracks, $purpose = false){
			global $web_root, $root_dir, $row_colors;
			
			$display = new jzDisplay();
			
			// Let's setup Smarty
			$smarty = smartySetup();
			
			// Let's define our variables
			$i=0;
			foreach ($tracks as $child) {
				$metaData = $child->getMeta();
				$tArr[$i]['name'] = $display->returnShortName($child->getName(),25);
				$tArr[$i]['length'] = convertSecMins($metaData['length']);				
				$tArr[$i]['playlink'] = $display->playlink($child, $child->getName(), false, false, true, false, true);		
				$tArr[$i]['downloadlink'] = $display->downloadButton($child, true, false, false, true);				
				$tArr[$i]['i'] = $i;
				$art = $child->getMainArt("125x125", true, "video");
				if ($art){
					$tArr[$i]['art'] = $art;
				} else {
					$tArr[$i]['art'] = false;
				}
				$tArr[$i]['playcount'] = $child->getPlayCount();
				$i++;
			}
			
			$smarty->assign('tracks', $tArr);
			$smarty->assign('i', 0);
			$smarty->assign('cols', 3);
			$smarty->assign('jz_row1', $row_colors[1]);
			$smarty->assign('jz_row2', $row_colors[2]);
			$smarty->assign('word_watch_now', word("Watch Now"));			
			$smarty->assign('word_download', word("Download"));			
			$smarty->assign('word_viewed', word("Viewed"));			
			
			// Now let's include the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/videotable.tpl');
		}

	  /**
	   * Displays a table of the given nodes.
	   *
	   * @author Ross Carlson
	   * @version 11/30/04
	   * @since 11/30/04
	   * @param object $node The node that we are viewing
	   */
	  function nodeTable($nodes,$type=false){
		global $media_dir, $jinzora_skin, $hierarchy, $album_name_truncate, $row_colors, 
		  $img_more, $img_email, $img_rate, $img_discuss, $num_other_albums, $jzUSER;					
		
		if (sizeof($nodes) == 0) return;
		// Let's setup the new display object
		$display = &new jzDisplay();
		
		// Now let's setup the big table to display everything
		$i=0;
		$c = 0;
		  ?>
		  <table class="jz_track_table" width="100%" cellpadding="3" cellspacing="0" border="0">
		 <?php
		 foreach ($nodes as $child) {
		 	
		 	$path = $child->getPath("String");
		   ?>
		   <tr class="<?php echo $row_colors[$i]; ?>">
		   <?php
		   if ($type <> "search"){
		   ?>
		   <td width="1%" valign="top" class="jz_track_table_songs_td">
		   <input class="jz_checkbox" type="checkbox" name="track-<?php echo $c++; ?>" value="<?php echo $path; ?>">
		   </td>
		   <?php
		   }
		   ?>
		   <td width="1%" valign="top" class="jz_track_table_songs_td" nowrap>
			   	<?php 
				 	echo $display->downloadButton($child);
			  	echo $display->playButton($child);
					?>
		   </td>
		   <td width="100%" valign="top" class="jz_track_table_songs_td">
		   <?php 
		   $parent = $child->getNaturalParent();
		   if ($parent->getLevel() > 0) {
			 $display->link($parent, $parent->getName("String"), $parent->getName(), "jz_track_table_songs_href"); 
			 echo " / ";
		   }
		   $display->link($child, $child->getName("String"), $child->getName(), "jz_track_table_songs_href"); 
		   ?></a>
		   </td>
		   <td width="12%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr>&nbsp;</nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr> &nbsp; &nbsp; </nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr></nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr> &nbsp;  &nbsp; </nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr> &nbsp;  </nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr> &nbsp;  &nbsp; </nobr>
		   </td>
		   <td width="10%" align="center" valign="top" class="jz_track_table_songs_td">
		   <nobr> &nbsp;  &nbsp; </nobr>
		   </td>
		   </tr>
		   <?php		
		   $i = 1 - $i; // cool trick ;)
		 }
		
		// Now let's set a field with the number of checkboxes that were here
		echo "</table><br>";
	  }
	
	
		/**
		* Displays the random albums block
		* @author Ross Carlson
		* @version 12/22/04
		* @since 12/22/04
		* @param object $node the node that we are looking at
		* @param string $level The level we are looking at, like a subartist
		*/
		function slickRandomAlbums(&$node, $level = ""){
			global  $show_album_art,$random_albums,$random_per_slot, $random_albums, $random_per_slot, $random_rate, $row_colors, $root_dir, $jzUSER, $show_album_art, $random_art_size;

			// Should we show this?
			if ($show_album_art == "false"){return;}
			if ($_GET['action'] == "viewallart"){return;}
			
			// Now let's get a random amount of albums with album art
			$artArray = $node->getSubNodes("nodes",distanceTo("album",$node),true,$random_albums*$random_per_slot,true);
			if (count($artArray) == 0){return;}
			$title = word("Random Albums");
			if ($node->getName() <> ""){
				$title = word("Random Albums"). " :: ". $node->getName();
			}
			$url_array = array();
			$url_array['jz_path'] = $node->getPath("String");
			$url_array['action'] = "viewallart";
			$showLink = '<a href="'. urlize($url_array). '">'. word("View All Art"). '</a> &nbsp; ';
			
			// Should we be here????
			if ($random_albums == "0" or $show_album_art == "false"){ return; }
			
			// Let's setup the new display object
			$display = &new jzDisplay();
			
			/* // WTF is this doing here? (BJD 6/21/06)
			// Let's make sure they didn't pass the data already
			if ($valArray){
				$artArray = $valArray;
			} else {
				// Now let's get a random amount of albums with album art
				$artArray = $node->getSubNodes("nodes",distanceTo("album",$node),true,$random_albums*$random_per_slot,true);
			}
	
			// Now let's see how much we got back and make sure we just shouldn't return
			if (count($artArray) == 0){ return; }
			*/			
			// Let's startup Smarty
			$smarty = smartySetup();
			
			$smarty->assign('title', $title);
			$smarty->assign('showLink', $showLink);
				
			// Now let's display the template
			$smarty->display(SMARTY_ROOT. 'templates/slick/block-random-albums.tpl');		
	
			// Now let's add the Javascript for the rotations
			?>
			<SCRIPT LANGUAGE=JAVASCRIPT TYPE="TEXT/JAVASCRIPT"><!--\
				
				//you may add your image file or text below
				$c=1;
				// Now let's create the variables
				<?php
					$c=1;
					while ($c < ($random_albums + 1)){
						echo "var imgItem". $c. "=new Array()". "\n";
						$c++;
					}
				
					// Now let's build the first array with ALL the data so we can break it up later
					$c=0;
					for ($i=0; $i < count($artArray); $i++){					
						$albumName_long = $artArray[$i]->getName();
						$albumName = returnItemShortName($albumName_long,12);	 					
						$albumLink = str_replace('"',"\\\"",$display->link($artArray[$i],$albumName, word("Browse"). ": ". $albumName_long, "jz_random_art_block", true));
						
						$artist = $artArray[$i]->getNaturalParent();
						$artistName_long = $artist->getName();	 
						$artistName = returnItemShortName($artistName_long,12);	 
						$artistLink = str_replace('"',"\\\"",$display->link($artist,$artistName, word("Browse"). ": ". $artistName_long, "jz_random_art_block", true));
						$artsize = explode("x",$random_art_size);
						$art = $artArray[$i]->getMainArt($random_art_size);
						$imgSrc = str_replace('"',"'",$display->returnImage($art,$artistName_long,$artsize[0],$artsize[1],"fixed"));
						$item_link = str_replace('"',"'",$display->link($artArray[$i],$imgSrc, $albumName_long, "jz_random_art_block", true));
						
						// Now, can they stream?
						if ($jzUSER->getSetting('stream')){
							$playLink = str_replace('"',"\\\"",$display->playLink($artArray[$i],word("Play"), word("Play"). ": ". $albumName_long, "jz_random_art_block", true));
							$randLink = str_replace('"',"\\\"",$display->playLink($artArray[$i],word("Play Random"), word("Play Random"). ": ". $albumName_long, "jz_random_art_block", true, true));
							$dispLink = $playLink. " - ". $randLink;
						} else {
							$dispLink = "";
						}
						
						// Let's make sure they aren'te view only				
						$arrayVar = "<center>". $artistLink. "<br>". $albumLink. "<br>". $item_link;
						if ($jzUSER->getSetting('stream')){
							$arrayVar .= "<br>". $dispLink. "</center>";
						}
						$fullArray[] = $arrayVar;					
					}
					
					// Now we need to get the different arrays
					$c=1; $start=0;
					while ($c < ($random_albums + 1)){
						$dataArray = array_slice($fullArray,$start,$random_per_slot);
						for ($ctr=0; $ctr < count($dataArray); $ctr++){
							echo "imgItem". $c. "[". $ctr. "]=\"". $dataArray[$ctr]. '"'. "\n";
						}
		
						// Now let's move on
						$start = $start+$random_per_slot;
						$c++;
					}
					
					// Now let's create the functions
					$c=1;
					while ($c < ($random_albums + 1)){
						?>					
						var current<?php echo $c; ?>=0
						<?php
						$c++;
					}
					$c=1;
					while ($c < ($random_albums + 1)){
						?>
						var ns6=document.getElementById&&!document.all
						function changeItem<?php echo $c; ?>(){
							if(document.layers){
								document.layer1.document.write(imgItem<?php echo $c; ?>[current<?php echo $c; ?>])
								document.layer1.document.close()
							}
							if(ns6)document.getElementById("div<?php echo $c; ?>").innerHTML=imgItem<?php echo $c; ?>[current<?php echo $c; ?>]
							{
								if(document.all){
									div<?php echo $c; ?>.innerHTML=imgItem<?php echo $c; ?>[current<?php echo $c; ?>]
								}
							}
							if (current<?php echo $c; ?>==<?php echo ($random_per_slot -1); ?>) current<?php echo $c; ?>=0
							else current<?php echo $c; ?>++
							<?php 
								if ($random_per_slot <> 1){
									?>
									setTimeout("changeItem<?php echo $c; ?>()",<?php echo $random_rate; ?>)
									<?php
								}
							?>
						}
						<?php
						$c++;
					}
					$c=1;
					while ($c < ($random_albums + 1)){
						?>					
						changeItem<?php echo $c; ?>();
						<?php
						$c++;
					}
				?>
				
				//-->
			</script>
			<?php
		}					 
	}
?>
