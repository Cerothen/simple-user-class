<?php 
// require_once('functions.php');

// Debugging output functions
function debug_out($variable, $die = false) {
	$trace = debug_backtrace()[0];
	echo '<pre style="background-color: #f2f2f2; border: 2px solid black; border-radius: 5px; padding: 5px; margin: 5px;">'.$trace['file'].':'.$trace['line']."\n\n".print_r($variable, true).'</pre>';
	if ($die) { http_response_code(503); die(); }
}

class db_conn {
	// External
	
	
	// Internal
	private $user_id = null;
	private $group_id = null;
	private $db = null;
	private $db_type = null;
	private $db_options = null;
	
	// Constants
	private $db_version = '0.52';
	
	// Setup
	function __construct($db_type, $options) {
		switch ($db_type) {
			case 0:
			case 'sqlite':
				// Set Type
				$this->db_type = 'sqlite';
				
				// Check if options exists
				if ($options) {
					// Check if options is as an array
					if (is_array($options)) {
						if (isset($options['path'])) {
							$options = $options['path'];
						} else if (isset($options['host'])) {
							$options = $options['host'];
						}
					}
					
					// If string process
					if (is_string($options)) {
						// make sure .db is on the end
						$options = preg_replace('/\.db\.db$/','.db',$options.'.db');
						
						// Cache Path
						$this->db_options = $options;
						
						// Connect to file
						$this->db = new PDO('sqlite:'.$options);
						# $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						
						// Confirm file exists
						if (file_exists($options)) {
							if (!filesize($options)) {
								// Create DB if empty file
								$this->create_db();
							} else {
								// Check if upgrade is needed on database
								$this->upgrade_db();
							}
							
							
							
							// Login User Automatically if Enabled
							
							
							
							// Complete Construct
							return true;
						} else {
							throw new Exception('Can\'t access database.');
						}

					} else {
						throw new Exception('Path specified is not valid.');
					}
				} else {
					throw new Exception('Construct second parameter was incorrectly formed.');
				}
				break;
			case 1:
			case 'mysql':
				throw new Exception('Not Implemented: This is a planned Feature');
				break;
			case 2:
			case 'mariadb':
				throw new Exception('Not Implemented: This is a planned Feature');
				break;
			case 3:
			case 'postgresql': 
				throw new Exception('Not Implemented: This is a planned Feature');
				break;
			default:
				throw new Exception('Database Type Doesn\'t Exist.');
		}
	}
	
	// Takedown
	function __destruct() {
		
	}
	
	// External Functions
	public function getUsers() {
		// Return User List
		
		return array();
	}
	
	public function createUser($options) {
		// Primary Qualify
		$data = array();
		foreach(array('username','pass_hash','first','last','groups_id') as $value) {
			if (isset($options[$value])) {
				$data[$value] = $options[$value];
			}
		}
		return $this->query_create('users', $data);
	}
	
	public function globalOpts($values = null) {
		// If values not set then return values, if set with key=>val pair then set values
		if (isset($values) && is_array($values)) {
			$success = true;
			foreach($values as $key => $value) {
				if (!$this->query_create_or_update('global', array('name' => $key, 'value' => $value))) {
					$success = false;
				}
			}
			return $success;
		} else {
			$results = $this->query_select('global');
			if (is_array($results)) {
				$output = array();
				foreach($results as $k => $v) {
					$output[$v['name']] = $v['value'];
				}
				return $output;
			} else {
				return false;
			}
		}
		return false;
	}
	
	public function options($link = null, $values = null) {
		// If link not set then currently active user id, negative indicates group id
		// If values not set then return values, if set with key=>val pair then set values
		if (isset($values) && is_array($values)) {
			if (isset($link)) {
				$is_valid_link = false;
				foreach($this->query_select('users') as $key=>$value) {
					if ($link == $value['id']) {
						$is_valid_link = true;
						break;
					}
				}
				if (!$is_valid_link) {
					foreach($this->query_select('groups') as $key=>$value) {
						if ($link == ($value['id'] * -1)) {
							$is_valid_link = true;
							break;
						}
					}
				}

				if ($is_valid_link) {
					foreach($values as $k => $v) {
						$this->query_create_or_update('options', array(
							'link_id' => $link,
							'name' => $k,
							'value' => $v,
						));
					}
					return true;
				} else {
					return false;
				}
			} else {
				
			}
		} else {
			if (!isset($link)) { $link = $this->user_id; }
			if (!isset($link)) { $link = '*'; } // Remove?
			if (isset($link)) {
				// Format Input
				if ($link == '*') {
					$link = array();
					foreach($this->query_select('users') as $key=>$value) {
						$link[] = $value['id'];
					}
					foreach($this->query_select('groups') as $key=>$value) {
						$link[] = $value['id'] * -1;
					}
				} else if (!is_array($link)) {
					$link = array($link);
				}
				
				// Get Output
				$output = array();
				foreach($link as $value) {
					foreach($this->query_select('options', array('link_id' => $value)) as $k => $v) {
						$output[$value][$v['name']] = $v['value'];
					}
				}
				
				return $output;
			} else {
				return false;
			}
		}
		return array();
	}
	
