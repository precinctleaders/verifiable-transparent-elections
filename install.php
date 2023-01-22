<?php
$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.');

//create random string for this installation to seed encryption.
if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_randomseed`"))
{
	//create random seeds. One is permanent for password protection, the other may be changed before voting opens to ensure unknowable alias encryption seed
	$createseedtable = "
	CREATE TABLE IF NOT EXISTS `election_randomseed` (
	  `key` varchar(6) NOT NULL,
	  `seed` varchar(64) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createseedtable)) redalert("ERROR: Could not create random seed table: " . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_randomseed` ADD PRIMARY KEY (`key`)")) redalert("ERROR: Could not create primary key on random seed table");
	$randomness = random_str();
	$random = mysqli_query($_SERVER['con'], "INSERT INTO `election_randomseed` (`key`,`seed`) VALUES ('Global', '" . mrs($randomness) . "')");
}

if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters`"))
{
	//create user table
	$createusertable = "
	CREATE TABLE IF NOT EXISTS `election_voters` (
	  `id` int(11) NOT NULL,
	  `firstname` varchar(30) NOT NULL,
	  `lastname` varchar(30) NOT NULL,
	  `username` varchar(50) NOT NULL,
	  `password` varchar(100) NOT NULL,
	  `is_admin` varchar(1) NOT NULL,
	  `alias` varchar(50) NOT NULL,
	  `created` DATETIME NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createusertable)) redalert("ERROR: Could not create users table" . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_voters` ADD PRIMARY KEY (`id`)")) redalert("ERROR: Could not create primary key on users table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_voters` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;")) redalert("ERROR: Could not create primary key on users table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_voters` ADD UNIQUE(`firstname`, `lastname`, `username`);")) redalert("ERROR: Could not create unique key on users table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_voters` ADD UNIQUE(`username`);")) redalert("ERROR: Could not create unique username key on users table");
}
if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections`"))
{
	//create elections table
	$createelectiontable = "
	CREATE TABLE IF NOT EXISTS `election_elections` (
	  `id` int(11) NOT NULL,
	  `name` varchar(30) NOT NULL,
	  `cand_num` int(11) NOT NULL,
	  `status` varchar(1) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createelectiontable)) redalert("ERROR: Could not create elections table" . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_elections` ADD PRIMARY KEY (`id`)")) redalert("ERROR: Could not create primary key on elections table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_elections` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;")) redalert("ERROR: Could not create primary key on elections table");
}
if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands`"))
{
	//create candidates table
	$createcandtable = "
	CREATE TABLE IF NOT EXISTS `election_cands` (
	  `id` int(11) NOT NULL,
	  `electionid` int(11) NOT NULL,
	  `firstname` varchar(30) NOT NULL,
	  `lastname` varchar(30) NOT NULL	  
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createcandtable)) redalert("ERROR: Could not create candidates table" . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_cands` ADD PRIMARY KEY (`id`)")) redalert("ERROR: Could not create primary key on candidates table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_cands` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;")) redalert("ERROR: Could not create primary key on candidates table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_cands` ADD INDEX(`electionid`);")) redalert("ERROR: Could not create election index on candidates table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_cands` ADD UNIQUE(`electionid`, `firstname`, `lastname`);")) redalert("ERROR: Could not create unique candidate index on candidates table");
}
if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots`"))
{
	//create ballots table
	$createballottable = "
	CREATE TABLE IF NOT EXISTS `election_ballots` (
	  `id` int(11) NOT NULL,
	  `electionid` int(11) NOT NULL,
	  `alias` varchar(50) NOT NULL,
	  `ballot` text NOT NULL,
	  `stamp` datetime NOT NULL	  
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createballottable)) redalert("ERROR: Could not create ballots table" . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_ballots` ADD PRIMARY KEY (`id`)")) redalert("ERROR: Could not create primary key on ballots table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_ballots` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;")) redalert("ERROR: Could not create primary key on ballots table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_ballots` ADD UNIQUE(`electionid`, `alias`);")) redalert("ERROR: Could not create unique ballot key on ballots table");
}
if ($_POST['submit'] == 'Create Admin') if (!$admincheck = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters` WHERE `is_admin` = 'Y'")))
if (!$tablecheck = mysqli_query($_SERVER['con'],"SELECT * FROM `election_checkin` WHERE 1"))
{
	//create check-in table
	$createballottable = "
	CREATE TABLE IF NOT EXISTS `election_checkin` (
	  `id` int(11) NOT NULL,
	  `userid` int(11) NOT NULL,
	  `checkin_time` datetime NOT NULL	  
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
	if (!$createtable = mysqli_query($_SERVER['con'],$createballottable)) redalert("ERROR: Could not create checkin table" . mysqli_error($_SERVER['con']));
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_checkin` ADD PRIMARY KEY (`id`)")) redalert("ERROR: Could not create primary key on check-in table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_checkin` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;")) redalert("ERROR: Could not create primary key on checkin table");
	if (!$addidex = mysqli_query($_SERVER['con'], "ALTER TABLE `election_checkin` ADD UNIQUE(`userid`);")) redalert("ERROR: Could not create unique ballot key on checkin table");
}
if ($_POST['submit'] == 'Create Admin') if (!$admincheck = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters` WHERE `is_admin` = 'Y'")))
{
	//Form processing to create administrator account. This will only work on a fresh install
	//generate encrypted password using username, password and random string
	$password = crypt(md5(strtolower($_POST['password']) . $_SERVER['randomness']),md5(strtolower($_POST['username'])));
	$now = date("Y-m-d H:i:s");
	$alias = random_str(10);
	$createq = "INSERT INTO `election_voters` (`firstname`,`lastname`,`username`,`password`,`is_admin`,`created`,`alias`) VALUES ('" . mrs($_POST['firstname']) . "' , '" . mrs($_POST['lastname']) . "' , '" . mrs($_POST['username']) . "' , '" . mrs($password) . "' , 'Y', '" . mrs($now) . "', '" . mrs($alias) . "')";
	if (!$createadmin = mysqli_query($_SERVER['con'],$createq)) redalert("Error: couldn't create administrator" . $createq);
	else 
	{
		redalert("Administrator created successfully. [<a href='index.php'>Login</a>]");
		exit;
	}
}

?>
<form action='index.php' method='post'>
<b>Create administrator</b>
<br><br><table><tr><td>First Name:</td><td><input type='text' name='firstname'></td></tr>
<tr><td>Last Name: </td><td><input type='text' name='lastname'></td></tr>
<tr><td>Username: </td><td><input type='text' name='username'></td></tr>
<tr><td>Password: </td><td><input type='password' name='password'></td></tr>

<tr><td><input type='submit' name='submit' value='Create Admin'></td></tr></table>
<br></form>
