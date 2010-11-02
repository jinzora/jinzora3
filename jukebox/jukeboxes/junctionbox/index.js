// Player Variables
var player;
var playlist = new Array();
var pos = -1;

var users = {};

var actor; // bump scope

var colors = {};
colors.normal = {};
colors.over = {};

colors.normal.playing='#9be';
colors.normal.queue='white';
colors.over.playing='#bcd';
colors.over.queue='#eee';


function loadPlayer() {
	player = niftyplayer('musicplayer');
	player.registerEvent('onSongOver','nextTrack()');
}

function nextTrack() {
  jumpTo(pos+1);
}

$(function(){
/* set up music player */

    var params = { allowScriptAccess: "always" };
    var atts = { id: "musicplayer" };
    swfobject.embedSWF("nifty/niftyplayer.swf", 
                       "musicplayer", "1", "1", "8", null, null, params, atts, 
				function(e) { 
					if (e.success) {
						// hack for no callback
						setTimeout('loadPlayer()',500);
					}
				}
			);


/* create junction */
var activity = 
  { ad: "org.jinzora.jukebox",
    friendlyName: "Jinzora Jukebox",
    roles: { "remote": { platforms: { "web" : { url:window.location.toString()}
				   ,"android": {package:"org.jinzora",
					    url:"http://prpl.stanford.edu/android/jinzora.apk"} }}
           , "jukebox": { platforms: {}} 
    }
  }

// debug
//activity.sessionID='jukebox4';

actor =
  { roles: ["jukebox"]
  , onMessageReceived : function(msg,header) 
      {
	if (msg.action) {
           // hack to reuse old code with RemoteIntent system
           if (msg.action=="org.jinzora.jukebox.PLAYLIST") {
		var cb = function(data) {
		  msg = parsePlaylistMsg(msg,data);
		  actor.onMessageReceived(msg,header);
		};
		$.get(msg.extras.playlist, cb);
                return;
	   } else if (msg.action=="org.jinzora.jukebox.PLAYLIST_SYNC_RESPONSE") {
	   	msg.action="playlist";
                var extras = msg.extras;
 		var pl = [];
		for (i=0;i<extras.names.length;i++){
		  pl.push({url:extras.urls[i], name:extras.names[i]});;
		}
		msg.playlist = pl;
		msg.jumpto=extras.pl_pos;
		msg.seekto=extras.seek_pos;
		msg.addtype="REPLACE";
		// continue to switch statement
	   } else if ((p = msg.action.indexOf("org.jinzora.jukebox.cmd.")) != -1) {
		var cmd = msg.action.substring(p+24).toLowerCase();
		msg.action=cmd;
		if (cmd=="jumpto") {
		  msg.pos=msg.extras.pos;
 		}
		// continue to switch statement
	   }


	switch (msg.action.toLowerCase()){
	case "nick":
	  setNick(header.from,msg.nick);
	break;
        case "joined":
          $('#connectionNotice').fadeIn(800,function(){$('#connectionNotice').fadeOut(800)});
	break;
	case "playlist":
	// order: (1) playlist (2) playback (3) gui
	  var old = playlist.length;
	  if (!addToList(msg.playlist,msg.addtype,header.from)) return;
	  if (msg.jumpto != undefined){ jumpTo(msg.jumpto,msg.seekto); }
	  else if (msg.addtype == 'REPLACE') { jumpTo(0); }
          else if (isNotPlaying(player)) {
	    jumpTo(old);
          }
	  drawList();
	break;
	}
	
	// update my playback:
	if (isFollowing(header.from))
	switch (msg.action) {
	case "play": 
          if (pos == -1) jumpTo(0);
	  else doPlay();
	break;
	case "pause":
	  doPause();
	break;
	case "prev":
	  jumpTo(pos-1);
	break;
	case "next":
	  jumpTo(pos+1);
	break;
	case "jumpto":
	  jumpTo(msg.pos);
	break;
        }
        }

	if (msg.status) {
		updatePlayingStatus(msg,header.from);
		
	}
      }
  }
  
actor.onActivityJoin = function() {
  
};

var session = Cookie.get("junctionbox_session");
if (session == null) {
  session = window.location.hostname + "_junctionbox";
  Cookie.set("junctionbox_session", session);
}

var config = { host: "sb.openjunction.org"};
activity.sessionID = session;

var jx = JX.getInstance(config).newJunction(activity,actor);

$('#invitationRemoteURL').attr('href',jx.getInvitationForWeb('remote'));
$('#remoteQR').attr('src',jx.getInvitationQR('remote'));

/* Helper JS */
function parsePlaylistMsg(msg,data) {
  var addtype = msg.extras.addtype;
  msg = {};
  switch (addtype) {
    case 0:
      msg.addtype="REPLACE";
      break;
    case 1:
      msg.addtype="END";
    case 2:
      msg.addtype="END"; // CURRENT
  }
  msg.action = "playlist";

  var playlist = [];
  // {name, url}
  var pl = data.split("\n");
  // TODO: this requires #extinfo entries
  for (i=2;i<pl.length;i+=2){
    var title = pl[i-1];
    title = title.substring(title.indexOf(",")+1);
    playlist.push({url:pl[i],name:title});
  }

  msg.playlist = playlist;
  return msg;
}

/* GUI Javascript */

$('#queueButton').click(function(){
	var msg = { action:'playlist' };
	msg.playlist = [ { url: $('#queueURL').val(), name: $('#queueURL').val() } ];
	actor.junction.sendMessageToSession(msg);
        $('#queueURL').val('');
  });

$('#button_prev').click(function() {
  actor.junction.sendMessageToSession({action:"prev"});
});

$('#button_pause').click(function() {
  actor.junction.sendMessageToSession({action:"pause"});
});

$('#button_play').click(function() {
  actor.junction.sendMessageToSession({action:"play"});
});

$('#button_next').click(function() {
  actor.junction.sendMessageToSession({action:"next"});
});

$('#buttonframe div').mouseenter(
  function(){
    $(this).css('background',colors.over.queue);
  }
);

$('#buttonframe div').mouseleave(
  function(){
    $(this).css('background',colors.normal.queue);
  }
);

$('#nickbutton').click(
  function(){
    actor.junction.sendMessageToSession({action:'nick',nick:$('#nickname').val()});
  }
);


// end onReady
});


