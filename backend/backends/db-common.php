<?php
 	class jzMediaNode extends jzRawMediaNode {
		function jzMediaNode($arg = array(),$mode="path") {
			$this->_constructor($arg,$mode);
		}
	}
	class jzMediaTrack extends jzRawMediaTrack {
		function jzMediaTrack($arg = array(),$mode="path") {
			$this->_constructor($arg,$mode);
		}
	}
	
	
if (!class_exists("jzUser")) { 
 	class jzUser extends jzRawUser {
  		function jzUser($login = true, $uid = false) {
    		$this->_constructor($login,$uid);
  		}
	}
 }
	
	
// TODO: Make this an abstract interface and recode for each DB.
// Same with the function below it.	
class sqlTable {
	var $data;
	var $rows;

	function sqlTable() {
		$this->data = array ();
		$this->rows = 0;
	}

	function add($row) {
		foreach ($row as $key => $val) {
			$this->data[$this->rows][$key] = $val;
		}
		$this->rows++;
	}
}
	
function jz_db_cache($type, $sql, $val = false) {
	global $enable_query_cache;
	static $jz_query_cache = array();

	if ($enable_query_cache != "true") {
		return false;
	}

	if ($val === false) {
		if (isset($jz_query_cache[$type][$sql])) {
			return $jz_query_cache[$type][$sql];
		} else {
			return false;
		}
	} else {
		if ( (false !== stripos($sql,"select")) && (false === stripos($sql,jz_db_rand_function()))) {
			$jz_query_cache[$type][$sql] = $val; 
		}
	}
}

function resultsToArray(& $results, $type = false) {
	global $backend;

	if ($type === false) {
		$arr = array ();
		$hash = array ();
		for ($i = 0; $i < $results->rows; $i++) {
		  if (isset($results->data[$i]['leaf']) && $results->data[$i]['leaf'] == "false") {
				$me = & new jzMediaNode(jz_db_unescape($results->data[$i]['path']));
				$me->leafcount = $results->data[$i]['leafcount'];
				$me->nodecount = $results->data[$i]['nodecount'];
				$me->artpath = jz_db_unescape($results->data[$i]['main_art']);
				$me->myid = $results->data[$i]['my_id'];
				if ($me->artpath == "") {
					$me->artpath = false;
				}
				$me->playcount = $results->data[$i]['playcount'];
				$me->dlcount = $results->data[$i]['dlcount'];
				$me->longdesc = jz_db_unescape($results->data[$i]['longdesc']);
				$me->ptype = jz_db_unescape($results->data[$i]['ptype']);
				// Gross hack to follow;
				// Fixes case where an album is in 2 genres from 1 artist:
				if ($backend == "id3-database") {
					if (!isset ($hash[pathize(strtolower($me->getName()))])) {
						$arr[] = $me;
						$hash[pathize(strtolower($me->getName()))] = true;
					}
				} else {
					$arr[] = $me;
				}
			} else {
			  $r = $results->data[$i];
			  $track = & new jzMediaTrack(jz_db_unescape($r['path']));
			  
			  // we never query for just bitrate, so if it's returned, we have all meta.
			  if (isset($r['bitrate']) && $r['bitrate'] != '-') {
			    $meta = array();
			    $meta['title'] = jz_db_unescape($r['trackname']);
			    $meta['bitrate'] = jz_db_unescape($r['bitrate']);
			    $meta['frequency'] = jz_db_unescape($r['frequency']);
			    $meta['filename'] = jz_db_unescape($r['name']);
			    $meta['size'] = jz_db_unescape($r['filesize']);
			    $meta['year'] = jz_db_unescape($r['year']);
			    if (isset($r['descr'])) {
			      $meta['comment'] = jz_db_unescape($r['descr']);
			    }
			    $meta['length'] = jz_db_unescape($r['length']);
			    $meta['number'] = jz_db_unescape($r['number']);
			    $meta['genre'] = jz_db_unescape($r['genre']);
			    $meta['artist'] = jz_db_unescape($r['artist']);
			    $meta['album'] = jz_db_unescape($r['album']);
			    $meta['lyrics'] = jz_db_unescape($r['lyrics']);
			    $meta['type'] = jz_db_unescape($r['extension']);
			    $track->meta = $meta;
			  }
			  $arr[] = $track;
			}
		}
		return $arr;
	} else
		if ($type == 'tracks') {
			$arr = array ();
			for ($i = 0; $i < $results->rows; $i++) {
				$me = & new jzMediaTrack(jz_db_unescape($results->data[$i]['path']));
				// FILL META HERE. ***//
				if (isset ($results->data[$i]['filepath']) && $results->data[$i]['filepath'] != "")
					$me->playpath = $results->data[$i]['filepath'];
				if ($results->data[$i]['trackname'] != "" && $results->data[$i]['trackname'] != "-")
					$me->title = jz_db_unescape($results->data[$i]['trackname']);
				$arr[] = $me;
			}
			return $arr;
		}
}


?>
