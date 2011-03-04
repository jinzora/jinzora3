{php}
	global $this_page, $img_play, $artist_truncate, $img_random_play, 
		$directory_level, $web_root, $root_dir, $img_more, $media_dir, $show_sub_numbers, $show_all_checkboxes, 
		$img_more_dis, $img_play_dis, $img_random_play_dis, $url_seperator, $days_for_new, $img_rate, $enable_ratings,
		$enable_discussion, $img_discuss, $show_sub_numbers, $disable_random, $info_level, 
		$enable_playlist, $track_play_only, $skin, $bg_c, $text_c, $img_discuss_dis, $hierarchy, $random_albums, $frontend, $include_path,
		$cols_in_genre,$alphabet_depth, $days_for_new, $jz_path;
		
	$node = new jzMediaNode($jz_path);
	$display = new jzDisplay();
	$lvl = isset($_GET['jz_letter']) ? ($_GET['jz_level'] + $node->getLevel() - 1): $node->getLevel();
	switch ($hierarchy[$lvl]){
		case "genre":
			$pg_title = word("Genres");
		break;
		case "artist":
			$pg_title = word("Artists");
		break;
		case "album":
			$pg_title = word("Albums");
		break;
		default:
			$pg_title = word("Genres");
		break;
	}
	if (isset($_GET['jz_letter'])) {
		$retArray = $node->getAlphabetical($_GET['jz_letter'],"nodes",$_GET['jz_level']);
	} else {
		$retArray = $node->getSubNodes("nodes",$_SESSION['jz_node_distance']);
	}
	sortElements($retArray,"name");
	
	if (isset($_GET['jz_letter'])) {
		$letter = $_GET['jz_letter'];
	} else {
		$letter = '';
	}
{/php}
<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td height="23" class="jz_main_block_topl">&nbsp;</td>
		<td width="50%" class="jz_main_block_topm" nowrap>
			<span class="headertextshadow">
				<strong>
					{$title}
					<font color="{$jz_bg_color}" class="headertext">
						{$title}
					</font>
				</strong>
			</span>
		</td>
		<td width="50%" align="right" class="jz_main_block_topm" nowrap>
		</td>
		<td class="jz_main_block_topr">&nbsp;</td>
	</tr>
</table>
<table width="100%" cellspacing="0" cellpadding="2">
	<tr>
		<td colspan="4" class="jz_block_td">
			<table width="100%" cellspacing="0">
				<tr>
				{php}					
				$folder_per_column = round(count($retArray) / $cols_in_genre + .49,0);
				$first_loop = "";
				$ctr = 1;
						
				// Now let's loop through that array, displaying the items 
				for ($e=0; $e < count($retArray); $e++){
					if ($ctr == 1){
						if ($first_loop == ""){
							$first_loop = "no";
						} else {
							echo '</td>';
						}
						echo '<td valign="top">';
					}
			
					{/php}
					<table width="100%" cellspacing="0">
						<tr>
							<td valign="top">
								<nobr>
								{php} 
									$display->statsButton($retArray[$e]); 
									$display->playButton($retArray[$e]); 
									$display->randomPlayButton($retArray[$e]); 
									if ($enable_ratings == "true"){
										$display->rateButton($retArray[$e]);
									}
								{/php}
								</nobr>
							 </td>
							
							<td width="100%" valign="top">
								<nobr>
								{php}							
								// Now let's display the link
								$title = $retArray[$e]->getName();
								$shortTitle = returnItemShortName($title,$artist_truncate);
								$subCountN = $retArray[$e]->getSubNodeCount("nodes");
								if ($subCountN > 0){
									
									$shortTitle .= " (". $subCountN;
									$title .= " (". $subCountN;
									
									if ($retArray[$e]->getSubNodeCount("tracks") > 0) {
										$shortTitle .= "+";
										$title .= "+";
									}
									
									$shortTitle .= ")";
									$title .= ")";
								}
								$linkSub = $retArray[$e];
								if ($subCountN == 1) {
								  $mysubs = $linkSub->getSubNodes();
								  if (! $mysubs[0]->isLeaf()) {
								    $linkSub = $mysubs[0];
								  }
								}
								$display->link($linkSub, $shortTitle, word("Browse: "). $title);	
								
								// Let's see if this is new or not
								if ($days = $retArray[$e]->newSince($days_for_new)){
									echo icon('new', 
									          array('literal' => $display->returnToolTip($days. " ". word("days ago"), word("New Since"))));
								}
	
								//if ($new_from <> ""){ echo ' <img src="'. $root_dir. '/style/'. $skin. '/new.gif" border=0 '. $new_data. '>'; }
									
								// Now let's see if they wanted ratings
								if ($enable_ratings == "true"){
									echo "<br>&nbsp;";
									$display->rating($retArray[$e]);
								}
						
								// Now let's return the description
								//$descData = $retArray[$e]->getShortDescription();
								if (isset($descData)){
									echo "<br>&nbsp;". stripslashes($descData). "<br><br>";
								}
						
								// Now let's close out
								{/php}
								<nobr>
							</td>
						</tr>
					</table>
					{php}				
					// Now let's increment for out column counting
					if ($ctr == $folder_per_column){ $ctr=1; } else { $ctr++; }
				} // go to next loop
				echo '<input type="hidden" name="numboxes" value="'. $e. '">';
				{/php}
				</table>
			</td>
	</tr>
</table>