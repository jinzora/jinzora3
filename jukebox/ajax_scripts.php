<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.'); ?>
<?php include_once($include_path. "jukebox/class.php"); ?>

<script>
var jb_types = ["stream"];
<?php
$jbArr = jzJukebox::getJbArr();
for ($i = 0; $i < count($jbArr); $i++) {
  echo 'jb_types.push("'.$jbArr[$i]['type']."\");\n";
}
?>

function setJbFormCommand(cmd) {
	document.getElementById('jbPlaylistForm').elements['command'].value = cmd;
}

function setPlayback(obj) {
  playback = obj.options[obj.selectedIndex].value;
  if (playback != "stream") {
    playback = "jukebox";
  } else {
    playback = streamto;
  }

  // javascript hook for a jukebox
  if (playback == "jukebox" && jb_types[obj.selectedIndex] == "junction") {
    junction_jukebox_init();
    js_jukebox = junction_jukebox;
  } else {
    js_jukebox = function() { return true; }
  }
}

$(function(){
  setPlayback(document.getElementById("jukeboxSelect"));
});

function jukeboxUpdater() {
  updateJukebox(false);
  setTimeout('jukeboxUpdater()',10*1000);
}

function updateJukebox(direct_call) {
  obj = document.getElementById("jukeboxSelect");
  if (obj != null && obj != false) {
    setPlayback(obj);
    x_ajaxJukebox(obj.options[obj.selectedIndex].value, direct_call, updateJukebox_cb);
  } else {
    x_ajaxJukebox(false, direct_call, updateJukebox_cb);
  }
}

function updateJukebox_cb(a) {
  if (a != "") {
    document.getElementById("jukebox").innerHTML = a;
    NextTicker_start();
    CurTicker_start();
    if (typeof(displayCountdown) == "function") {
      displayCountdown();
    }
  }
}

function updateSmallJukebox() {
  obj = document.getElementById("smallJukeboxSelect");  
  if (obj != null && obj != false) {
    setPlayback(obj);
    x_ajaxSmallJukebox(obj.options[obj.selectedIndex].value, sm_text, sm_buttons, sm_linebreaks, updateSmallJukebox_cb);
  }
}

function updateSmallJukebox_cb(a) {
  document.getElementById("smallJukebox").innerHTML = a;
}

function sendJukeboxRequest(cmd, param) {
  if (param == null) {
    x_ajaxJukeboxRequest(cmd, sendJukeboxRequest_cb);
  } else {
    x_ajaxJukeboxRequest(cmd, param, sendJukeboxRequest_cb);
  }
}

function sendJukeboxVol() {
  obj = document.getElementById("jukeboxVolumeSelect");
  if (obj != null && obj != false) {
    x_ajaxJukeboxRequest('volume',obj.options[obj.selectedIndex].value,sendJukeboxRequest_cb);
  }
}

function sendJukeboxAddType() {
  obj = document.getElementById("jukeboxAddTypeSelect");
  if (obj != null && obj != false) {
    x_ajaxJukeboxRequest('addwhere',obj.options[obj.selectedIndex].value,sendJukeboxRequest_cb);
  }
}

function sendJukeboxForm() {
  obj = document.getElementById("jukeboxJumpToSelect");
  if (obj != null && obj != false) {
  	selectedItems = new Array();
  	total = 0;
  	for (i = 0; i < obj.length; i++) {
  		if (obj.options[i].selected) {
  			selectedItems[total] = obj.options[i].index;
  			total++;
  		}
  	}
    if (total == 0) { return false; }

    cmd = document.getElementById('jbPlaylistForm').elements['command'].value;

    // don't worry about a serverside update for these:
    if (cmd == "moveup" || cmd == "movedown" || cmd == "delone") {
      cb_func = nothing;
    } else {
      cb_func = sendJukeboxRequest_cb;
    }

    x_ajaxJukeboxRequest(cmd, selectedItems,cb_func);
    
    // same logic as in the server-side jukebox code.
    // the sync is a little funny.
    scrollTop = obj.scrollTop;
    if (cmd == "moveup") {
      i = 0;
      while (i < total && selectedItems[i] == i) {
        i++;
      }
      
      while (i < total) {
        swap = obj.options[selectedItems[i]-1].text;
        swapFontWeight = obj.options[selectedItems[i]-1].style.fontWeight;

        obj.options[selectedItems[i]-1].selected = true;
        obj.options[selectedItems[i]-1].text = obj.options[selectedItems[i]].text;
        obj.options[selectedItems[i]-1].style.fontWeight = obj.options[selectedItems[i]].style.fontWeight;

        obj.options[selectedItems[i]].selected = false;
        obj.options[selectedItems[i]].text = swap;
        obj.options[selectedItems[i]].style.fontWeight = swapFontWeight;


	i++;
      }
    } else if (cmd == "movedown") {
      i = total-1;
      j = obj.options.length-1;
      while (i >= 0 && selectedItems[i] == j) {
        i--; j--;
      }
      while (i >= 0) {
        swap = obj.options[selectedItems[i]+1].text;
        swapFontWeight = obj.options[selectedItems[i]+1].style.fontWeight;

        obj.options[selectedItems[i]+1].selected = true;
        obj.options[selectedItems[i]+1].text = obj.options[selectedItems[i]].text;
        obj.options[selectedItems[i]+1].style.fontWeight = obj.options[selectedItems[i]].style.fontWeight;

        obj.options[selectedItems[i]].selected = false;
        obj.options[selectedItems[i]].text = swap;
        obj.options[selectedItems[i]].style.fontWeight = swapFontWeight;

	i--;
      }
    } else if (cmd == "delone") {
      for (i = obj.options.length-1; i >= 0; i--) {
        if (selectedItems[selectedItems.length-1] == i) {
          selectedItems.pop();
          obj.remove(i);
        }
      } 
    }
    obj.scrollTop = scrollTop;
  }
}

