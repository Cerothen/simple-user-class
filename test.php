<?php
require_once('./userclass.php');

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

$test = new db_conn(0,'./test2.db');
$test->importDatabase(0,'./test.db');
debug_out($test->globalOpts());

debug_out($test->get_database_tables(true));

debug_out($test->globalOpts());

$user = current($test->getUsers());
debug_out($user);
debug_out('Apply User Settings: '.$test->options($user['id'], array(
	'set1' => randString(),
	'set2' => randString(),
	'set3' => randString(),
)));
debug_out($test->getUser($user['id'],true));
debug_out('Remove User Settings: '.$test->removeOptions($user['id'], array(
	'set1',
	'set2',
)));
debug_out($test->getUser($user['id'],true));

// Session Testing
if (is_null($test->user['id'])) {
	debug_out('Not Logged In');
	$user = randString();
	$pass = randString();
	debug_out('Create User: '.$test->createUser(array(
		'username' => $user,
		'password' => $pass,
	)));
	debug_out($test->loginUser($user,$pass, true));
} else {
	debug_out('Logged In');
	debug_out($test->user);
	debug_out($test->logoutUser());
}

debug_out($test->updateUser($user,array(
	'first' => 'Marky',
)));
debug_out($test->updateUser($test->getUser($user)['id'],array(
	'last' => 'Markity',
)));
debug_out($test->getUser($user,true));



die();
debug_out('Apply User Settings: '.$test->options($test->getUser($user)['id'], array(
	'set1' => randString(),
	'set2' => randString(),
	'set3' => randString(),
	'set4' => array(randString(), randString() => randString()),
	randString() => randString(),
)));

debug_out($test->options());
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
	'password' => randString(),
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
debug_out($test->getGroup(1,true));
debug_out($test->getGroups(true));

// Users with options
debug_out($test->getUser(2,true));
debug_out($test->getUsers(true));