	// Internal Functions
	private function create_db() { // Eventually inject table data as array (maybe)
		switch ($this->db_type) {
			case 'sqlite':
				if (!isset($this->db)) {
					$this->db = new PDO('sqlite:'.$this->db_options);
					# $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				}
				
				// Lazy For Now
				$global = $this->db->query('CREATE TABLE `global` (
					`name`	TEXT NOT NULL UNIQUE,
					`value`	TEXT,
					PRIMARY KEY(`name`)
				);');
				$options = $this->db->query('CREATE TABLE `options` (
					`link_id`	INTEGER NOT NULL,
					`name`	TEXT NOT NULL,
					`value`	TEXT NOT NULL,
					PRIMARY KEY(`link_id`,`name`)
				);');
				$users = $this->db->query('CREATE TABLE `users` (
					`id`	INTEGER PRIMARY KEY AUTOINCREMENT,
					`groups_id`	TEXT NOT NULL,
					`username`	TEXT NOT NULL UNIQUE,
					`pass_hash`	TEXT NOT NULL,
					`active`	INTEGER NOT NULL DEFAULT 1,
					`first`	TEXT,
					`last`	TEXT,
					`otp_key`	TEXT
				);');
				$groups = $this->db->query('CREATE TABLE `groups` (
					`id`	INTEGER PRIMARY KEY AUTOINCREMENT,
					`inherit_groups_id`	INTEGER,
					`name`	TEXT NOT NULL UNIQUE
				);');
				$db_version = $this->globalOpts(array('db_version' => $this->db_version));
				return $global && $options && $users && $groups && $db_version;
				break;
			
		}
	}
	
	private function upgrade_db() {
		$globals = $this->globalOpts();
		if (!isset($globals['db_version']) || $globals['db_version'] < $this->db_version) {
			// Cache Contents
			$cache = array();
			foreach($this->query_select('sqlite_master', array('type' => 'table')) as $table) { // ========== SQLITE specific
				foreach($this->query_select($table['name']) as $key => $row) {
					foreach($row as $k => $v) {
						if (is_string($k)) {
							$cache[$table['name']][$key][$k] = $v;
						}
					}
				}
			}
			
			// Remove DB
			$remove_success = $this->destroy_db();
			
			// Create DB
			$create_success = $this->create_db();
			
			// Restore database contents
			if ($remove_success && $create_success) {
				foreach($cache as $table => $tableData) {
					if ($tableData) {
						foreach($tableData as $key => $value) {
							$this->query_create($table, $value);
						}
					}
				}
			}
		}
		return true;
	}
	
	private function destroy_db() {
		switch ($this->db_type) {
			case 'sqlite':
				$this->db = null;
				if (file_exists($this->db_options)) {
					rename($this->db_options, preg_replace('/\.db$/','.bak.db',$this->db_options));
				}
				return true;
				break;
			
		}
	}
	
	private function query_create($table, $data) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$data = $this->condition_data($data);
				
				// Perform Query
				if ($this->db->query('INSERT OR IGNORE INTO '.$table.'(`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$data).');')->rowCount()) {
					// Record Inserted
					return true;
				} else {
					// Record Not Inserted
					return false;
				}
				break;
			
		}
	}
	
	private function query_update($table, $data, $where) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$data = $this->condition_data($data);
				$where = $this->condition_data($where);
				
				// Perform Query
				if($this->db->query('UPDATE '.$table.' SET '.implode(',',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($data),$data)).($where?' WHERE '.implode(' AND ',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($where),$where)):'').';')->rowCount()) {
					// Record Updated
					return true;
				} else {
					// Err?
					return false;
				}
				break;
			
		}
	}
	
	private function query_create_or_update($table, $data) {
		switch ($this->db_type) {
			case 'sqlite':
				if ($this->query_create($table, $data)) {
					return true;
				} else {
					// Find the Primary Keys
					preg_match('/PRIMARY KEY\(([^\)]+)\)/',$this->db->query('SELECT sql FROM sqlite_master WHERE tbl_name=\''.$table.'\';')->fetch()[0],$match);
					$primaryKeys = explode(',',$match[1]);
					
					$where = array();
					foreach($data as $k => $v) {
						if (in_array('`'.$k.'`',$primaryKeys)) {
							$where[$k] = str_replace('\'','',$v);
							unset($data[$k]);
						}
					}
					
					return $this->query_update($table,$data,$where);
				}
				break;
			
		}
	}
	
	private function query_delete($table, $where) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$where = $this->condition_data($where);
				return $this->db->query('DELETE FROM '.$table.($where?' WHERE '.implode(' AND ',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($where),$where)):'').';')->rowCount();
				break;
			
		}
	}
	
	private function query_select($table, $where = array()) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$where = $this->condition_data($where);
				$results = array();
				foreach($this->db->query('SELECT * FROM '.$table.($where?' WHERE '.implode(' AND ',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($where),$where)):'').';') as $k => $v) {
					$results[$k] = $v;
				}
				return $results;
				break;
			
		}
	}
	
	private function condition_data($data) {
		foreach($data as $key => $value) {
			if (strtolower($value) !== 'null') { $data[$key] = "'".addslashes($value)."'"; }
		}
		return $data;
	}
}
$test = new db_conn(0,'./test.db');

debug_out($test->options(1, array(
	'set1' => '4',
	'set2' => '3',
	'set3' => '2',
	'set4' => '1',
)));

debug_out($test->options('*'));
