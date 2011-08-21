<?php
// Start streaming
$gptc = new GnipPowerTrack_Consume('YOUR_GNIP_USERNAME', 'YOUR_GNIP_PASSWORD');
$gptc->consume();




class GnipPowerTrack_Consume {
	// URL Gnip provided to access power track
	protected $gnip_powertrack_location = 'https://XXXXXXXXXXXXX-powertrack.gnip.com/data_collectors/1/track.json';
	
	// Set this to something identifiable in case Gnip needs to troubleshoot
	protected $user_agent = 'YOUR USERAGENT';
	
	// path to log data
	protected $log_location = '/var/log/firehose_power_track.txt';
	
	// reconnect to Gnip if we are idle for more than X minutes.  If set to 0, will be set to a 24 hours from now.
	protected $reconnect_if_idle_for = 15;
	
	
	
	public function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
		
		// automatically connect so we are ready to consume();
		while (!$this->connect()) {
			$this->write_log('error', 'Error Connecting: Could not obtain connection URL');
			sleep(5);
		}
		
		// number of seconds to delay connecting to Gnip, increases by one each connect attempt. Resets to 0 on success. Should be left at 0.
		$this->connect_delay = 0;
	}
	
	public function __destruct() {
		// Do any cleanup needed. Nothing for now.
	}
	
	// this gets us our coveted cookie and proper URL to stream from
	public function connect() {
		$this->write_log('info', 'Connecting to: ' . $this->gnip_powertrack_location);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gnip_powertrack_location);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);  
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
		
		$header = curl_exec($ch);
		curl_close($ch);
		
		$header_arr = array();
	        $header_lines = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
	        foreach($header_lines as $header_line) {
	            if( preg_match('/([^:]+): (.+)/m', $header_line, $match) ) {
	                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
	                if( isset($header_arr[$match[1]]) ) {
	                    $header_arr[$match[1]] = array($header_arr[$match[1]], $match[2]);
	                } else {
	                    $header_arr[$match[1]] = trim($match[2]);
	                }
	            }
	        }
		
		if (!isset($header_arr['Location'])) return false;
		
		$this->write_log('info', 'Reconnect URL set to: ' . $header_arr['Location']);
		
		$location_tmp = parse_url($header_arr['Location']);
		
		$this->gnip_powertrack_reconnect_scheme = $location_tmp['scheme'];
		$this->gnip_powertrack_reconnect_host = $location_tmp['host'];
		$this->gnip_powertrack_reconnect_port = $location_tmp['port'];
		$this->gnip_powertrack_reconnect_path = $location_tmp['path'];
		$this->gnip_powertrack_reconnect_cookie = implode('; ', $header_arr['Set-Cookie']);
		
		unset($location_tmp);
		return true;
	}

	public function consume() {
		$this->write_log('info', 'Preparing to Consume');
		
		if ($this->connect_delay > 0) {
			$this->write_log('info', 'Connect delay currently: ' . $this->connect_delay . ' seconds');
			sleep($this->connect_delay);
		}
		
		// increase the delay by one second
		$this->connect_delay++;
		if ($this->connect_delay > 60) $this->connect_delay = 60;
		
		$this->last_found_time = $this->last_log_time = time();
		$this->idle_reconnect_time = ($this->reconnect_if_idle_for==0) ? time()+(60*60*24) : time()+($this->reconnect_if_idle_for*60);
		$this->consume_count = 0;
		$this->disconnect = false;
		$debug_headers = '';
		
		$fp = fsockopen((($this->gnip_powertrack_reconnect_scheme=='https') ? 'ssl://' : '') . $this->gnip_powertrack_reconnect_host, $this->gnip_powertrack_reconnect_port, $errno, $errstr, 30);
		if (!$fp) {
			// there was an unkown connect error of some sort, report it, then sleep for 5 seconds and try again			
			$this->log(array('error'=>'Error Connecting: ' . $errstr . ' (' . $errno . ')'));
			sleep(5);
		} else {
			$this->write_log('info', 'Connected; Ready to Consume');
			// set the response to empty
			$response = '';
						
			$out = "GET " . $this->gnip_powertrack_reconnect_path . " HTTP/1.0\r\n";
			$out .= "Host: " . $this->gnip_powertrack_reconnect_host . "\r\n";
			$out .= "Cookie: " . $this->gnip_powertrack_reconnect_cookie . "\r\n";
			$out .= "Connection: Close\r\n\r\n";
			fwrite($fp, $out);
		    
			stream_set_blocking($fp, 1); 
			stream_set_timeout($fp, ($this->reconnect_if_idle_for==0) ? (60*60*24) : ($this->reconnect_if_idle_for*60)); 
			$stream_info = stream_get_meta_data($fp);
			
			// save the headers so we can use them for debugging if we are forcefully disconnected.
			while(!feof($fp) && ($debug = fgets($fp)) != "\r\n" ) {
				$debug_headers .= trim($debug) . '; ';
			}
		    
			stream_set_blocking($fp, 1); 
		    
			$this->write_log('info', 'Consuming');
			while ((!feof($fp)) AND (!$stream_info['timed_out'])) {
				$response .= fgets($fp, 16384);
				if (($newline = strpos($response, "\r\n")) === FALSE) {
					continue; // We need a newline
		        	}
				
				// only enqueue the responses that have data (e.g. ignore keep alive data)
				$response = trim($response);
				if (strlen($response) > 0) {
					// set the fact that we just got valid data
					$this->connect_delay = 0;

					$this->idle_reconnect_time = ($this->reconnect_if_idle_for==0) ? time()+(60*60*24) : time()+($this->reconnect_if_idle_for*60);
					$this->consume_count++;
					
					// enqueue our new item
					$enqueue_start = microtime(TRUE);
					$this->enqueue($response);
					$this->enqueue_time += (microtime(TRUE) - $enqueue_start);
				}
				
				// clean up the response variable for next time around.
				$response = '';
				
				// log our information (function takes care of if we actually need to log anything)
				$this->log();
				
				// this only gets triggered if during the log action, we determined that we were completely idle for X minutes. X = $this->reconnect_if_idle_for
				if ($this->disconnect == true) {
					$this->disconnect = false;
					break;
				}
			}
			fclose($fp);
		}
		
		// log the last headers if we get here, just so we can see them if needed
		$this->write_log('debug', $debug_headers);
		
		// If we are disconnected for any reason, reconnect now.
		// lets get a new connect location, just in case it was changed
		while (!$this->connect()) {
			$this->write_log('error', 'Error Connecting: Could not obtain connection URL');
			sleep(5);
		}
		$this->consume();
	}
	
	public function log($message='') {
		$now = time();

		// if there is something to log, lets log it immediately
		if (isset($message['error'])) {
			$this->write_log('error', $message['error']);
		}
		if (isset($message['notice'])) {
			$this->write_log('notice', $message['notice']);
		}
		
		// it has been equal to or more than a minute since we last logged data to the log, lets write some stats data
		if (($this->last_log_time+60) <= $now) {
			$this->write_log('stats', array('items_consumed_total'=>number_format($this->consume_count), 'items_consumed_per_sec'=>($this->consume_count == 0) ? '0' : number_format($this->consume_count/($now-$this->last_log_time), 2), 'items_consumed_per_min'=>($this->consume_count == 0) ? '0' : number_format($this->consume_count/(($now-$this->last_log_time)/60), 2), 'avg_enqueue_time'=>($this->consume_count > 0) ? round($this->enqueue_time / $this->consume_count * 1000, 2) : 0));
			
			$this->last_log_time = $now;
			$this->consume_count = 0;
			$this->enqueue_time = 0;
		}
		
		// if we've been idle for longer than we allow ourselves to be, disconnect and reconnect.
		if ($this->idle_reconnect_time <= $now) {
			// log the error, we've been idle for more than allowed
			$this->write_log('error', 'Max idle time reached (' . $this->reconnect_if_idle_for . ' minutes).  Disconnecting.');
			
			$this->disconnect = true;
		}
	}
	
	// overwrite this with a custom enqueue function
	public function enqueue($message) {
		// just for example. $message will contain a complete, json_encoded string
		echo "\nMessage: '" . $message . "'\n";
	}
	
	// overwrite this with a custom logging function, if needed.
	private function write_log($type, $log_item) {
		if (is_array($log_item)) {
			foreach ($log_item AS $name=>$value) {
				$log_item_temp_arr[] = $name . ': ' . $value;
			}
			$log_line = date('Y-m-d H:i:s') . ' [' . ucfirst($type) . '] ' . implode('; ', $log_item_temp_arr);
		}
		else {
			$log_line = date('Y-m-d H:i:s') . ' [' . ucfirst($type) . '] ' . $log_item;
		}
		if ($type == 'stats') echo "\n";
		echo $log_line . "\n";
		$fh = fopen($this->log_location, 'a') or die("Can't open log file\n");
		fwrite($fh, $log_line . "\n");
		fclose($fh);
	}
}
?>