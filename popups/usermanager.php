<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
global $resampleRates, $frontend, $jinzora_skin, $include_path, $jzUSER, $jzSERVICES, $cms_mode, $http_auth_enable;

$display = new jzDisplay();
$be = new jzBackend();
// First let's display the top of the page and open the main block
$ucount = sizeof($jzUSER->listUsers());

$this->displayPageTop("", word("User Manager (%s users)", $ucount));
$this->openBlock();

if ($jzUSER->getSetting('admin') === false) {
	echo word("Insufficient permissions");
	$this->closeBlock();
	return;
}
// Make a menu button for later:
$urla = array ();
$urla['action'] = "popup";
$urla['ptype'] = "usermanager";

$MENU_BUTTON = '<form method="POST" action="' . urlize($urla) . '">';
$MENU_BUTTON .= '<input type="submit" class="jz_submit" name="menu" value="' . word('Menu') . '">';
$MENU_BUTTON .= '</form>';

// Different features:
if (!isset ($_GET['subaction'])) {
	echo '<table>';

	echo '<tr><td>';
	$urla['subaction'] = "adduser";
	echo '<a href="' . urlize($urla) . '">' . word("Add a user") . '</a>';
	echo "</td></tr>";

	if ($ucount > 2) {
		echo '<tr><td>';
		$urla['subaction'] = "edituser";
		echo '<a href="' . urlize($urla) . '">' . word("Modify a user") . '</a>';
		echo "</td></tr>";
	}

	if ($ucount > 2) {
		echo '<tr><td>';
		$urla['subaction'] = "removeuser";
		echo '<a href="' . urlize($urla) . '">' . word("Remove a user") . '</a>';
		echo "</td></tr>";
	}

	echo '<tr><td>';
	$urla['subaction'] = "editclasses";
	echo '<a href="' . urlize($urla) . '">' . word("Modify user templates") . '</a>';
	echo "</td></tr>";

	echo '<tr><td>';
	$urla['subaction'] = "default_access";
	echo '<a href="' . urlize($urla) . '">' . word("Edit default access") . '</a>';
	echo "</td></tr>";

	echo '<tr><td>';
	$urla['subaction'] = "registration";
	echo '<a href="' . urlize($urla) . '">' . word("Edit self-registration") . '</a>';
	echo "</td></tr>";

	echo '</table>';
	$this->closeBlock();
	return;
}

// HANDLE USER CHANGES:
if ($_GET['subaction'] == "handleuser") {
	// Now, did they submit the form?
	if ($_POST['field1'] != $_POST['field2']) {
		echo word("Error: Password mismatch");
		return;
	}
}
if ($_GET['subaction'] == "handleuser" && ($_POST['templatetype'] == "customize") && !isset ($_POST['usr_interface'])) {
	if ($_GET['usermethod'] == "add") {
		$myid = $jzUSER->addUser($_POST['username'], $_POST['field1']);
		if ($myid === false) {
			echo word("Could not add user") . " " . $_POST['username'] . ".";
			return;
		}
	} else {
		$myid = $_POST['user_to_edit'];
		if ($_POST['field1'] != "jznoupd") {
			// update password
			$jzUSER->changePassword($_POST['field1'], $jzUSER->lookupName($myid));
		}
		// change name
		if (($oldname = $jzUSER->lookupName($myid)) != $_POST['username']) {
			$jzUSER->changeName($_POST['username'], $oldname);
		}
	}
	if ($_POST['userclass'] == "jznewtemplate") {
		$settings = array ();
		if (isset ($_POST['user_to_edit'])) {
			$settings = $jzUSER->loadSettings($_POST['user_to_edit']);
		} else {
			$settings = array ();
		}
	} else {
		$classes = $be->loadData('userclasses');
		$settings = $classes[$_POST['userclass']];
	}
	$post = $_POST;
	unset ($post['handleUser']);
	userManSettings("custom", $settings, 'handleuser', $post);
	return;
}

