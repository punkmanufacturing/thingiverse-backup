<?php

// http://js-kit.com/comments-data.js?ref=http%3A%2F%2Fwww.thingiverse.com%2Fthing%3A13298&randevuId=&&p[0]=%2Fthing%3A13298&gen[0]=0&srt[0]=date&sp[0]=1&skin[0]=echo&permalink[0]=http%3A%2F%2Fwww.thingiverse.com%2Fthing%3A13298&jx[0]=0

class Thingiverse {
	
	public $thing_path = './things/';
	public $log_path = './logs/thing.log';
	
	public function log($msg) {
		file_put_contents($this->log_path, $msg, FILE_APPEND);
	}
	
	/* Helpers */
	
	public function get_range($start, $end, $get_files = true, $get_images = false) {
		
		$this->get_things(range($start,$end), $get_files, $get_images);
	
	}
	
	public function get_user_things($user, $last_id = false) {
	
		$this->get_list($last_id,  $user . '/things');
	
	}

	public function get_things($thing_ids, $get_files = true, $get_images = false) {
		$ttotal = 0;
		foreach($thing_ids as $id) {
			$tstart = time();
			
			if($this->get_thing($id, $get_files, $get_images)) {
				echo 'Got thing '. $id;
			} else {
				echo 'Failed thing '. $id;
			}
			$tend = time();
			$dt = $tend - $tstart;
			$ttotal += $dt;
			echo ' in ' . $dt  . " sec \n";
			ob_flush();
			flush();
		}
		echo 'Total time: '. $ttotal . " sec \n";
	}
	
	/* Main methods */
	
	public function get_list($last_id, $type = 'newest' ) {

		
		$r = new HttpReq('http://www.thingiverse.com/' . $type);
		$r->list_type = $type;
		$r->data = array();
		$r->last_id = $last_id;
		$r->singleFail = true;
		$r->attach('success', array($this, 'thing_list'));
		$r->attach('fail', array($this, 'list_fail'));
		$r->exec();
	
		return $r->data;
	}
	
	public function get_thing($thing_id, $get_files = true, $get_images = false) {
		$thing_id = (string) $thing_id;
		
		$r = new HttpReq('http://www.thingiverse.com/thing:'.$thing_id);
		$r->singleFail = true;
		$r->attach('success', array($this, 'thing_meta'));
		$r->attach('fail', array($this, 'thing_fail'));
		$r->thing_id = $thing_id;
		$r->exec();
		
		/*
		$r = new stdClass;
		$r->body = file_get_contents('thing12056.htm');
		$r->thing_id = '12056';
		$this->thing_meta($r);
		*/
		
		if(!isset($r->data) || !is_array($r->data) || empty($r->data)) {
			return false;
		}
		/*
		echo '<pre>';
		print_r($r->data);
		echo '</pre>';
		*/

		if($get_files) {
			$thing_path = $this->thing_path . '/' . $thing_id[0] . '/' . $thing_id;
			if(!is_dir($thing_path)) {
				mkdir($thing_path);
			}
			file_put_contents($thing_path . '/meta.json', json_encode($r->data));
		}
		
		if($get_files && isset($r->data['files']) && !empty($r->data['files'])) {
			foreach($r->data['files'] as $file_id => $file_inf) {
				$this->get_file($file_id, $thing_path);
			}
		
		}
		if($get_images) {
		
		
		}
		
		return $r->data;
	}
	
	public function get_file($file_id, $path) {
		$r = new HttpReq('http://www.thingiverse.com/download:'.$file_id);
		$r->options[CURLOPT_FOLLOWLOCATION] = false;
		$r->singeFail = true;
		$r->attach('fail', array($this, 'file_fail'));
		$r->file_id = $file_id;
		$r->exec();
		if(isset($r->headers[0]['location'][0])) {
			$redir = $r->headers[0]['location'][0];
			$filename = pathinfo(substr($redir,7), PATHINFO_BASENAME);
		
		
			$fh = fopen($path . '/' . $file_id . '_' . $filename, 'w');
			$rf = new HttpReq($redir);
			$rf->readResponse = false;
			$rf->parseHeaders = false;
			$rf->singeFail = true;
			$rf->attach('fail', array($this, 'file_fail'));
			$rf->options[CURLOPT_HEADER] = false;
			$rf->options[CURLOPT_BINARYTRANSFER] = true;
			$rf->options[CURLOPT_FILE] = $fh;
			$rf->exec();
			fclose($fh);
		}
	}

	public function get_image($image_id, $size = 'large') {
		$r = new HttpReq('http://www.thingiverse.com/download:'.$file_id);
		$r->exec();
		
	}
	
	/* Parsing methods */
	
	public function thing_list($r) {
		$stop = false;
		
		if(preg_match_all('@<a href="http://www.thingiverse.com/thing:([^"]+)">([^<]+)</a>@', $r->body, $things)) {
			
			foreach($things[1] as $k => $id) {
				if($r->last_id && $r->last_id == $id) {
					$r->keep = false;
					$stop = true;
					break;
				} else {
					$r->data[$id] = $things[2][$k];
				}
			}
		}
		
		if(!$stop && preg_match('@/page:([0-9]+)">next@', $r->body, $page)) {
			$r->keep = true;
			$r->url = 'http://www.thingiverse.com/' . $r->list_type . '/page:' . $page[1];
		} else {
			$r->keep = false;
		}
	
	}
	
