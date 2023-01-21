<?php
//login/password routines 

$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.'); //this page can only be called from the one master web page and will otherwise stop


if ($_REQUEST['submit'] == 'Log In')
{
	//attempt to log in
	$password = crypt(md5(strtolower($_POST['password']) . $_SERVER['randomness']),md5(strtolower($_POST['username'])));
	if ($try = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters` WHERE `username` LIKE '" . mrs($_REQUEST['username']) . "' AND `password` LIKE '" . $password . "'"))) 
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
		$password = crypt(md5(strtolower($_POST['password']) . $_SERVER['randomness']),md5(strtolower($_SESSION['username'])));
		if (!$upd = mysqli_query($_SERVER['con'],"UPDATE `election_voters` SET `password` = '" . mrs($password) . "' WHERE `id` = " . mrs($_SESSION['id']))) redalert("Error: could not update your password.");
		else redalert("Password updated successfully.");
	}
	else
	{
		?>
<SCRIPT LANGUAGE="JavaScript">
function validateForm ( ) {
	if (document.changeyourpw.password.value != document.changeyourpw.password2.value) {
		alert("Your password confirmation did not match; please re-enter them.\n");
		return false;
	}
	if (document.changeyourpw.password.value == '') {
		alert("Please enter a new password.\n");
		return false;
	}
	return true;
}
</script>
		<?
		
		echo "<b>Change Your Password</b><form action='index.php' name='changeyourpw' id='changeyourpw' method='post' onsubmit='return validateForm( );'><input type='hidden' name='mode' value='changepw'>";
		echo "<br><br>New password: <input type='password' id ='password' name='password'><br><br>Type again: <input type='password' id = 'password2' name='password2'><br><br><input type='submit' name='pw' value='Change Password'>";
		echo "</form>";
		exit;
	}
}
?>