if ($_GET['subaction'] == "handleuser") {
	if ($_GET['usermethod'] == "add") {
		$myid = $jzUSER->addUser($_POST['username'], $_POST['field1']);
		if ($myid === false) {
			echo word("Could not add user") . " " . $_POST['username'] . ".";
			return;
		}
	} else {
		if (isset($_POST['user_to_edit'])) {
		    $myid = $_POST['user_to_edit'];
		} else {
			$list = $jzUSER->listUsers();
			foreach ($list as $id => $name) {
				if ($name == $_POST['username']) {
					$myid = $id;
				}
			} 
		}
		if (!isset($myid)) {
		  // okay, yes,  this is the worst piece of code in Jinzora.
		  $myid = $jzUSER->lookupUID(NOBODY);
		}
		if($myid == false) {
		  // need to add NOBODY
		  $myid = $jzUSER->addUser(NOBODY, "");
		  if($myid == false) {
		    echo word("Could not add user") . " " . word('Anonymous') . ".";
		    return;
		  }
		}
		if ($_POST['field1'] != "jznoupd") {
			// update password
			$jzUSER->changePassword($_POST['field1'], $jzUSER->lookupName($myid));
		}
		// change name
		if (($oldname = $jzUSER->lookupName($myid)) != $_POST['username']) {
			$jzUSER->changeName($_POST['username'], $oldname);
		}
	}
	$wipe = false;
	if ($_POST['templatetype'] == "sticky") {
			if ($_POST['userclass'] == "jznewtemplate") {
				echo "Cannot stick user to a blank template.";
				return;
			} else {
				$settings = array ();
				$settings['template'] = $_POST['userclass'];
				$wipe = true;
			}
			// CUSTOMIZE
	} else {
			if ($_POST['templatetype'] == "customize") {
				$settings = userPullSettings();
				$settings['template'] = "";
			} else {
				echo "Sorry, I don't know how to manage the user.";
				return;
			}
	}

	$un = ($_POST['username'] != NOBODY) ? $_POST['username'] : word('Anonymous');
	if (isset($settings['home_dir'])) {
		$settings['home_dir'] = str_replace('USERNAME', $un, $settings['home_dir']);
	}

	$jzUSER->setSettings($settings, $myid, $wipe);
	

	echo word("User") . ": " . $un . " " . word("updated");
	echo "<br><br><center>";
	echo $MENU_BUTTON . '&nbsp;';
	$this->closeButton();
	$this->closeBlock();
	return;

} else
	if ($_GET['subaction'] == "handleclass") {
		$settings = userPullSettings();
		$classes = $be->loadData("userclasses");
		if (!is_array($classes)) {
			$classes = array ();
		}
		$classes[$_POST['classname']] = $settings;
		$be->storeData('userclasses', $classes);

		echo word("Template updated.");
		echo "<br><br><center>";
		echo $MENU_BUTTON . '&nbsp;';
		$this->closeButton();
		$this->closeBlock();
		return;
	}
/** ** ** ** **/
// USER CLASS MANAGER
//

if ($_GET['subaction'] == "editclasses") {
	$urla = array ();
	$urla['action'] = "popup";
	$urla['ptype'] = "usermanager";
	$urla['subaction'] = "editclasses";

	if (!isset ($_GET['subsubaction'])) {
		echo '<table>';
		echo '<tr><td>';
		$urla['subsubaction'] = "add";
		echo '<a href="' . urlize($urla) . '">' . word("Add a template") . '</a>';
		echo "</td></tr>";

		$classes = $be->loadData('userclasses');
		if (!(!is_array($classes) || sizeof($classes) == 0)) {
			echo '<tr><td>';
			$urla['subsubaction'] = "edit";
			echo '<a href="' . urlize($urla) . '">' . word("Edit a template") . '</a>';
			echo "</td></tr>";

			echo '<tr><td>';
			$urla['subsubaction'] = "remove";
			echo '<a href="' . urlize($urla) . '">' . word("Remove a template") . '</a>';
			echo "</td></tr>";
		}
		echo '</table>';

	} else
		if ($_GET['subsubaction'] == "edit") {
			$urla['subsubaction'] = "edit2";
			echo '<table>';
			echo '<form method="POST" action="' . urlize($urla) . '">';
?><input type="hidden" name="update_settings" value="true"><?php

			echo '<tr><td>' . word("Template:");
			echo '</td><td>';
			echo '<select name="classname" class="jz_select">';
			$classes = $be->loadData('userclasses');
			$keys = array_keys($classes);
			foreach ($keys as $key) {
				echo '<option value="' . $key . '">' . $key;
			}
			echo '</select>';
			echo '</td></tr>';
			echo '<tr colspan="2"><td>';
			echo '<input type="submit" class="jz_submit" name="submit" value="' . word('Go') . '">';
			echo '</td></tr></form></table>';

		} else
			if ($_GET['subsubaction'] == "add" || $_GET['subsubaction'] == "edit2") {
				if ($_GET['subsubaction'] == "add") {
					$settings = array ();
					$settings['view'] = true;
					$settings['stream'] = true;
					$settings['powersearch'] = true;
					$settings['edit_prefs'] = true;
					$settings['frontend'] = $frontend;
					$settings['theme'] = $jinzora_skin;
					$settings['language'] = "english";
					$settings['playlist_type'] = "m3u";
					userManSettings("new", $settings);
				} else {
					$classes = $be->loadData('userclasses');
					if (!isset ($classes[$_POST['classname']])) {
						die("Invalid user template.");
					}
					$settings = $classes[$_POST['classname']];
					userManSettings("update", $settings);
				}

			} else
				if ($_GET['subsubaction'] == "remove") {
					if (!isset ($_POST['class_to_remove'])) {
						$list = $jzUSER->listUsers();
						$url_array = array ();
						$url_array['action'] = "popup";
						$url_array['ptype'] = "usermanager";
						$url_array['subaction'] = "editclasses";
						$url_array['subsubaction'] = "remove";

						echo '<form action="' . urlize($url_array) . '" method="POST">';
						echo '<input type="hidden" name="update_settings" value="true">';
						echo word("Template:") . '&nbsp';
						echo '<select name="class_to_remove" class="jz_input">';
						$classes = $be->loadData('userclasses');
						$keys = array_keys($classes);
						foreach ($keys as $key) {
							if ($key != NOBODY) {
								echo '<option value="' . $key . '">' . $key . '</option>';
							}
						}
						echo "</select>";
						echo '&nbsp;<input type="submit" class="jz_submit" value="Go">';
						echo '</form>';
					} else {
						$classes = $be->loadData('userclasses');
						unset ($classes[$_POST['class_to_remove']]);
						$be->storeData('userclasses', $classes);
						echo $_POST['class_to_remove'] . word(" has been removed.");
						echo '<br><br><center>';
						echo $MENU_BUTTON;
						echo '&nbsp;';
						$this->closeButton();
					}
					$this->closeBlock();
					return;
				}

	$this->closeBlock();
	return;
}