$('.playlist_entry').live('click',
  function() {
    var entryID = this.id;
    // pl_entry_X
    var num = entryID.substring(9);
    
    // local-only or remote? remote for now
    msg = {action:"jumpto"};
    msg.pos = num;
    actor.junction.sendMessageToSession(msg);
  }
);

$('.playlist_entry').live('mouseover',
  function() {
    if (pos >= 0 && $(this).data('pos') ==  pos)
      $(this).css('background',colors.over.playing);
    else
      $(this).css('background',colors.over.queue);

    setInfo(this);
  }
);

$('.playlist_entry').live('mouseout',
  function() {
    if (pos >= 0 && $(this).data('pos') == pos)
      $(this).css('background',colors.normal.playing);
    else
      $(this).css('background',colors.normal.queue);

    clearInfo();
  }
);


/* Player Javascript */

function doPlay() {
  player.play();
  drawPlaying();
}

function doPause() {
  player.playToggle(); //player.pause();
}

// -1 => null
// -2 == length-1
function jumpTo(i,seek) {
  if (typeof(i)=='string') i = parseInt(i);
  if (i>=playlist.length || i == -1) {
    pos=-1;
    player.reset();
    drawPlaying();
    return;
  }
  else if (i < -1) {
    i = (i % playlist.length);
  }
  pos = i;
  if (seek){
    player.loadAndSeek(playlist[i].url,seek);
  } else {
    player.loadAndPlay(playlist[i].url);
  }
  drawPlaying();
  actor.junction.sendMessageToSession({status:'playing',pos:pos});
}