	public function thing_meta($r) {
		$thing['id'] = $r->thing_id;
		
		if(strpos($r->body, 'This thing is a Work in Progress') !== false) {
			$thing['status'] = 'wip';
		} else {
			$thing['status'] = 'stable';
		}
	
		$name_reg = '@<h1 id="pageTitle">\s*(.+?) by@'; 
		preg_match($name_reg, $r->body, $thing_name);
		$thing['name'] = $thing_name[1];
		
		$user_reg = '@<h3>Creator</h3>\s*<p>\s*<a href="http://www.thingiverse.com/([^"]+)">@';
		preg_match($user_reg, $r->body, $user);
		$thing['user'] = $user[1];
		
		$date_reg = '@on (.+)</p>\s*(?:<div|<h3>|<ul)@';
		if(preg_match($date_reg, $r->body, $cdate)) {
			$thing['date'] = $cdate[1];
		}
		
		if(preg_match('@is licensed under the <a rel="license" href="([^"]+)">([^<]+)</a> license@', $r->body, $license_parts)) {
			$thing['license']['name'] = $license_parts[2];
			$thing['license']['link'] = $license_parts[1];
		}

		if(strpos($r->body, "<div id=\"thing_tags\">\n\t\tNo tags.\n</div>") === false) {
			$tag_reg = '@<li class="tag_display">\s*<a href="/tag:([^"]+)">@';
			preg_match_all($tag_reg, $r->body, $tags);
			$thing['tags'] = (is_array($tags[1])) ? $tags[1] : 'None';
		} else {
			$thing['tags'] = 'None';
		}

		$files_reg = '@<h3 class="file_info">\s*([^<]+)</h3>\s*([^<]+)\s*(?:<div class="BaseError">[^<]+</div>\s*)?</td>@';
		preg_match_all($files_reg, $r->body, $files);
		$downloads_reg = '@<a href="/download:([0-9]+)"><img src="/img/download.jpg">@';
		preg_match_all($downloads_reg, $r->body, $downloads);
		if(is_array($files[1])) {
			$thing['files'] = array();
			foreach($files[1] as $fk => $filename) {
				$fid = $downloads[1][$fk];
				$info = $files[2][$fk];
				preg_match('@([0-9]+ (?:b|kb|mb|gb))\s*/\s*([0-9]+)@', $info, $infos);
				$thing['files'][$fid] = array('name'=> trim($filename), 'size' => $infos[1], 'dl' => $infos[2]);
			}
		
		}
		
		$pics_reg = '#<a href="/image:([0-9]+)">#';
		preg_match_all($pics_reg, $r->body, $images);
		$thing['images'] = (is_array($images[1])) ? array_unique($images[1]) : 'None';

		if(strpos($r->body, '<h2>History</h2>') !== false) {
			$hist_reg = '@<h2>History</h2>\s*<p>(.+?)</p>@s';
			preg_match($hist_reg, $r->body, $history);
			$thing['history'] = trim($history[1]);
		}
		
		$desc_reg = '@<h2>Description</h2>\s*<p>(.+?)</p>@s';
		if(preg_match($desc_reg, $r->body, $desc)) {
			$thing['description'] = trim($desc[1]);
		}

		if(strpos($r->body, '<h2>Instructions</h2>') !== false) {
			$instr_reg = '@<h2>Instructions</h2>\s*<p>(.+?)</p>@s';
			preg_match($instr_reg, $r->body, $instr);
			$thing['instructions'] = trim($instr[1]);
		}
		
		/*
		if(strpos($r->body, '<span class="h2"> Part List </span>') ! == false) {
			$parts_reg = '@<a target="_blank" href="http://www.thingiverse.com/part:([^"]+)">([^<]+)</a>\s*</td>\s*<td class="rightcell">([0-9]*) </td>@';
			preg_match_all($parts_reg, $r->body, $parts);
			if(is_array($parts[1])) {
				$things['parts'] = array();
				foreach($parts[1] as $k => $id) {
					$things['parts'][$id] = array( 'name' => $parts[2][$k], 'qty' => $parts[3][$k]);
				}
			}
		}
		*/
		$parts_reg = '@<iframe src="(http://www.thingiverse.com/thing:[0-9]+/partlist[^"]+)"@';
		if(preg_match($parts_reg, $r->body, $partlist)) {
			$thing['partlist'] = $partlist[1];
		}
		
		$r->data = $thing;
	}
	
	/* Error handlers */

	public function thing_fail($r, $msg) {
		$this->log('thing '. $r->thing_id .' failed ' . $msg . "\n");
	}

	public function file_fail($r,$msg) {
		$this->log('file ' . $r->file_id . ' failed ' . $msg . "\n");
	}
	
	public function list_fail($r, $msg) {
		$this->log('list '. $r->url .' failed ' . $msg . "\n");
	}

}

?>