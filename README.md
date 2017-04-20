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
```
```
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
```
  Get Specific User
```
$user = $test->getUser(1);
$user = $test->getUser(1, true); // Add true to get all associated user options
```
  Get Group List
```
$groups = $test->getGroups();
$groups = $test->getGroups(true); // Add true to get all associated user options
```
  Get Specific Group
```
$user = $test->getGroup(1); // Group id's are stored negative in the database, you can enter either the positive or negative version here (eg. -6 = 6).
$user = $test->getGroup(1, true); // Add true to get all associated user options
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