// queues up a track.
function addToList(list, addtype, from) {
  for (i=0;i<list.length;i++) {
    list[i].from = from;
    list[i].playing = [];
  }

  if (addtype=='REPLACE') {
    playlist = list;
    drawList();
    return true;
  } 
  else if (addtype=='END') {
    playlist=playlist.concat(list);
    drawList();
    return true;
  }
  // TODO: 'CURRENT'
}

function isNotPlaying(pl) {
  var status = pl.getState();
  return (status == 'stopped' ||
 	  status == 'empty' ||
	  status == 'error');
}

function drawList() {
  $('#playlistframe').html('');
  for (i=0;i<playlist.length;i++) {
    var entryID = 'pl_entry_'+i;
    var friendID = 'pl_friend_'+i;

    var html =  ''; 
  	html += '<div id="'+entryID+'" class="playlist_entry">'
	html += '<div id="'+friendID+'" class="entry_friends">&nbsp;</div>';
 	html += playlist[i].name;
	html += '</div>';
    $('#playlistframe').append(html);
    $('#'+entryID).data('pos',i);

  }
  drawPlaying();
}

function drawPlaying() {
  $('.playlist_entry').css('background',colors.normal.queue);
  if (pos < 0) return;

  var entryID='pl_entry_'+pos;
  $('#'+entryID).css('background',colors.normal.playing);
}

function setInfo(item) {
  var pos = $(item).data('pos');
  // might be safer to put meta info in DOM objects data()?
  var info = 'Queued by: ' + getDisplayName(playlist[pos].from) + '<br/>';
      if (playlist[pos].playing.length>0) {
	info += 'Playing: ';
	for (i=0;i<playlist[pos].playing.length;i++) {
          if (i>0) info += ', ';
	  info += getDisplayName(playlist[pos].playing[i]);
        }
	info += '<br/>';
      }
      //info += '<a href="'+playlist[pos].url+'">Link</a>';
  
  $('#infobar').html(info);
}

function clearInfo() {
  $('#infobar').html('&nbsp;');
}

function setNick(id,nick) {
  if (id == actor.actorID) return;

  users[id] = {nick:nick};

  var curFollow = $('#followingSelect').val();
  var options = '<option value="anyone" ';
  if (curFollow == 'anyone') options += 'selected';
  options += '>Anyone</option><option value="me" ';
  if (curFollow == 'me') options += 'selected';
  options += '>Just me</option>';
  for (n in users) {
    options += '<option value="'+n+'" ';
    if (curFollow == n) options += 'selected';
    options += '>'+users[n].nick+'</option>';
  }
  $('#followingSelect').html(options);

}

function getDisplayName(id) {
  if (id==actor.actorID) return 'me';
  if (users[id]) return users[id].nick;
  return id;
}

function isFollowing(id) {
  if (id == actor.actorID) return true;

  // pretty gross auto-caching
  if (!users[id]) setNick(id,id);

  var following = $('#followingSelect').val();
  if (following == 'me') return false;
  if (following == 'anyone') return true;
  return (following == id);

}

function updatePlayingStatus(msg,from) {
  if (msg.status!='playing') return;
  if (!('pos' in msg)) return;
  var mpos = parseInt(msg.pos);

  for (i=0;i<playlist.length;i++) {
    var p = playlist[i].playing;
 
    var kill=-1;
    for (j=0;j<p.length;j++) {
      if (p[j]==from) {
	kill=j;
	break;
      }
    }
    if (kill>=0) {
	// remove from this list
        playlist[i].playing = p.slice(0,j).concat(p.slice(j+1));
        if (playlist[i].playing.length==0) {
	  $('#pl_friend_'+i).html('&nbsp;');
        }
	break;
    }
  }
  playlist[mpos].playing.push(from);
  $('#pl_friend_'+mpos).html(' :) ');
}
