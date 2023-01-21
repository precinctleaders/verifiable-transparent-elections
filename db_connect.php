<?php
$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.'); //this page can only be called from the one master web page and will otherwise stop

$_SERVER['con'] = mysqli_connect("host","username","password");
if (!$_SERVER['con'])
{
   echo 'Could not connect to database.<br><br>';
   echo 'Would you like a random new password? Try one of these:<br><br>';
   for ($x = 0; $x<20; $x++)
   {
		random_str() . random_str(64,'O0o1lI') . "<br>";   
   }
   exit;
}
mysqli_select_db($_SERVER['con'],"db85847_washtenawgop");

$_SERVER['title'] = "Washtenaw County Republican Convention";
?>
