<?php

// after including it, start the calls with:
// $gptr = new GnipPowerTrack_ManageRules('YOUR_GNIP_USERNAME', 'YOUR_GNIP_PASSWORD');
//
// USAGE:
// (bool) = $gptr->add(array(array('value'=>'item', 'Search'))))
// (bool) = $gptr->delete(array(array('value'=>'item'))))

class GnipPowerTrack_ManageRules {
	// URL Gnip provided to manage powertrack rules (json)
	protected $gnip_powertrack_rules_location = 'https://XXXXXXXXXXX-powertrack.gnip.com/data_collectors/1/rules.json';
	
	// Set this to something identifiable in case Gnip needs to troubleshoot
	protected $user_agent = 'YOUR USERAGENT';

	
	
	public function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}
	
	public function __destruct() {
		// Do any cleanup needed.
	}
	
	// get current rules
	// returns array of current rules
	public function get() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gnip_powertrack_rules_location);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);  
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);

		if ((curl_errno($ch) == 6) OR (curl_errno($ch) == 7)) {
			return false;
		}
		else {
			$response = curl_exec($ch);
			if (trim($response) == '') {
				return false;
			}
			else {
				return json_decode($response, true);
			}
		}
		curl_close($ch);
	}
	
	// add a new rule
	// $rule_arr should be an array of arrays.
	// returns true/false
	public function add($rule_arr) {
		if (!is_array($rule_arr)) {
			return false;
		}
		else {
			// if any of the rules don't have a value for some reason, return false.
			foreach ($rule_arr AS $rule_item) {
				if (!isset($rule_item['value'])) {
					return false;
				}
			}
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gnip_powertrack_rules_location);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);  
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('rules'=>$rule_arr)));

		if ((curl_errno($ch) == 6) OR (curl_errno($ch) == 7)) {
			return false;
		}
		else {
			$response = curl_exec($ch);
			if (trim($response) == '') {
				return false;
			}
			else {
				$response_decoded = json_decode($response, true);
				if ((isset($response_decoded['response']['message'])) AND ($response_decoded['response']['message'] == 'created')) {
					return true;
				}
				else {
					return false;
				}
			}
		}
		curl_close($ch);
	}
	
	// delete an existing rule
	// $rule_arr should be an array of arrays, each with the values of each rule you want deleted
	// returns true/false
	public function delete($rule_arr) {
		if (!is_array($rule_arr)) {
			return false;
		}
		else {
			// if any of the rules don't have a value for some reason, return false.
			foreach ($rule_arr AS $rule_item) {
				if (!isset($rule_item['value'])) {
					return false;
				}
			}
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gnip_powertrack_rules_location);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);  
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('rules'=>$rule_arr)));

		if ((curl_errno($ch) == 6) OR (curl_errno($ch) == 7)) {
			return false;
		}
		else {
			$response = curl_exec($ch);
			if (trim($response) == '') {
				return false;
			}
			else {
				$response_decoded = json_decode($response, true);
				if ((isset($response_decoded['response']['message'])) AND ($response_decoded['response']['message'] == 'accepted')) {
					return true;
				}
				else {
					return false;
				}
			}
		}
		curl_close($ch);
	}
	
	// This deletes ALL existing rules.
	// returns true/false
	public function delete_all() {
		// we use $existing_rules_arr in multiple places below
		$existing_rules_arr = $this->get();
		
		// if we are already at 0 rules, return true
		if (count($existing_rules_arr) == 0) return true;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gnip_powertrack_rules_location);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);  
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_NOSIGNAL, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($existing_rules_arr));

		if ((curl_errno($ch) == 6) OR (curl_errno($ch) == 7)) {
			return false;
		}
		else {
			$response = curl_exec($ch);
			if (trim($response) == '') {
				return false;
			}
			else {
				$response_decoded = json_decode($response, true);
				if ((isset($response_decoded['response']['message'])) AND ($response_decoded['response']['message'] == 'accepted')) {
					return true;
				}
				else {
					return false;
				}
			}
		}
		curl_close($ch);
	}
}
?>