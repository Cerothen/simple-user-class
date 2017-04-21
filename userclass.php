<?php 
// Debugging output functions
function debug_out($variable, $die = false) {
	$trace = debug_backtrace()[0];
	echo '<pre style="background-color: #f2f2f2; border: 2px solid black; border-radius: 5px; padding: 5px; margin: 5px;">'.$trace['file'].':'.$trace['line']."\n\n".print_r($variable, true).'</pre>';
	if ($die) { http_response_code(503); die(); }
}

// Generate Random string
function randString($length = 10, $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	$tmp = '';
	for ($i = 0; $i < $length; $i++) {
		$tmp .= substr(str_shuffle($chars), 0, 1);
	}
    return $tmp;
}



class db_conn {
	// External
	public $user = null;
	
	// Internal
	private $user_id = null;
	private $db = null;
	private $db_type = null;
	private $db_options = null;
	private $persist_name = 'simpleuserclass';
	
	// Setup
	function __construct($db_type, $options) {
		// Start Session if not already started
		@session_start();
		
		// Setup Database Connection
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
						
						// Confirm file exists
						if (file_exists($options)) {
							if (filesize($options)) {
								// Check version & Upgrade if needed
								$this->upgrade_db();
							} else {
								// Create DB if empty file
								$this->create_db();
							}
							// Complete DB Setup
							break;
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
				// Set Type
				$this->db_type = 'mysql';
				
				// Perform flight check
				$precheck = true;
				if (is_array($options)) {
					// Required
					if (!isset($options['host'])) { $precheck = false; }
					if (!isset($options['user'])) { $precheck = false; }
					if (!isset($options['pass'])) { $precheck = false; }
					if (!isset($options['database'])) { $precheck = false; }
				}
				
				if ($precheck) {
					// Cache Options
					$this->db_options = $options;
					
					// Connect to Database
					$this->db = mysqli_connect($options['host'], $options['user'], $options['pass']);
					
					if ($this->db) {
						// Lookup database
						if ($result = @$this->db->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$options['database'].'\'')) {
							if ($result->num_rows) {
								// Check version & Upgrade if needed
								$this->db->query('USE '.$options['database'].';');
								$this->upgrade_db();
							} else {
								// Create DB
								$this->create_db(true);
							}
							
							// Complete DB Setup
							break;
						} else {
							throw new Exception('Lookup failed check permissions');
						}
					} else {
						throw new Exception('Database connection failed, check your parameters');
					}
				} else {
					throw new Exception('Construct second parameter was incorrectly formed.');
				}
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
		
		// Setup Session
		$this->session_init();
	}
	
	// Takedown
	function __destruct() {
		
	}
	
	// External Functions
	public function getUser($user_id, $include_options = false, $inherit = true) {
		return current($this->get_user_or_group('users', $user_id, $include_options, $inherit));
	}
	
	public function getUsers($include_options = false, $inherit = true) {
		return $this->get_user_or_group('users', false, $include_options, $inherit);
	}
	
	public function getGroup($group_id, $include_options = false, $inherit = true) {
		return current($this->get_user_or_group('groups', $group_id, $include_options, $inherit));
	}
	
	public function getGroups($include_options = false, $inherit = true) {
		return $this->get_user_or_group('groups', false, $include_options, $inherit);
	}
	
	public function createUser($options) {
		// Username qualify
		if (isset($options['username']) && $options['username'] && is_numeric($options['username'])) {
			die('Username can\'t be a number!');
		}
		
		// Create Secure Password Hash
		if (isset($options['password']) && $options['password']) {
			$options['pass_hash'] = password_hash($options['password'], PASSWORD_BCRYPT);
			unset($options['password']);
		}
		
		// Primary Qualify
		$data = array();
		foreach(array_keys($this->db_structure()['tables']['users']) as $value) {
			if (isset($options[$value])) {
				$data[$value] = $options[$value];
				unset($options[$value]);
			}
		}
		return $this->query_create('users', $data);
	}
	
	public function createGroup($options) {
		// Primary Qualify
		$data = array();
		foreach(array('inherit_groups_id','name') as $value) {
			if (isset($options[$value])) {
				$data[$value] = $options[$value];
			}
		}
		return $this->query_create('groups', $data);
	}
	