// SELF-REGISTRATION:
if ($_GET['subaction'] == "registration") {
	$be = new jzBackend();
	$data = $be->loadData('registration');
	if (!is_array($data)) {
		$data = array ();
	}
	$classes = $be->loadData('userclasses');

	if (!is_array($classes)) {
		$urla = array ();
		$urla['action'] = "popup";
		$urla['ptype'] = "usermanager";
		$urla['subaction'] = "editclasses";
		$urla['subsubaction'] = "add";
		echo word("<p>You must set up a user template before enabling user registration.</p>");
		echo '<p><a href="' . urlize($urla) . '">' . word("Click here to do add a user template.") . '</a></p>';

		return;
	}

	if (isset ($_POST['update_postsettings'])) {
		echo word("Settings updated") . "<br>";
	}
	$page_array = array ();
	$page_array['action'] = "popup";
	$page_array['ptype'] = "usermanager";
	$page_array['subaction'] = "registration";
	$display->openSettingsTable(urlize($page_array));
	$display->settingsCheckbox(word("Allow Self-Registration") . ":", 'allow_registration', $data);

	$keys = array_keys($classes);
	$display->settingsDropdown(word("User Template:"), 'classname', $keys, $data);

	$display->closeSettingsTable(true);
	if (isset ($_POST['update_postsettings'])) {
		$be->storeData('registration', $data);
	}
	return;
}

// * * * * * * * * //
// ANONYMOUS USER SUBSECTION
// * * * * * * * * //
if ($_GET['subaction'] == "default_access") {
	$_GET['subaction'] = "edituser";
	$myid = $jzUSER->lookupUID(NOBODY);
	if ($myid == "" || $myid == false) {
	  // need to add NOBODY
	  $myid = $jzUSER->addUser(NOBODY, "");
	  if ($myid == false) {
	    echo word("Could not add user") . " " . word('Anonymous') . ".";
	    return;
	  }
	}
	$_POST['user_to_edit'] = $myid;
}

// * * * * * * * * //
// EDIT USER SUBSECTION
// * * * * * * * * //
if ($_GET['subaction'] == "edituser") {
	if (!isset ($_POST['user_to_edit'])) {
		$list = $jzUSER->listUsers();
		$my_id = $jzUSER->getID();
		$url_array = array ();
		$url_array['action'] = "popup";
		$url_array['ptype'] = "usermanager";
		$url_array['subaction'] = "edituser";
		echo '<form action="' . urlize($url_array) . '" method="POST">';
		echo '<input type="hidden" name="update_settings" value="true">';
		echo word("User") . ": ";
		echo '<select name="user_to_edit" class="jz_input">';
		foreach ($list as $id => $name) {
			if ($name != NOBODY && $id != $my_id) {
				echo '<option value="' . $id . '">' . $name . '</option>';
			}
		}
		echo "</select>";
		echo '&nbsp;<input type="submit" class="jz_submit" value="Go">';
		echo '</form>';
		return;
	}
}

