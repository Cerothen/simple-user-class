# simple-user-class
Generic simple user class for storing data about users in one of many different types of databases.

# General Usage
  Connection for SQLite
```
$test = new db_conn(0,'./test.db');
```
  Connection for MySQL
```
$test = new db_conn(1,array(
	'host' => '127.0.0.1',
	'user' => 'root',
	'pass' => 'password',
	'database' => 'db_name',
));
```
  Read Global Settings
```
  $variable = $test->globalOpts();
```
  Set/Create Global Options
```
  $variable = $test->globalOpts(array(
    'option' => 'value',
  ));
  $variable = $test->globalOpts(array(
    'option' => 'value',
    'option2' => 'value2',
    'option3' => 'value3',
  ));
```
  Update Existing Global Options
```
  $variable = $test->globalOpts(array(
    'option' => 'new value',
  ));
```
  Create User
```
$success = $test->createUser(array(
	'username' => 'username',
	'pass_hash' => 'password_hash',
));
```
  Get User List
```
$users = $test->getUsers();
$users = $test->getUsers(true); // Add true to get all associated user options
$users = $test->getUsers(true, false); // Add false to not get inherited options
```
  Get Specific User
```
$user = $test->getUser(1);
$user = $test->getUser('Cerothen'); // Users can also be retreived by username
$user = $test->getUser(1, true); // Add true to get all associated user options
$user = $test->getUser(1, true , false); // Add false to not get inherited options
```
  Delete User
```
$test->deleteUser(1);
$test->deleteUser('Cerothen');
```
  Login User
```
$test->loginUser($user,$pass); // Username must be string and not the user's ID
$test->loginUser($user,$pass, true); // Add true to set a 1 week cookie for persistant logins
```
  Logout User
```
$test->logoutUser(); // Logout a user that is logged into the class
$test->logoutUser(true); // Add true to destroy every cookie login
```
  Get Group List
```
$groups = $test->getGroups();
$groups = $test->getGroups(true); // Add true to get all associated user options
$groups = $test->getGroups(true, false); // Add false to not get inherited options
```
  Get Specific Group
```
$user = $test->getGroup(1); // Group id's are stored negative in the database, you can enter either the positive or negative version here (eg. -6 = 6).
$user = $test->getGroup('admins'); // Groups can also be retreved by the group name
$user = $test->getGroup(1, true); // Add true to get all associated user options
$user = $test->getGroup(1, true , false); // Add false to not get inherited options
```
  Delete Group
```
$test->deleteGroup(1); 
$test->deleteGroup('users');
```
  Get All Options
```
$options = $test->options('*');
```
  Get Options for User or Group
```
$options = $test->options(1); // User IDs are positive
$options = $test->options(-1); // Group IDs are negative
```
Example Create User, Add Some Options, Log User In, Show User Details with Options and Delete User
```
$test = new db_conn(0,'./test.db');

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
```