	public function loginUser($username, $password, $persist = false, $twoFactorCode= false) {
		if (is_numeric($username)) { die('Username can\'t be a number!'); } // Prevent using ID as username
		$user = current($this->get_user_or_group('users', $username));
		
		// Check if user Exists
		if ($user) {
			// Verify Password
			if (password_verify($password, $user['pass_hash'])) {
				// Verify Multi Factor Auth Code
				if (1 || $twoFactorCode) {
					// Set User ID
					$this->user_id = $user['id'];
					// Set User Publicly
					$this->user = $user;
					// Cache login in session
					$_SESSION[$this->persist_name]['user_id'] = $user['id'];
					// Set cookie if required
					if ($persist) {
						// Prepare cookie parts
						$token = hash('sha256',$user['id'].time().$user['username'].rand(1, 1000));
						$expiry = time() + (86400 * 7);
						// Set Cookie
						setcookie($this->persist_name, json_encode(array(
							'user' => $user['id'],
							'token' => $token,
						)), $expiry, "/", $_SERVER['HTTP_HOST']);
						// Set db session
						$user['sessions'][$token] = $expiry;
						$this->updateUser($user['id'], array('sessions'=>$user['sessions']));
					}
					// Return Successful Auth
					return $user;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public function logoutUser($allSessions = false) {
		if (!is_null($this->user)) {
			// Destroy Session
			unset($_SESSION[$this->persist_name]);
			// Destroy Cookie
			if (isset($_COOKIE[$this->persist_name])) {
				$token = json_decode($_COOKIE[$this->persist_name],true)['token'];
				unset($_COOKIE[$this->persist_name]);
				setcookie($this->persist_name, null, -1, '/', $_SERVER['HTTP_HOST']);
			}

			// Amend Sessions
			if ($allSessions) {
				$sessions = array();
			} else if (isset($token)) {
				$sessions = current($this->get_user_or_group('users', $this->user_id))['sessions'];
				unset($sessions[$token]);
			}
			
			// Commit new Sessions
			if (isset($sessions)) {
				$this->updateUser($this->user_id, array('sessions'=>$sessions));
			}
			
			// Remove class login indicators
			$this->user = null;
			$this->user_id = null;
			// Complete
			return true;
		} else {
			// No User Logged In
			return false;
		}
	}
	
	public function updateUser($id, $values) {
		return $this->query_update('users', $values, array(array(
			'id' => $id,
			'username' => $id,
		)));
	}
	
	public function deleteUser($id) {
		$user = current($this->get_user_or_group('users', $id));
		
		if ($user) {
			// Delete Related Options
			$this->query_delete('options', array('link_id' => $user['id']));
			// Delete User
			$this->query_delete('users', array('id' => $user['id']));
			return true;
		} else {
			return false;
		}
	}
	
	public function deleteGroup($id) {
		$group = current($this->get_user_or_group('groups', $id));
		
		if ($group) {
			// Delete Related Options
			$this->query_delete('options', array('link_id' => $group['id']));
			// Remove User References
			$this->query_update('users', array('groups_id' => 'null'), array('groups_id' => $group['id']));
			$this->query_update('users', array('groups_id' => 'null'), array('groups_id' => abs($group['id'])));
			// Remove Group References
			$this->query_update('groups', array('inherit_groups_id' => 'null'), array('inherit_groups_id' => $group['id']));
			$this->query_update('groups', array('inherit_groups_id' => 'null'), array('inherit_groups_id' => abs($group['id'])));
			// Delete User
			$this->query_delete('groups', array('id' => abs($group['id'])));
			return true;
		} else {
			return false;
		}
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
				$user_links = count($this->query_select('users', array('id' => $link)));
				$group_links = count($this->query_select('groups', array('id' => abs($link))));
				
				if ($user_links || $group_links) {
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
				// Cant update if link not specified
				return false;
			}
		} else {
			if (!isset($link)) { $link = $this->user_id; }
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
	private function get_user_or_group($type = 'users', $id = false, $include_options = false, $inherit = true) {
		// Check if entry is numeric or string
		if ($id !== false) {
			if (is_numeric($id)) {
				$id = array('id'=>abs($id));
			} else if (is_string($id)) {
				$id = array(($type=='groups'?'name':'username')=>$id);
			} else {
				die('Malformed input: Only Types STRING, INTEGER, BOOLEAN(false only) accepted');
			}
		} else {
			$id = array();
		}
		
		// Perform Lookup
		$output = array();
		foreach($this->query_select($type, $id) as $key => $value) {
			$value['id'] = (abs($value['id']) * ($type=='groups'?-1:1));
			foreach($value as $k => $v) {
				if (is_string($k)) {
					$output[$value['id']][$k] = $v;
				}
			}
			// Get Options
			if ($include_options) {
				$output[$value['id']]['options'] = current($this->options($value['id']));
				if (!$output[$value['id']]['options']) { $output[$value['id']]['options'] = array(); }
				$inheritFieldName = ($type=='groups'?'inherit_groups_id':'groups_id');
				// Inherit As Needed
				if ($inherit && isset($value[$inheritFieldName]) && $value[$inheritFieldName]) {
					$groupOpts = current($this->get_user_or_group('groups', $value[$inheritFieldName], true))['options'];
					// Add in missing fields if there are any
					if (is_array($groupOpts)) {
						foreach($groupOpts as $k => $v) {
							if (!isset($output[$value['id']]['options'][$k])) {
								$output[$value['id']]['options'][$k] = $v;
							}
						}
					}
				}
			}
		}
		return $output;
	}
	
	private function session_init() {
		if (isset($_SESSION[$this->persist_name]) && $_SESSION[$this->persist_name]) {
			// Assign user to class
			$user = current($this->get_user_or_group('users', $_SESSION[$this->persist_name]['user_id']));
			if ($user) {
				$this->user = $user;
				$this->user_id = $user['id'];
				return true;
			} else {
				// User Doesnt Exists
				return false;
			}
		} else if (isset($_COOKIE[$this->persist_name]) && $_COOKIE[$this->persist_name]) {
			$cookieDigest = json_decode($_COOKIE[$this->persist_name],true);
			if (is_array($cookieDigest) && isset($cookieDigest['user']) && isset($cookieDigest['token'])) {
				$user = current($this->get_user_or_group('users', $cookieDigest['user']));
				if ($user && $user['sessions'] && is_array($user['sessions'])) {
					foreach($user['sessions'] as $k => $v) {
						if ($k == $cookieDigest['token'] && $v >= time()) {
							// If session matches and isnt expired in db then log user in
							$this->user = $user;
							$this->user_id = $user['id'];
							$_SESSION[$this->persist_name]['user_id'] = $user['id'];
							return true;
						}
					}
					// No Session
					return false;
				} else {
					// User doesnt exist OR user has no sessions
					return false;
				}
			} else {
				// Malformed Cookie
				return false;
			}
			
		}
	}
	
	private function db_structure() {
		// Field Template
		// 'example' => array('type' => 'TEXT', 'length' => '50', 'unsigned' => false, 'notnull' => false, 'zerofill' => false, 'primary' => false, 'autoinc' => false, 'unique'=> false, 'default' => '', 'comment' => ''),
		// type and length are required
		return array(
			'version' => '0.61',
			'tables' => array(
				'global' => array(
					'name' => array('type' => 'VARCHAR', 'length' => 100, 'primary' => true,), 
					'value' => array('type' => 'TEXT', 'length' => 100,),
				),
				'options' => array(
					'link_id' => array('type' => 'INT', 'length' => 11, 'primary' => true,),
					'name' => array('type' => 'VARCHAR', 'length' => 100, 'primary' => true,),
					'value' => array('type' => 'TEXT', 'length' => 100,),
				),
				'users' => array(
					'id' => array('type' => 'INT', 'length' => 11, 'primary' => true, 'autoinc' => true,),
					'groups_id' => array('type' => 'INT', 'length' => 100,),
					'username' => array('type' => 'VARCHAR', 'length' => 100,),
					'pass_hash' => array('type' => 'VARCHAR', 'length' => 320,),
					'sessions' => array('type' => 'TEXT', 'length' => 100,),
					'active' => array('type' => 'INT', 'length' => 11,),
					'first' => array('type' => 'VARCHAR', 'length' => 50,),
					'last' => array('type' => 'VARCHAR', 'length' => 50,),
					'otp_key' => array('type' => 'TEXT', 'length' => 100,),
				),
				'groups' => array(
					'id' => array('type' => 'INT', 'length' => 11, 'primary' => true, 'autoinc' => true,),
					'inherit_groups_id' => array('type' => 'INT', 'length' => 11,),
					'name' => array('type' => 'VARCHAR', 'length' => 100,),
				),
			),
		);
	}
	
	private function create_db($recreate = false) {
		$db_structure = $this->db_structure();
		
		switch ($this->db_type) {
			case 'sqlite':
				if ($recreate || !isset($this->db)) {
					$this->db = new PDO('sqlite:'.$this->db_options);
					# $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				}
				
				// Type mappings
				$typeMap = function($key) {
					$map = array(
						'TINYINT' => 'INTEGER',
						'SMALLINT' => 'INTEGER',
						'MEDIUMINT' => 'INTEGER',
						'INT' => 'INTEGER',
						'BIGINT' => 'INTEGER',
						'BIT' => 'INTEGER',
						'FLOAT' => 'REAL',
						'DOUBLE' => 'REAL',
						'DECIMAL' => 'REAL',
						'CHAR' => 'TEXT',
						'VARCHAR' => 'TEXT',
						'TINYTEXT' => 'TEXT',
						'MEDIUMTEXT' => 'TEXT',
						'LONGTEXT' => 'TEXT',
						'JSON' => 'TEXT',
						'BINARY' => 'BLOB',
						'VARBINARY' => 'BLOB',
						'TINYBLOB' => 'BLOB',
						'MEDIUMBLOB' => 'BLOB',
						'LONGBLOB' => 'BLOB',
						'DATE' => 'TEXT',
						'TIME' => 'TEXT',
						'YEAR' => 'TEXT',
						'DATETIME' => 'TEXT',
						'TIMESTAMP' => 'TEXT',
						'POINT' => 'NULL',
						'LINESTRING' => 'NULL',
						'POLYGON' => 'NULL',
						'GEOMETRY' => 'NULL',
						'MULTIPOINT' => 'NULL',
						'MULTILINESTRING' => 'NULL',
						'MULTIPOLYGON' => 'NULL',
						'GEOMETRYCOLLECTION' => 'NULL',
						'ENUM' => 'NULL',
						'SET' => 'NULL',
					);
					
					if (in_array($key, array_keys($map))) {
						return $map[$key];
					} else {
						return $key;
					}
				};
				
				// Table Creation
				$success = true;
				foreach($db_structure['tables'] as $tableName => $tableFields) {
					// Field/Key Arrays
					$fields = array();
					$primaryKey = array();
					// Field Processing
					foreach($tableFields as $fieldName => $fieldAttrs) {
						// General
						if (isset($fieldAttrs['type']) && $fieldAttrs['type']) { $fieldAttrs['type'] = $typeMap($fieldAttrs['type']); } else { die('Malformed Field: `'.$tableName.'`.`'.$fieldName.'`(Attr:Type)'); }
						if (isset($fieldAttrs['notnull']) && $fieldAttrs['notnull']) { $fieldAttrs['notnull'] = 'NOT NULL'; } else { $fieldAttrs['notnull'] = ''; }
						if (isset($fieldAttrs['autoinc']) && $fieldAttrs['autoinc']) { $fieldAttrs['autoinc'] = 'PRIMARY KEY AUTOINCREMENT'; unset($fieldAttrs['primary']); } else { $fieldAttrs['autoinc'] = ''; }
						if (isset($fieldAttrs['default']) && $fieldAttrs['default']) { $fieldAttrs['default'] = 'DEFAULT \''.addslashes($fieldAttrs['default']).'\''; } else { $fieldAttrs['default'] = ''; }
						if (isset($fieldAttrs['unique']) && $fieldAttrs['unique']) { $fieldAttrs['unique'] = 'UNIQUE'; } else { $fieldAttrs['unique'] = ''; }
						// Keys
						if (isset($fieldAttrs['primary']) && $fieldAttrs['primary']) {
							$fieldAttrs['primary'] = '';
							$primaryKey[] = '`'.$fieldName.'`';
						} else { $fieldAttrs['primary'] = ''; }
						// Form Field Query
						$fields[] = '`'.$fieldName.'` '.$fieldAttrs['type'].' '.$fieldAttrs['notnull'].' '.$fieldAttrs['unique'].' '.$fieldAttrs['autoinc'].' '.$fieldAttrs['default'];
					}
					// Add in field query for primary and unique
					if ($primaryKey) {
						$fields[] = 'PRIMARY KEY ('.implode(',',$primaryKey).')';
					}
					
					// Query Execution
					$query = 'CREATE TABLE `'.$tableName.'`('.implode(',', $fields).');';
					
					if (!$this->db->query($query)) {
						debug_out($this->db->errorInfo());
						die('Create Failed: '.$query.'<br>Db Error: '.implode(' : ',$this->db->errorInfo()));
					}
				}
				break;
			case 'mysql':
				if ($recreate) {
					$this->db->query('CREATE DATABASE '.$this->db_options['database'].';');
					$this->db->query('USE '.$this->db_options['database'].';');
				}
				
				// Type mappings
				$typeMap = function($key) {
					$map = array(
						'INTEGER' => 'INT',
						'REAL' => 'FLOAT',
						'NULL' => 'TEXT',
					);
					
					if (in_array($key, array_keys($map))) {
						return $map[$key];
					} else {
						return $key;
					}
				};
				
				// Table Creation
				$success = true;
				foreach($db_structure['tables'] as $tableName => $tableFields) {
					// Field/Key Arrays
					$fields = array();
					$hasAuto = false;
					$primaryKey = array();
					$uniqueKey = array();
					// Field Processing
					foreach($tableFields as $fieldName => $fieldAttrs) {
						// General
						if (isset($fieldAttrs['type']) && $fieldAttrs['type']) { $fieldAttrs['type'] = $typeMap($fieldAttrs['type']); } else { die('Malformed Field: `'.$tableName.'`.`'.$fieldName.'`(Attr:Type)'); }
						if (isset($fieldAttrs['length']) && $fieldAttrs['length']) { $fieldAttrs['length'] = '('.$fieldAttrs['length'].')'; } else { $fieldAttrs['length'] = ''; }
						if (isset($fieldAttrs['unsigned']) && $fieldAttrs['unsigned']) { $fieldAttrs['unsigned'] = 'UNSIGNED'; } else { $fieldAttrs['unsigned'] = ''; }
						if (isset($fieldAttrs['notnull']) && $fieldAttrs['notnull']) { $fieldAttrs['notnull'] = 'NOT NULL'; } else { $fieldAttrs['notnull'] = 'NULL'; }
						if (isset($fieldAttrs['zerofill']) && $fieldAttrs['zerofill']) { $fieldAttrs['zerofill'] = 'ZEROFILL'; } else { $fieldAttrs['zerofill'] = ''; }
						if (isset($fieldAttrs['autoinc']) && $fieldAttrs['autoinc']) { $fieldAttrs['autoinc'] = 'AUTO_INCREMENT'; $hasAuto = true; } else { $fieldAttrs['autoinc'] = ''; }
						if (isset($fieldAttrs['default']) && $fieldAttrs['default']) { $fieldAttrs['default'] = 'DEFAULT \''.addslashes($fieldAttrs['default']).'\''; } else { $fieldAttrs['default'] = ''; }
						if (isset($fieldAttrs['comment']) && $fieldAttrs['comment']) { $fieldAttrs['comment'] = 'COMMENT \''.addslashes($fieldAttrs['comment']).'\''; } else { $fieldAttrs['comment'] = ''; }
						// Keys
						if (isset($fieldAttrs['primary']) && $fieldAttrs['primary']) { 
							$fieldAttrs['primary'] = '';
							$primaryKey[] = '`'.$fieldName.'`'.(in_array($fieldAttrs['type'], array('CHAR','VARCHAR','TINYTEXT','TEXT','MEDIUMTEXT','LONGTEXT'))?$fieldAttrs['length']:'');
						} else { $fieldAttrs['primary'] = ''; }
						if (isset($fieldAttrs['unique']) && $fieldAttrs['unique']) {
							$fieldAttrs['unique'] = '';
							if (is_bool($fieldAttrs['unique'])) {
								$uniqueKey[] = array($fieldName);
							} else {
								$uniqueKey[$fieldAttrs['unique']] = $fieldName;
							}
						} else { $fieldAttrs['unique'] = ''; }
						// Form Field Query
						$fields[] = '`'.$fieldName.'` '.$fieldAttrs['type'].$fieldAttrs['length'].' '.$fieldAttrs['unsigned'].' '.$fieldAttrs['zerofill'].' '.$fieldAttrs['notnull'].' '.$fieldAttrs['autoinc'].' '.$fieldAttrs['default'].' '.$fieldAttrs['comment'];
					}
					// Add in field query for primary and unique
					if ($primaryKey) {
						$fields[] = 'PRIMARY KEY ('.implode(',',$primaryKey).')';
					}
					if ($uniqueKey) {
						foreach($uniqueKey as $k => $v) {
							$fields[] = 'UNIQUE INDEX `'.$tableName.'_'.implode('_', $v).'` (`'.implode('`,`',$v).'`)';
						}
					}
					
					// Query Execution
					$query = 'CREATE TABLE `'.$tableName.'`('.implode(',', $fields).') COLLATE=\'utf8_general_ci\' ENGINE=InnoDB '.($hasAuto?'AUTO_INCREMENT=1':'').';';
					if (!$this->db->query($query)) {
						die('Create Failed: '.$query.'<br>Db Error: '.$this->db->error);
					}
				}
				break;
		}
		
		// Assign the DB Version
		$this->globalOpts(array('db_version' => $db_structure['version']));
		return true;
	}
	
	private function upgrade_db() {
		$globals = $this->globalOpts();
		
		if (!isset($globals['db_version']) || $globals['db_version'] < $this->db_structure()['version']) {
			// Cache Contents
			$cache = array();
			foreach(array_keys($this->db_structure()['tables']) as $table) { // ========== Still not great since it will likely error if new tables are added
				foreach($this->query_select($table) as $key => $row) {
					foreach($row as $k => $v) {
						if (is_string($k)) {
							$cache[$table][$key][$k] = $v;
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
	
	private function destroy_db($justTables = false) {
		switch ($this->db_type) {
			case 'sqlite':
				$this->db = null;
				if (file_exists($this->db_options)) {
					rename($this->db_options, preg_replace('/\.db$/','.bak.db',$this->db_options));
				}
				return true;
				break;
			case 'mysql':
				if ($justTables) {
					$result = $this->db->query('SHOW TABLES;');
					$tables = array();
					while($row = $result->fetch_assoc()) {
						$this->db->query('DROP TABLE '.$row['Tables_in_'.$this->db_options['database']].';');
					}
				} else {
					$this->db->query('DROP DATABASE '.db_options['database'].';');
				}
				break;
		}
	}
	
	private function query_create($table, $data) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$data = $this->condition_data($data);
				// Process Query
				return ($this->db->query('INSERT OR IGNORE INTO '.$table.'(`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$data).');')->rowCount()?true:false);
				break;
			case 'mysql':
				// Condition data
				$data = $this->condition_data($data);
				// Process Query
				return $this->db->query('INSERT INTO '.$table.'(`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$data).');');
				break;
		}
	}
	
	private function query_update($table, $data, $where) {
		switch ($this->db_type) {
			case 'sqlite':
				// Condition data
				$data = $this->condition_data($data);
				// Perform Query
				return ($this->db->query('UPDATE '.$table.' SET '.implode(',',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($data),$data)).($where?' WHERE '.$this->condition_where($where):'').';')->rowCount()?true:false);
				break;
			case 'mysql':
				// Condition data
				$data = $this->condition_data($data);
				// Perform Query
				return $this->db->query('UPDATE '.$table.' SET '.implode(',',array_map(function($k,$v) { return "`$k` = $v"; },array_keys($data),$data)).($where?' WHERE '.$this->condition_where($where):'').';');
				break;
		}
	}
	
	private function query_create_or_update($table, $data) {
		switch ($this->db_type) {
			default: // Other functions will handle the specifics for now
			case 'sqlite':
				if ($this->query_create($table, $data)) {
					return true;
				} else {
					// Find the Primary Keys
					$db_structure = $this->db_structure()['tables'];
					$primaryKeys = array();
					if (isset($db_structure[$table])) {
						foreach($db_structure[$table] as $k => $v) {
							if ((isset($v['primary']) && $v['primary']) || (isset($v['autoinc']) && $v['autoinc'])) {
								$primaryKeys[] = $k;
							}
						}
					}
					
					// Generate WHERE array
					$where = array();
					foreach($data as $k => $v) {
						if (in_array($k,$primaryKeys)) {
							$where[$k] = $v;
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
				// Perform Query
				return ($this->db->query('DELETE FROM '.$table.($where?' WHERE '.$this->condition_where($where):'').';')->rowCount()?true:false);
				break;
			case 'mysql':
				// Perform Query
				return $this->db->query('DELETE FROM '.$table.($where?' WHERE '.$this->condition_where($where):'').';');
				break;
		}
	}
	
	private function query_select($table, $where = array()) {
		switch ($this->db_type) {
			case 'mysql': // This works for now
			case 'sqlite':
				// Condition data
				$results = array();
				foreach($this->db->query('SELECT * FROM '.$table.($where?' WHERE '.$this->condition_where($where):'').';') as $key => $value) {
					foreach($value as $k => $v) {
						if (is_string($k)) {
							if (preg_match('/^JSON\|(.*)/',$v,$json) === 1) {
								$v = json_decode(stripslashes($json[1]),true);
							}
							$results[$key][$k] = $v;
						}
					}
				}
				return $results;
				break;
			case 'mysql':
				// Maybe something more complicated later?
				break;
		}
	}
	
	private function condition_data($data) {
		foreach($data as $key => $value) {
			if (is_array($value)) { $value = 'JSON|'.json_encode($value); }
			if (strtolower($value) !== 'null') { $value = "'".addslashes($value)."'"; }
			$data[$key] = $value;
		}
		return $data;
	}
	
	private function condition_where($where, $andOr = false) {
		$output = array();
		foreach($where as $k => $v) {
			if (is_string($k)) {
				if (is_array($v)) {
					$compare = '=';
					if (isset($v[0]) && $v[0]) { $compare = $v[0]; }
					if (isset($v['sign']) && $v['sign']) { $compare = $v['sign']; }
					$val = null;
					if (isset($v[1]) && $v[1]) { $val = $v[1]; }
					if (isset($v['val']) && $v['val']) { $val = $v['val']; }
					if (isset($v['value']) && $v['value']) { $val = $v['value']; }
					
					$output[] = '`'.$k.'`'.$compare.($val==='null'?$val:"'$val'");
				} else {
					$output[] = '`'.$k.'`=\''.$v.'\'';
				}
			} else if (is_array($v)) {
				$output[] = '('.$this->condition_where($v, !$andOr).')';
			} else {
				die('Malformed Where Arguments');
			}
		}
		
		return implode(($andOr?' OR ':' AND '),$output);
	}
}

// Create DB Connection
$test = new db_conn(0,'./test.db');

// Test user full circle
$user = randString();
$pass = randString();
debug_out($user);
debug_out($pass);
debug_out('Create User: '.$test->createUser(array(
	'username' => $user,
	'password' => $pass,
)));
debug_out('Apply User Settings: '.$test->options($test->getUser($user)['id'], array(
	'set1' => randString(),
	'set2' => randString(),
	'set3' => randString(),
	'set4' => array(randString(), randString() => randString()),
	randString() => randString(),
)));
debug_out($test->loginUser($user,$pass));
debug_out($test->getUser($user,true));
debug_out($test->deleteUser($user));

// Test Groups
debug_out('Create Group: '.$test->createGroup(array(
	'name' => randString(),
)));
$groups = $test->getGroups();
debug_out($groups);

// Test Options Group
debug_out('Apply Group Settings: '.$test->options(end($groups)['id'], array(
	'group1' => randString(),
	'group2' => randString(),
	'group3' => randString(),
	'group4' => randString(),
	randString() => randString(),
)));
debug_out($test->options(end($groups)['id']));

// Test Users
debug_out('Create User: '.$test->createUser(array(
	'username' => randString(),
	'pass_hash' => randString(),
	'groups_id' => end($groups)['id'],
)));
$users = $test->getUsers();
debug_out($users);

// Test Options User
debug_out('Apply User Settings: '.$test->options(end($users)['id'], array(
	'set1' => randString(),
	'set2' => randString(),
	'set3' => randString(),
	'set4' => array(randString(), randString() => randString()),
	randString() => randString(),
)));
debug_out($test->options(end($users)['id']));

// Group with options
debug_out($test->getGroup(-1,true));
debug_out($test->getGroups(true));

// Users with options
debug_out($test->getUser(1,true));
debug_out($test->getUsers(true));