function sendJukeboxRequest_cb(a) {
  // Update everything!
  // 2 ways: update all the elements
  // or just refresh the jukebox.
  // The entire jukebox is actually more responsive, so let's do that:
  // TODO: Update the small jukebox. How should we handle the parameters?
  // You can make some hidden fields in there to embed the vars as form elements.
  // Then you can even remove them as parameters if you want.
  obj = document.getElementById("smallJukebox");
  if (obj) {
    // TODO: How to pass the actual 3 parameters here from above???
    updateSmallJukebox();
  }
  obj = document.getElementById("jukebox");
  if (obj) {
    updateJukebox(true);
  }
  return false;


  obj = document.getElementById("jukeboxNowPlaying");
  if (obj != null && obj != false) {
    x_ajaxJukeboxNowPlaying(updateJukeboxNowPlaying_cb);
  }

  obj = document.getElementById("jukeboxNextTrack");
  if (obj != null && obj != false) {
    x_ajaxJukeboxNextTrack(updateJukeboxNextTrack_cb);
  }

  // I didn't finish adding things, since updateJukebox() is snappier.

  return false;
}


function updateJukeboxNowPlaying_cb(a) {
  obj = document.getElementById("jukeboxNowPlaying");
  if (obj != null && obj != false) {
    obj.innerHTML = a;
  }

  obj = document.getElementById("jukeboxNextTrack");
  if (obj != null && obj != false) {
    obj.innerHTML = a;
  }
}

function updateJukeboxNextTrack_cb(a) {
  obj = document.getElementById("jukeboxNextTrack");
  if (obj != null && obj != false) {
    obj.innerHTML = a;
  }
}

<?php
  // This code is associated with the JunctionJukebox.
  // We don't have a general-purpose way of adding javascript
  // to a jukebox implementation, so it's here for now.
?>
var jb_actor = {};
// jb_actor.onMessageReceived = function(msg) { alert(msg.url); }
// jb_actor.onActivityJoin = function() { alert("joined."); }

var junction_jukebox_initialized = false;
function junction_jukebox_init() {
  if (junction_jukebox_initialized) return;
  junction_jukebox_initialized = true;

  var session = Cookie.get("junctionbox_session");
  if (session == null) {
    session = window.location.hostname + "_junctionbox";
    Cookie.set("junctionbox_session", session);
  }
  var config = { host: "sb.openjunction.org" };
  var activity = { sessionID: session };

  JX.getInstance(config).newJunction(activity,jb_actor);
}

function junction_jukebox(url) {
  var msg = {action:"org.jinzora.jukebox.PLAYLIST"};
  msg.extras = {playlist: url};
  
  var where = $("#jukeboxAddTypeSelect").val();
  if (where == 'replace') {
    where = 0;
  } else if (where == 'end') {
    where = 1;
  } else if (where == 'current') {
    where = 2;
  } else if (where == 'begin') {
    where = 3;
  } else {
    where = 0;
  }
  msg.extras.addtype = where;
  jb_actor.sendMessageToSession(msg);
  return false;
}
</script>
<?php
  global $root_dir;
  foreach($jbArr as $j) {
    if ($j['type'] == 'junction') {
      echo '<script type="text/javascript" src="http://openjunction.github.com/JSJunction/json2.js"></script>';
      echo '<script type="text/javascript" src="http://openjunction.github.com/JSJunction/strophejs/1.0.1/strophe.js"></script>';
      echo '<script type="text/javascript" src="http://openjunction.github.com/JSJunction/junction/0.6.8/junction.js"></script>';
      echo '<script type="text/javascript" src="'. $root_dir. '/jukebox/jukeboxes/junctionbox/cookie.js"></script>';
      break;
    }
  }
?>