if ($_GET['subaction'] == "removeuser") {
	if (!isset ($_POST['user_to_remove'])) {
		$list = $jzUSER->listUsers();
		$url_array = array ();
		$url_array['action'] = "popup";
		$url_array['ptype'] = "usermanager";
		$url_array['subaction'] = "removeuser";
		echo '<form action="' . urlize($url_array) . '" method="POST">';
		echo '<input type="hidden" name="update_settings" value="true">';
		echo word("User") . ": ";
		echo '<select name="user_to_remove" class="jz_input">';
		foreach ($list as $id => $name) {
			if ($name != NOBODY) {
				echo '<option value="' . $id . '">' . $name . '</option>';
			}
		}
		echo "</select>";
		echo '&nbsp;<input type="submit" class="jz_submit" value="Go">';
		echo '</form>';
	} else {
		$name = $jzUSER->lookupName($_POST['user_to_remove']);
		$jzUSER->removeUser($_POST['user_to_remove']);
		echo $name . word(" has been removed.");
		echo '<br><br><center>';
		echo $MENU_BUTTON . '&nbsp;';
		$this->closeButton();
	}
	$this->closeBlock();
	return;
}

// * * * * * * * * * * * //
// ADD A USER SUBSECTION
// * * * * * * * * * * * //
// Let's show the form for this
$url_array = array ();
$url_array['action'] = "popup";
$url_array['ptype'] = "usermanager";
$url_array['subaction'] = "handleuser";

if (!isset ($_POST['user_to_edit'])) {
	$url_array['usermethod'] = "add";
	$edit_guest = false;
	$mid = $jzUSER->lookupUID(NOBODY);
} else {
	$url_array['usermethod'] = "update";
	if ($_POST['user_to_edit'] == $jzUSER->lookupUID(NOBODY)) {
		$edit_guest = true;
		$mid = $_POST['user_to_edit'];
	} else {
		$edit_guest = false;
		$mid = $_POST['user_to_edit'];
	}
}

if (isset($http_auth_enable) && $http_auth_enable == "true") {
  $edit_pwd = false;  
} else if ($cms_mode == "false") {
  $edit_pwd = true;
} else {
  $edit_pwd = false;
}


$jzUSER2 = new jzUser(false, $mid);

if ($_GET['subaction'] == "adduser") {
	// set some settings manually.
	$jzUSER2->settings['view'] = true;
	$jzUSER2->settings['stream'] = true;
	$jzUSER2->settings['lofi'] = true;
}
echo '<form action="' . urlize($url_array) . '" method="POST">';

if (isset ($_POST['user_to_edit'])) {
	echo '<input type="hidden" name="user_to_edit" value="' . $_POST['user_to_edit'] . '">';
}
?>
		      <input type="hidden" name="update_settings" value="true">
			 <table width="100%" cellpadding="3">
			 <?php if ($edit_guest === false) { ?>
			 <tr>
			 <td width="30%" align="right">
			 Username:
		    </td>
			 <td width="70%">
			<?php

if ($_GET['subaction'] == "adduser") {
	// Now let's return our tooltip													
?>
				<input type="input" name="username" class="jz_input">
				<?php

} else {
?>
				<input type="input" name="username" class="jz_input" value="<?php echo $jzUSER2->getName(); ?>">
				<?php

}
?>
			 </td>
			 </tr><?php if ($edit_pwd == true) { ?>
			 <tr>
			 <td width="30%" valign="top" align="right">
			 <?php echo word("Password"); ?>:
		    </td>
			 <td width="70%">
			<?php

if ($_GET['subaction'] == "adduser") {
?>
				 <input type="password" name="field1" class="jz_input"><br>
				 <input type="password" name="field2" class="jz_input">
				 <?php

} else {
?>
				 <input type="password" name="field1" class="jz_input" value="jznoupd"><br>
				 <input type="password" name="field2" class="jz_input" value="jznoupd">
				 <?php

}
?>
			 </td>
			 </tr><?php } else { ?>
			   <input type="hidden" name="field1" value="jznoupd">
			   <input type="hidden" name="field2" value="jznoupd"> <?php } ?>
			 <tr>
			 <td width="30%" valign="top" align="right">
			 <?php echo word("Full Name"); ?>:
			</td>
			 <td width="70%">
			 <input type="input" name="fullname" class="jz_input" value="<?php echo $jzUSER2->getSetting('fullname'); ?>">
			 </td>
			 </tr>
			 <tr>
			 <td width="30%" valign="top" align="right">
			 <?php echo word("Email Address"); ?>:
			</td>
			 <td width="70%">
			 <input type="input" name="email" class="jz_input" value="<?php echo $jzUSER2->getSetting('email'); ?>">
			 </td>
			 </tr>
			 <?php

} else { ?>
<input type="hidden" name="field1" value="jznoupd">
<input type="hidden" name="field2" value="jznoupd">
<input type="hidden" name="username" value="<?php echo NOBODY; ?>">
<?php } ?>
			 <tr>
			    <td width-"30%" valign="top" align="right">
			    <?php echo word("Template:"); ?>
			    </td>
			    <td width="70%">
						<?php

