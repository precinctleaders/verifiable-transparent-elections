<?php
$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.');

$_SERVER['con'] = mysqli_connect("localhost","username","password");
if (!$_SERVER['con'])
{
   die('Could not connect: ' . mysqli_error($_SERVER['con']));
}
mysqli_select_db($_SERVER['con'],"dbname");
?>