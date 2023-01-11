<?php
//login/password routines 

$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.');


if ($_REQUEST['submit'] == 'Log In')
{
	//attempt to log in
	$password = crypt(md5(strtolower($_POST['password']) . $_SERVER['randomness']),md5(strtolower($_POST['email'])));
	if ($try = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `email` LIKE '" . mrs($_REQUEST['email']) . "' AND `password` LIKE '" . $password . "'"))) 
	{
		redalert("Logged in successfully.");
		foreach ($try as $key => $var) $_SESSION[$key] = $var;
		$_SESSION['is_logged_in'] = 'Y';
	}
	else if ($try = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `email` LIKE '" . mrs($_REQUEST['email']) . "' AND `tmp_password` LIKE '" . $password . "'"))) 
	{
		redalert("Logged in successfully.");
		foreach ($try as $key => $var) $_SESSION[$key] = $var;
		$_SESSION['is_logged_in'] = 'Y';
	}
	else redalert("Login failed; try again or reset your password at the check-in desk.");
}

if (($_SESSION['is_logged_in'] == 'Y') and ($_REQUEST['mode'] == 'changepw'))
{
	if ($_REQUEST['pw'] == 'Change Password')
	{
		//execute password reset
		$password = crypt(md5(strtolower($_POST['password']) . $_SERVER['randomness']),md5(strtolower($_SESSION['email'])));
		if (!$upd = mysqli_query($_SERVER['con'],"UPDATE `election_users` SET `password` = '" . mrs($password) . "' , `tmp_password` = '' WHERE `id` = " . mrs($_SESSION['id']))) redalert("Error: could not update your password.");
		else redalert("Password updated successfully.");
	}
	else
	{
		echo "<b>Change Your Password</b><form action='index.php' method='post'><input type='hidden' name='mode' value='changepw'>";
		echo "<br><br>New password: <input type='password' name='password'><br><br><input type='submit' name='pw' value='Change Password'>";
		echo "</form>";
		exit;
	}
}
?>