echo '<select name="userclass" class="jz_select">';
echo "<option value=\"jznewtemplate\">" . word('Blank Template');
$classes = $be->loadData('userclasses');
if (is_array($classes)) {
	$keys = array_keys($classes);
	$set = $jzUSER2->loadSettings();
	if (isset ($set['template'])) {
		$t = $set['template'];
	} else {
		$t = $keys[0];
	}
	foreach ($keys as $key) {
		echo "<option value=\"$key\"";
		if ($key == $t) {
			echo ' SELECTED';
		}
		echo ">$key";
	}
}
?>
		      </select>
			  </td></tr>
			  <tr><td width="30%" valign="top" align="right"><?php echo word('Management:'); ?></td><td>
		          <input type="radio" name="templatetype" value="sticky"><?php echo word('Update user when template is updated'); ?>
			  </td></tr>
			  <tr><td></td><td>
			  <input type="radio" name="templatetype" value="customize" checked><?php echo word("Customize this user's settings"); ?>
			  </td></tr>
			  <tr>
			  <td width="30%" valign="top">
			  </td>
			  <td width="70%">
			  <input type="submit" name="handleUser" value="<?php echo word("Go"); ?>" class="jz_submit">
			  </td>
			  </tr>


			</table>
			<?php

echo '</form>';
$this->closeBlock();


 /*
   * Pulls the user settings from POST to a settings array.
   * @author Ben Dodson
   * @since 12/7/05
   * @version 12/7/05
   **/
  function userPullSettings() {
    $settings = array();
    
    $settings['language'] = $_POST['usr_language'];		 
    $settings['theme'] = $_POST['usr_theme'];
    $settings['frontend'] = $_POST['usr_interface'];      
    $settings['home_dir'] = $_POST['home_dir'];
    if (isset($_POST['home_read'])) {
      $settings['home_read'] = "true";
    } else {
      $settings['home_read'] = "false";
    }
    if (isset($_POST['home_admin'])) {
      $settings['home_admin'] = "true";
    } else {
      $settings['home_admin'] = "false";
    }
    if (isset($_POST['home_upload'])) {
      $settings['home_upload'] = "true";
    } else {
      $settings['home_upload'] = "false";
    }
    
    $settings['cap_limit'] = $_POST['cap_limit'];
    $settings['cap_duration'] = $_POST['cap_duration'];
    $settings['cap_method'] = $_POST['cap_method'];
    
    $settings['player'] = $_POST['player'];
    
    $settings['resample_rate'] = $_POST['resample'];
    
    if (isset($_POST['lockresample'])) {
      $settings['resample_lock'] = "true";
    } else {
      $settings['resample_lock'] = "false";
    }

    if (isset($_POST['view'])) {
      $settings['view'] = "true";
    } else {
      $settings['view'] = "false";
    }
    
    if (isset($_POST['stream'])) {
      $settings['stream'] = "true";
    } else {
      $settings['stream'] = "false";
    }
    
    if (isset($_POST['download'])) {
      $settings['download'] = "true";
    } else {
      $settings['download'] = "false";
    }
    
    if (isset($_POST['lofi'])) {
      $settings['lofi'] = "true";
    } else {
      $settings['lofi'] = "false";
    }
    
    if (isset($_POST['jukebox_admin'])) {
      $settings['jukebox_admin'] = "true";
      $settings['jukebox'] = "true";
    } else {
      $settings['jukebox_admin'] = "false";
    }
    
    if (isset($_POST['jukebox_queue'])) {
      $settings['jukebox_queue'] = "true";
      $settings['jukebox'] = "true";
    } else {
      $settings['jukebox_queue'] = "false";
    }

    if (isset($_POST['force_clips'])) {
      $settings['force_clips'] = "true";
    } else {
      $settings['force_clips'] = "false";
    }
    
    
    if (isset($_POST['powersearch'])) {
      $settings['powersearch'] = "true";
    } else {
      $settings['powersearch'] = "false";
    }
    
    if (isset($_POST['admin'])) {
      $settings['admin'] = "true";
    } else {
      $settings['admin'] = "false";
    }
    
    if (isset($_POST['edit_prefs'])) {
      $settings['edit_prefs'] = "true";
    } else {
      $settings['edit_prefs'] = "false";
    }
    $settings['playlist_type'] = $_POST['pltype'];

		if (isset($_POST['fullname'])) {
      $settings['fullname'] = $_POST['fullname'];
    }
    
    if (isset($_POST['email'])) {
      $settings['email'] = $_POST['email'];
    }

    return $settings;
  }



  /*
   * Displays the user/template settings page
   * @param purpose: Why the function is being called:
   * One of: new|update|custom
   * @param settings: the preloaded settings
   * @author Ben Dodson
   **/

  function userManSettings($purpose, $settings = false, $subaction = false, $post = false) {
    global $jzSERVICES,$resampleRates,$include_path;
    $be = new jzBackend();
    $display = new jzDisplay();
    $url_array = array();
    $url_array['action'] = "popup";
    $url_array['ptype'] = "usermanager";
    if ($subaction === false) {
      $url_array['subaction'] = "handleclass";
    } else {
      $url_array['subaction'] = $subaction;
    }

    // Why PHP pisses me off.
    foreach ($settings as $k=>$v) {
      if ($v == "true") {
	$settings[$k] = true;
      } else if ($v == "false") {
	$settings[$k] = false;
      } else {
	$settings[$k] = $v;
      }
    }
      ?>
      <form method="POST" action="<?php echo urlize($url_array); ?>">
	 <input type="hidden" name="update_settings" value="true">
	 <?php 
	 if (is_array($post)) {
	   foreach ($post as $p => $v) {
	     echo '<input type="hidden" name="'.$p.'" value="'.$v.'">';
	   }
	 }
	?>
	 <table>
	 <?php if ($purpose != "custom") { ?>
	 <tr><td width="30%" valign="top" align="right">
	 <?php echo word("Template:"); ?>
	 </td><td width="70%">
	     <?php
	     if ($purpose == "new") {
	       ?>
	       <input name="classname" class="jz_input">
	       <?php
	     } else if ($purpose == "update") {
	       echo '<input type="hidden" name="classname" class="jz_input" value="'.$_POST['classname'].'">';
	       echo $_POST['classname'];
	     }
	   ?>
	     </td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr>
					   <?php } ?>
							<tr>
							<td width="30%" valign="top" align="right">
							<?php echo word("Interface"); ?>:
	       </td>
		   <td width="70%">
		   <?php
		   $overCode = $display->returnToolTip(word("INTERFACE_NOTE"), word("Default Interface"));
		 ?>
		   <select <?php echo $overCode; ?> name="usr_interface" class="jz_select" style="width:135px;">
			 <?php
			 // Let's get all the interfaces
			 $retArray = readDirInfo($include_path. "frontend/frontends","dir");
		    sort($retArray);
		    for($i=0;$i<count($retArray);$i++){
		      echo '<option ';
		      if ($settings['frontend'] == $retArray[$i]) { echo 'selected '; }
		      echo 'value="'. $retArray[$i]. '">'. $retArray[$i]. '</option>'. "\n";
		    }
		      ?>
			</select>
			</td>
			</tr>
			<tr>
			<td width="30%" valign="top" align="right">
			<?php echo word("Theme"); ?>:
			</td>
			<td width="70%">
			<?php
			$overCode = $display->returnToolTip(word("THEME_NOTE"), word("Default Theme"));
			 ?>
			<select <?php echo $overCode; ?> name="usr_theme" class="jz_select" style="width:135px;">
			<?php
			// Let's get all the interfaces
			$retArray = readDirInfo($include_path. "style","dir");
		    sort($retArray);
		    for($i=0;$i<count($retArray);$i++){
		      if ($retArray[$i] == "images"){continue;}
		      echo '<option ';
		      if ($settings['theme'] == $retArray[$i]) { echo 'selected '; }
		      echo 'value="'. $retArray[$i]. '">'. $retArray[$i]. '</option>'. "\n";
		    }
		      ?>
			</select>
			</td>
			</tr>
			<tr>
			<td width="30%" valign="top" align="right">
			<?php echo word("Language"); ?>:
			</td>
			<td width="70%">
			<?php
				$overCode = $display->returnToolTip(word("LANGUAGE_NOTE"), word("Default Language"));
			 ?>
			<select <?php echo $overCode; ?> name="usr_language" class="jz_select" style="width:135px;">
			<?php
			// Let's get all the interfaces
			$languages = getLanguageList();
		    for($i=0;$i<count($languages);$i++){
		      echo '<option ';
		      if ($languages[$i] == $settings['language']){echo ' selected '; }
		      echo 'value="'.$languages[$i]. '">'.$languages[$i]. '</option>'. "\n";
		    }
		      ?>
							</select>
							    </td>
							    </tr>
							    <tr>
							    <td width="30%" valign="top" align="right">
							    <?php echo word("Home Directory"); ?>:
							  </td>
							    <td width="70%">
								<?php
								$overCode = $display->returnToolTip(word("HOMEDIR_NOTE"), word("User Home Directory"));
								 ?>
							    <input <?php echo $overCode; ?> type="input" name="home_dir" class="jz_input" value="<?php echo $settings['home_dir']; ?>">
							    </td>
							    </tr>
							    <tr>
							    <td width="30%" valign="middle" align="right">
							    <?php echo word("Home Permissions"); ?>:
							  </td>
							    <td width="70%">
							    <br>
								<?php
									$overCode = $display->returnToolTip(word("HOMEREAD_NOTE"), word("Read Home Directory"));
									$overCode2 = $display->returnToolTip(word("HOMEADMIN_NOTE"), word("Admin Home Directory"));
									$overCode3 = $display->returnToolTip(word("HOMEUPLOAD_NOTE"), word("Home Directory Upload"));
								 ?>
							    <input <?php echo $overCode; ?> type="checkbox" name="home_read" class="jz_input" <?php if ($settings['home_read'] == true) { echo 'CHECKED'; } ?>> Read only from home directory<br>
							    <input <?php echo $overCode2; ?> type="checkbox" name="home_admin" class="jz_input" <?php if ($settings['home_admin'] == true) { echo 'CHECKED'; } ?>> Home directory admin<br>
							    <input <?php echo $overCode3; ?> type="checkbox" name="home_upload" class="jz_input" <?php if ($settings['home_upload'] == true) { echo 'CHECKED'; } ?>> Upload to home directory
							    <br><br>
							    </td>
							    </tr>
							    
							    <tr>
							    <td width="30%" valign="middle" align="right">
							    <?php echo word("User Rights"); ?>:
							  </td>
							    <td width="70%">
								<?php
									$overCode = $display->returnToolTip(word("VIEW_NOTE"), word("User can view media"));
									$overCode2 = $display->returnToolTip(word("STREAM_NOTE"), word("User can stream media"));
									$overCode3 = $display->returnToolTip(word("LOFI_NOTE"), word("User can access lo-fi tracks"));
									$overCode4 = $display->returnToolTip(word("DOWNLOAD_NOTE"), word("User can download"));
									$overCode5 = $display->returnToolTip(word("POWERSEARCH_NOTE"), word("User can power search"));
									$overCode6 = $display->returnToolTip(word("JUKEBOXQ_NOTE"), word("User can queue jukebox"));
									$overCode7 = $display->returnToolTip(word("JUKEBOXADMIN_NOTE"), word("User can admin jukebox"));
									$overCode8 = $display->returnToolTip(word("SITE_NOTE"), word("Site Admin"));
									$overCode9 = $display->returnToolTip(word("EDIT_NOTE"), word("Edit Preferences"));
                                                                        $overCode10 = $display->returnToolTip(word("FORCE_CLIPS_NOTE"), word("Force clip mode"));
								 ?>
							    <input <?php echo $overCode; ?> type="checkbox" name="view" class="jz_input" <?php if ($settings['view'] == true) { echo 'CHECKED'; } ?>> View
							    <input <?php echo $overCode2; ?> type="checkbox" name="stream" class="jz_input" <?php if ($settings['stream'] == true) { echo 'CHECKED'; } ?>> Stream
							    <input <?php echo $overCode3; ?> type="checkbox" name="lofi" class="jz_input" <?php if ($settings['lofi'] == true) { echo 'CHECKED'; } ?>> Lo-Fi<br>
							    <input <?php echo $overCode4; ?> type="checkbox" name="download" class="jz_input" <?php if ($settings['download'] == true) { echo 'CHECKED'; } ?>> Download
							    <input <?php echo $overCode5; ?> type="checkbox" name="powersearch" class="jz_input" <?php if ($settings['powersearch'] == true) { echo 'CHECKED'; } ?>> Power Search<br>
							    <input <?php echo $overCode6; ?> type="checkbox" name="jukebox_queue" class="jz_input" <?php if ($settings['jukebox_queue'] == true) { echo 'CHECKED'; } ?>> Jukebox Queue
							    <input <?php echo $overCode7; ?> type="checkbox" name="jukebox_admin" class="jz_input" <?php if ($settings['jukebox_admin'] == true) { echo 'CHECKED'; } ?>> Jukebox Admin<br>
							    <input <?php echo $overCode8; ?> type="checkbox" name="admin" class="jz_input" <?php if ($settings['admin'] == true) { echo 'CHECKED'; } ?>> Site Admin
						        <input <?php echo $overCode9; ?> type="checkbox" name="edit_prefs" class="jz_input" <?php if ($settings['edit_prefs'] == true) { echo 'CHECKED'; } ?>> Edit Prefs
                                                        <input <?php echo $overCode10; ?> type="checkbox" name="force_clips" class="jz_input" <?php if ($settings['force_clips'] == true) { echo 'CHECKED'; } ?>> Clips Only
																										    <br><br>
							    </td>
							    </tr>
							    <tr>
								<td width="30%" valign="top" align="right">
							    <?php echo word("Playlist Type"); ?>:
								</td><td width="70%">
								<?php
								$overCode = $display->returnToolTip(word("PLAYLIST_NOTE"), word("Playlist Type"));
								 ?>
								<select <?php echo $overCode; ?> name="pltype" class="jz_select" style="width:135px;">
							 <?php
						 $list = $jzSERVICES->getPLTypes();
						foreach ($list as $p=>$desc) {
						  echo '<option value="' . $p . '"';
						  if ($p == $settings['playlist_type']) {
						    echo ' selected';
						  }
						  echo '>' . $desc . '</option>';
						} ?>
				    </select></td></tr>

							    <tr>
							    <td width="30%" valign="top" align="right">
							    <?php echo word("Resample Rate"); ?>:
							  </td>
					<td width="70%">
					<?php
						$overCode = $display->returnToolTip(word("RESAMPLE_NOTE"), word("Resample Rate"));
						$overCode2 = $display->returnToolTip(word("LOCK_NOTE"), word("Resample Rate Lock"));
					 ?>
						<select <?php echo $overCode; ?> name="resample" class="jz_select" style="width:50px;">
							<option value="">-</option>
							<?php
								// Now let's create all the items based on their settings
								$reArr = explode("|",$resampleRates);
								for ($i=0; $i < count($reArr); $i++){
									echo '<option value="'. $reArr[$i]. '"';
									if ($settings['resample_rate'] == $reArr[$i]) {
									  echo ' selected';
									}
									echo '>'. $reArr[$i]. '</option>'. "\n";
								}
							?>
						</select> 
						    <input <?php echo $overCode2; ?> type="checkbox" name="lockresample" class="jz_input" <?php if ($settings['resample_lock'] == true) { echo 'CHECKED'; } ?>> <?php echo word('Locked'); ?>
					</td>
				</tr>
				<tr>
					<td width="30%" valign="top" align="right">
						<?php echo word("External Player"); ?>:
					</td>
					<td width="70%">
						<?php
						 $overCode = $display->returnToolTip(word("PLAYER_NOTE"), word("External Player"));
						?>
						<select <?php echo $overCode; ?> name="player" class="jz_select" style="width:135px;">
							<option value=""> - </option>
							<?php
								// Let's get all the interfaces
								$retArray = readDirInfo($include_path. "services/services/players","file");
								sort($retArray);
								for($i=0;$i<count($retArray);$i++){
									if (stripos(strrev($retArray[$i]),"php.") !== 0) {continue;}
									$val = substr($retArray[$i],0,-4);
									if ($val == "qt") {continue;}
									echo '<option value="'. $val. '"';
									if ($settings['player'] == $val) {
									  echo ' selected';
									}
									echo '>'. $val. '</option>'. "\n";
								}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="30%" valign="top" align="right">
						<?php echo word("Playback Limit"); ?>:
					</td>
					<td width="70%"><td></tr><tr><td></td><td>
					    <table><tr><td>
					    
						<?php
					    echo word("Limit:"); 
								echo '</td><td>';
					                        $overCode = $display->returnToolTip(word("Sets a streaming limit for users based on the size or number of songs played."), word("Playback Limit"));
								$cap_limit = $settings['cap_limit'];
								if (isNothing($cap_limit)) { $cap_limit = 0; }
						?>
					        <input <?php echo $overCode; ?> name="cap_limit" class="jz_select" style="width:35px;" value="<?php echo $cap_limit; ?>">
					</td></tr>
                                        <tr><td>					    
						<?php
					    echo word("Method:"); 
								echo '</td><td>';
					                        $overCode = $display->returnToolTip(word("Sets the method for limiting playback"), word("Limiting method"));
								$cap_method = $settings['cap_method'];
						?>
					        <select name="cap_method" class="jz_select" <?php echo $overCode; ?>>
					       <option value="size"<?php if ($cap_method == "size") { echo ' selected'; } ?>><?php echo word('Size (MB)');?></option>
					       <option value="number"<?php if ($cap_method == "number") { echo ' selected'; } ?>><?php echo word('Number');?></option>
					</td></tr>
                                        <tr><td>
					    
						<?php
					    echo word("Duration:"); 
								echo '</td><td>';
					                        $overCode = $display->returnToolTip(word("How long the limit lasts, in days."), word("Limit duration"));
								$cap_duration = $settings['cap_duration'];
								if (isNothing($cap_duration)) { $cap_duration = 30; }
						?>
					        <input <?php echo $overCode; ?> name="cap_duration" class="jz_select" style="width:35px;" value="<?php echo $cap_duration; ?>">
					</td></tr>
										  </table>
				</tr>
								
				
				<tr>
					<td width="30%" valign="top">
					</td>
					<td width="70%">
					<input type="submit" name="handlUpdate" value="<?php echo word("Save"); ?>" class="jz_submit">
					</td>
				</tr>
						    </table>
<?php
  }
	



?>
