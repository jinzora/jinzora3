<?php if (!defined(JZ_SECURE_ACCESS)) die('Security breach detected.');

	/**
	 * Sitewide settings editor
	 *
	 * @author Ben Dodson
	 * @since 2/2/05
	 * @version 2/2/05
	 *
	 **/
	  global $include_path,$jzUSER,$my_frontend;
	  
	  if ($jzUSER->getSetting('admin') !== true) {
	    exit();
	  }
	  
	  $display = new jzDisplay();
	  $page_array = array();
	  $page_array['action'] = 'popup';
	  $page_array['ptype'] = 'sitesettings';
	  if (isset($_GET['subpage'])) {
	    $page_array['subpage'] = $_GET['subpage'];
	  }
	  if (isset($_GET['subsubpage'])) {
	    $page_array['subsubpage'] = $_GET['subsubpage'];
	  }
	  if (isset($_GET['set_fe'])) {
	    $page_array['set_fe'] = $_GET['set_fe'];
	  }
	  if (isset($_POST['set_fe'])) {
	    $page_array['set_fe'] = $_POST['set_fe'];
	  }
	  
	  $this->displayPageTop("",word('Site Settings'));
	  $this->openBlock();
	  
	  // Index page:
	  if (!isset($_GET['subpage'])) {
	    echo "<table><tr><td>";
	    $page_array['subpage'] =  "main";
	    echo '<a href="'.urlize($page_array).'">'. word('Main Settings'). '</a>';
	    echo "</td></tr><tr><td>";
	    
	    $page_array['subpage'] =  "services";
	    echo '<tr><td><a href="'.urlize($page_array).'">'. word('Services'). '</a>';
	    echo "</td></tr><tr><td>";
	    
	    $page_array['subpage'] =  "frontend";
	    echo '<tr><td> <a href="'.urlize($page_array).'">'. word('Frontend Settings'). '</a>';
	    echo "</td></tr></table>";
	    
	    //unset($page_array['subpage']);
	    
	    $this->closeBlock();
	    return;
	  }
	  if ($_GET['subpage'] == "frontend" && !isset($page_array['set_fe'])) {
  ?>
 <form method="POST" action="<?php echo urlize($page_array); ?>">
    <select class="jz_select" name="<?php echo jz_encode('set_fe');?>">Frontend: 
   <?php
    $arr = readDirInfo($include_path.'frontend/frontends',"dir");
 foreach ($arr as $a) {
   if (file_exists($include_path."frontend/frontends/${a}/settings.php")) {
     echo "<option value=\"".jz_encode($a)."\"";
     if ($a == $my_frontend) {
       echo ' selected';
     }
     echo ">$a</option>";
   }
 }
   ?>
   </select>
       &nbsp;<input type="submit" class="jz_submit" value="<?php echo word('Go'); ?>">
		<?php 
		$this->closeBlock();
 return;
	  }
	  if (isset($_POST['update_postsettings']) && $_GET['subpage'] != "main") {
	    echo word('Settings Updated.'). "<br><br>";
	  }
	  
	  $display->openSettingsTable(urlize($page_array));
	  
	  if ($_GET['subpage'] == "main") {
	    $settings_file = $include_path.'settings.php';
	    $settings_array = settingsToArray($settings_file);

	    $urla = array();
	    $urla['subpage'] = "main";
	    $urla['action'] = "popup";
	    $urla['ptype'] = "sitesettings";



	      echo '<center>';
	      echo "| ";
	      $urla['subsubpage'] = "system";
	      echo '<a href="'.urlize($urla).'">'.word("System") . "</a> | ";
	      $urla['subsubpage'] = "playlist";
	      echo '<a href="'.urlize($urla).'">'.word("Playlist") . "</a> | ";
	      $urla['subsubpage'] = "display";
	      echo '<a href="'.urlize($urla).'">'.word("Display") . "</a> | ";
	      $urla['subsubpage'] = "image";
	      echo '<a href="'.urlize($urla).'">'.word("Image") . "</a> | ";
	      $urla['subsubpage'] = "groupware";
	      echo '<a href="'.urlize($urla).'">'.word("Groupware") . "</a> | ";
	      $urla['subsubpage'] = "jukebox";
	      echo '<a href="'.urlize($urla).'">'.word("Jukebox") . "</a> | ";
	      echo '<br>| ';
	      $urla['subsubpage'] = "resample";
	      echo '<a href="'.urlize($urla).'">'.word("Resampling") . "</a> | ";
	      $urla['subsubpage'] = "charts";
	      echo '<a href="'.urlize($urla).'">'.word("Charts/Random Albums") . "</a> | ";
	      $urla['subsubpage'] = "downloads";
	      echo '<a href="'.urlize($urla).'">'.word("Downloads") . "</a> | ";
	      $urla['subsubpage'] = "email";
	      echo '<a href="'.urlize($urla).'">'.word("Email") . "</a> | ";
	      $urla['subsubpage'] = "keywords";
	      echo '<a href="'.urlize($urla).'">'.word("Keywords") . "</a> | ";
	      $urla['subsubpage'] = "extauth";
	      echo '<a href="'.urlize($urla).'">'.word("External Auth") . "</a> | ";
	      echo "</center><br>";

	      if (isset($_POST['update_postsettings'])) {
		echo "<strong>".word("Settings Updated.")."</strong><br>";
	      }
	      echo "<br>";

	    switch ($_GET['subsubpage']) {
	    case "system":
	      $display->settingsTextbox("media_dirs","media_dirs",$settings_array);
	      $display->settingsTextbox("web_dirs","web_dirs",$settings_array);
	      $display->settingsCheckbox("live_update","live_update",$settings_array);
	      $display->settingsTextbox("audio_types","audio_types",$settings_array);
	      $display->settingsTextbox("video_types","video_types",$settings_array);
	      $display->settingsTextbox("ext_graphic","ext_graphic",$settings_array);
	      $display->settingsTextbox("track_num_seperator","track_num_seperator",$settings_array);
	      $display->settingsTextbox("date_format","date_format",$settings_array);
	      $display->settingsTextbox("short_date","short_date",$settings_array);
	      $display->settingsCheckbox("allow_filesystem_modify","allow_filesystem_modify",$settings_array);
	      $display->settingsCheckbox("allow_id3_modify","allow_id3_modify",$settings_array);
	      $display->settingsCheckbox("gzip_handler","gzip_handler",$settings_array);
	      $display->settingsCheckbox("ssl_stream","ssl_stream",$settings_array);
	      $display->settingsDropdown("media_lock_mode","media_lock_mode",array("off","track","album","artist","genre"),$settings_array);
	      break;
	    case "playlist":
	      $display->settingsCheckbox("enable_playlist","enable_playlist",$settings_array);
	      $display->settingsTextbox("playlist_ext","playlist_ext",$settings_array);
	      $display->settingsCheckbox("use_ext_playlists","use_ext_playlists",$settings_array);
	      $display->settingsTextbox("max_playlist_length","max_playlist_length",$settings_array);
	      $display->settingsTextbox("random_play_amounts","random_play_amounts",$settings_array);
	      $display->settingsTextbox("default_random_count","default_random_count",$settings_array);
	      $display->settingsTextbox("default_random_type","default_random_type",$settings_array);
	      $display->settingsTextbox("embedded_player","embedded_player",$settings_array);
	      break;
	    case "display":
	      $display->settingsTextbox("site_title","site_title",$settings_array);
	      $display->settingsDropdownDirectory("jinzora_skin","jinzora_skin",$include_path.'style',"dir",$settings_array);
	      $display->settingsDropdownDirectory("frontend","frontend",$include_path.'frontend/frontends',"dir",$settings_array);
	      $display->settingsDropdown("jz_lang_file","jz_lang_file",getLanguageList(),$settings_array);
	      $display->settingsCheckbox("allow_lang_choice","allow_lang_choice",$settings_array);
	      $display->settingsCheckbox("allow_style_choice","allow_style_choice",$settings_array);
	      $display->settingsCheckbox("allow_interface_choice","allow_interface_choice",$settings_array);
	      $display->settingsCheckbox("allow_player_choice","allow_player_choice",$settings_array);
	      $display->settingCheckbox("show_page_load_time","show_page_load_time",$settings_array);
	      $display->settingsCheckbox("show_sub_numbers","show_sub_numbers",$settings_array);
	      $display->settingsTextbox("quick_list_truncate","quick_list_truncate",$settings_array);
	      $display->settingsTextbox("album_name_truncate","album_name_truncate",$settings_array);
	      $display->settingsCheckbox("sort_by_year","sort_by_year",$settings_array);
	      $display->settingsTextbox("num_other_albums","num_other_albums",$settings_array);	      
	      $display->settingsCheckbox("header_drops","header_drops",$settings_array);
	      $display->settingsDropdown("genre_drop","genre_drop",array("true","false","popup"),$settings_array);
	      $display->settingsDropdown("artist_drop","artist_drop",array("true","false","popup"),$settings_array);
	      $display->settingsDropdown("album_drop","album_drop",array("true","false","popup"),$settings_array);
	      $display->settingsDropdown("song_drop","song_drop",array("true","false","popup"),$settings_array);
	      $display->settingsCheckbox("quick_drop","quick_drop",$settings_array);
	      $display->settingsTextbox("days_for_new","days_for_new",$settings_array);
	      $display->settingsTextbox("hide_id3_comments","hide_id3_comments",$settings_array);
	      $display->settingsTextbox("show_all_checkboxes","show_all_checkboxes",$settings_array);
	      $display->settingsTextbox("status_blocks_refresh","status_blocks_refresh",$settings_array);
	      $display->settingsCheckbox("compare_ignores_the","compare_ignores_the",$settings_array);
	      $display->settingsCheckbox("handle_compilations","handle_compilations",$settings_array);
	      $display->settingsTextbox("embedded_header","embedded_header",$settings_array);	      
	      $display->settingsTextbox("embedded_footer","embedded_footer",$settings_array);	      
	      break;
	    case "image":
	      $display->settingsCheckbox("resize_images","resize_images",$settings_array);
	      $display->settingsCheckbox("keep_porportions","keep_porportions",$settings_array);
	      $display->settingsCheckbox("auto_search_art","auto_search_art",$settings_array);
	      $display->settingsCheckbox("create_blank_art","create_blank_art",$settings_array);
	      //$display->settingsTextbox("default_art","default_art",$settings_array);	
	      break;
	    case "groupware":
	      $display->settingsCheckbox("enable_discussions","enable_discussions",$settings_array);
	      $display->settingsCheckbox("enable_requests","enable_requests",$settings_array);
	      $display->settingsCheckbox("enable_ratings","enable_ratings",$settings_array);
	      $display->settingsTextbox("rating_weight","rating_weight",$settings_array);
	      $display->settingsCheckbox("track_plays","track_plays",$settings_array);
	      $display->settingsCheckbox("display_downloads","display_downloads",$settings_array);
	      $display->settingsCheckbox("secure_links","secure_links",$settings_array);
	      $display->settingsCheckbox("user_tracking_display","user_tracking_display",$settings_array);
	      $display->settingsTextbox("user_tracking_age","user_tracking_age",$settings_array);
	      $display->settingsCheckbox("disable_random","disable_random",$settings_array);
	      $display->settingsTextbox("info_level","info_level",$settings_array);
	      $display->settingsCheckbox("track_play_only","track_play_only",$settings_array);
	      $display->settingsCheckbox("allow_clips","allow_clips",$settings_array);
	      $display->settingsTextbox("clip_length","clip_length",$settings_array);
	      $display->settingsTextbox("clip_start","clip_start",$settings_array);
	      break;
	    case "jukebox":
	      $display->settingsCheckbox("jukebox","jukebox",$settings_array);
	      $display->settingsDropdown("jukebox_display","jukebox_display",array("default","small","off"),$settings_array);
	      $display->settingsDropdown("jukebox_default_addtype","jukebox_default_addtype",array("current","begin","end","replace"),$settings_array);
	      $display->settingsTextbox("default_jukebox","default_jukebox",$settings_array);
	      $display->settingsTextbox("jb_volumes","jb_volumes",$settings_array);
	      break;
	    case "resample":
	      $display->settingsCheckbox("allow_resample","allow_resample",$settings_array);
	      $display->settingsCheckbox("force_resample","force_resample",$settings_array);
	      $display->settingsCheckbox("allow_resample_downloads","allow_resample_downloads",$settings_array);
	      $display->settingsTextbox("default_resample","default_resample",$settings_array);
	      $display->settingsTextbox("resampleRates","resampleRates",$settings_array);
	      $display->settingsTextbox("lame_cmd","lame_cmd",$settings_array);
	      $display->settingsTextbox("lame_opts","lame_opts",$settings_array);
	      $display->settingsTextbox("path_to_lame","path_to_lame",$settings_array);
	      $display->settingsTextbox("path_to_flac","path_to_flac",$settings_array);
	      $display->settingsTextbox("path_to_oggenc","path_to_oggenc",$settings_array);
	      $display->settingsTextbox("path_to_oggdec","path_to_oggdec",$settings_array);
	      $display->settingsTextbox("path_to_mpc","path_to_mpc",$settings_array);
	      $display->settingsTextbox("path_to_mpcenc","path_to_mpcenc",$settings_array);
	      $display->settingsTextbox("path_to_wavpack","path_to_wavpack",$settings_array);
	      $display->settingsTextbox("path_to_wavunpack","path_to_wavunpack",$settings_array);
	      $display->settingsTextbox("path_to_wmadec","path_to_wmadec",$settings_array);
	      $display->settingsTextbox("path_to_shn","path_to_shn",$settings_array);
	      $display->settingsTextbox("path_to_mplayer","path_to_mplayer",$settings_array);
	      $display->settingsTextbox("mplayer_opts","mplayer_opts",$settings_array);
	      $display->settingsTextbox("always_resample","always_resample",$settings_array);
	      $display->settingsTextbox("always_resample_rate","always_resample_rate",$settings_array);
	      $display->settingsTextbox("resample_cache_size","resample_cache_size",$settings_array);
	      break;
	    case "charts":
	      $display->settingsCheckbox("display_charts","display_charts",$settings_array);
	      $display->settingsTextbox("chart_types","chart_types",$settings_array);
	      $display->settingsTextbox("num_items_in_charts","num_items_in_charts",$settings_array);
	      $display->settingsTextbox("chart_timeout_days","chart_timeout_days",$settings_array);
	      $display->settingsTextbox("random_albums","random_albums",$settings_array);
	      $display->settingsTextbox("random_per_slot","random_per_slot",$settings_array);
	      $display->settingsTextbox("random_rate","random_rate",$settings_array);
	      $display->settingsTextbox("random_art_size","random_art_size",$settings_array);
	      $display->settingsCheckbox("rss_in_charts","rss_in_charts",$settings_array);
	      break;
	    case "downloads":
	      $display->settingsTextbox("multiple_download_mode","multiple_download_mode",$settings_array);
	      $display->settingsTextbox("single_download_mode","single_download_mode",$settings_array);
	      break;
	    case "email":
	      $display->settingsCheckbox("allow_send_email","allow_send_email",$settings_array);
	      //$display->settingsTextbox("email_from_address","email_from_address",$settings_array);
	      //$display->settingsTextbox("email_from_name","email_from_name",$settings_array);
	      //$display->settingsTextbox("email_server","email_server",$settings_array);
	      break;
	    case "keywords":
	      $display->settingsTextbox("keyword_radio","keyword_radio",$settings_array);
	      $display->settingsTextbox("keyword_random","keyword_random",$settings_array);
	      $display->settingsTextbox("keyword_play","keyword_play",$settings_array);
	      $display->settingsTextbox("keyword_track","keyword_track",$settings_array);
	      $display->settingsTextbox("keyword_album","keyword_album",$settings_array);
	      $display->settingsTextbox("keyword_artist","keyword_artist",$settings_array);
	      $display->settingsTextbox("keyword_genre","keyword_genre",$settings_array);
	      $display->settingsTextbox("keyword_lyrics","keyword_lyrics",$settings_array);
	      $display->settingsTextbox("keyword_limit","keyword_limit",$settings_array);
	      $display->settingsTextbox("keyword_id","keyword_id",$settings_array);
	      break;
	    case "extauth":
	      $display->settingsCheckbox(word("Enable REMOTE_USER"),"http_auth_enable",$settings_array);
	      $display->settingsTextbox(word("Anonymous REMOTE_USER"),"http_auth_anon_name",$settings_array);
	      $display->settingsCheckbox(word("Auto-Create New Users"),"http_auth_auto_create",$settings_array);
	      $be = new jzBackend();
	      $keys = array_keys($be->loadData('userclasses'));
	      $display->settingsDropdown(word("New User Template:"),'http_auth_newuser_template',$keys,$settings_array);
	      break;
	    default:
	      $this->closeBlock();
	      return;
	    }
	    /*
	    foreach ($settings_array as $key => $val) {
	      // The settingsTextbox (and other) functions update the array for us
	      // on a form submit. No other form handling is needed,
	      // other than to write the data back to the file!
	      // Plus, settings aren't modified if they aren't in the form.
	      if ($key == "jinzora_skin") {
		$display->settingsDropdownDirectory($key,$key,$include_dir."style","dir",$settings_array);
	      } else if ($key == "frontend") {
		$display->settingsDropdownDirectory($key,$key,$include_dir."frontend/frontends","dir",$settings_array);
	      } else {
		$display->settingsTextbox($key,$key,$settings_array);
	      }
	    }
	    */
	  } else if ($_GET['subpage'] == "services") {
	    $settings_file = $include_path.'services/settings.php';
	    $settings_array = settingsToArray($settings_file);
	    $display->settingsDropdownDirectory(word("Lyrics"), "service_lyrics", $include_path.'services/services/lyrics','file',$settings_array);
	    $display->settingsDropdownDirectory(word("Similar Artists"), "service_similar", $include_path.'services/services/similar','file',$settings_array);
	    $display->settingsDropdownDirectory(word("Links"), "service_link", $include_path.'services/services/link','file',$settings_array);
	    $display->settingsDropdownDirectory(word("Metadata Retrieval"), "service_metadata", $include_path.'services/services/metadata','file',$settings_array);
	    //$display->settingsDropdownDirectory(word("ID3 Tagging"), "service_tagdata", $include_path.'services/services/tagdata','file',$settings_array);
	  } else if ($_GET['subpage'] == "frontend") {
	    $settings_file = $include_path."frontend/frontends/".$page_array['set_fe']."/settings.php";
	    $settings_array = settingsToArray($settings_file);      
	    foreach ($settings_array as $key => $val) {
	      $display->settingsTextbox($key,$key,$settings_array);      
	    }
	  }
	  
	  $display->closeSettingsTable(is_writeable($settings_file));
	  //echo "&nbsp;";
	  //$this->closeButton();
	  if (isset($_POST['update_postsettings']) && is_writeable($settings_file)) {
	    arrayToSettings($settings_array,$settings_file);
	  }
	  $this->closeBlock();

?>
