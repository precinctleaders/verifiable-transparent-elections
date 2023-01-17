<?php
///Initialize the page. Define common functions, set the time zone, connect to the database, set the session (logout if requested by user), and output the HTML header

//require secure connection
if($_SERVER["HTTPS"] != "on")
{	
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
    exit();
}


//Common functions
function mrs($inp) { 
	//saves me typing "mysqli_real_escape_string" a million times. Cleanses database input
	if(is_array($inp)) return array_map(__METHOD__, $inp);
    if(!empty($inp) && is_string($inp)) {
        return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
    }
    return $inp;
} 

function hasdbtable($tablename) {
  $r = mysqli_query(
    $_SERVER['con'],
    "SELECT EXISTS(SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAM    E = '" . $tablename . "')"
  );
  if (!$r) {
    return false;
  }
  try {
    $count = $r -> fetch_row()[0];
    return $count > 0;
  } catch (Exception $ex) {
    return false;
  } finally {
    $r -> close();
  }
}

function nicedate($stamp)
{
	//  convert YYYY-MM-DD to MM/DD/YY
	return $stamp[5].$stamp[6]."/".$stamp[8].$stamp[9]."/".$stamp[2].$stamp[3];
}

function redalert($text) {
	//create highlight box around input text
	echo "<table width='100%' cellpadding='10px' style='border:2px solid #700;box-shadow: 2px 2px 2px #999;'><tr><td style='border-radius:2px 5px 2px 0px;background-image:linear-gradient(#d90, #ff0);color:#000;padding:10px;'><b>" . $text . "</b></td></tr></table><br><br>";
	return true;
}

function random_str(
    int $length = 64,
    string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
): string {
    if ($length < 1) {
        throw new \RangeException("Length must be a positive integer");
    }
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $pieces []= $keyspace[random_int(0, $max)];
    }
    return implode('', $pieces);
}
//end common functions


header('Content-type: text/html; charset=ASCII');
date_default_timezone_set("America/Detroit");
define('DOCUMENT_ROOT', dirname(realpath(__FILE__)).'/');
require('db_connect.php');		//this function connects to the database and is the ONLY file that contains privileged information (the db login)
//$_SERVER['con'] is the database connection, set to superglobal

$seed = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_randomseed` WHERE `key` = 'Global'"));
$_SERVER['randomness'] = $seed['seed'];

session_start();

//logout if requested
if ($_REQUEST['logout'] == 'Y') 
{
	//execute logout
	session_destroy();
	unset($_SESSION);
	redalert("Logged out successfully.");
}

//HTML header
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Washtenaw County GOP Elections</title>
	<meta property="og:title" content="Washtenaw GOP Voting System">
	<meta property="og:type" content="website">
	<meta property="og:description" content="Welcome to Our Voting System">
	<link href="https://fonts.googleapis.com/css?family=Montserrat" rel="stylesheet" type="text/css">
	<style type='text/css'>
		html, body { font-size: 16px; }
		body {
			font-size: 1.25rem;
			font-family: Montserrat, Helvetica, Arial, sans-serif;
		}
		div { max-width: 90vw; }
		h1, h2, h3, h4, h5, h6 { font-family: serif; }
		h1 { font-size: 2rem; }
		h2 { font-size: 1.7rem; }
		h3 { font-size: 1.5rem; }
		h4 { font-size: 1.3rem; }
		h5 { font-size: 1.1rem; }
		h6 { font-size: 1.05rem; }
		.admin-tools {
			width: 100vw;
			overflow: auto;
			background: #efe;
			padding: 10px;
			border: 1px solid #000;
		}
		table, tr, th, td {
			font-size: 1.25rem;
		}
		th {
			color: #fff;
			background-color: #314C76;
		}
		th, td {
			padding: 0.5rem;
		}
		.big-btn {
			font-size: 1.75rem;
			background-color: rgba(49, 76, 118, 0.85);
			color: #FFF;
			padding: 5px;
		}
		.candidate > .checkbox {
			width:  1.75rem;
			height: 1.75rem;
			position: relative;
			top: 0.25rem;
			margin-right: 1.33rem;
		}
		.candidate > .checkbox:checked + .label {
			color: blue;
			font-weight: bold;
			margin-right: 1rem;
		}
		.candidate > .label {
			font-size: 1.75rem;
		}
		#votesremaining {
			display: block;
		  position: fixed;
			right: 1.5rem;
			bottom: 1.5rem;
			border: 3px double #000;
			padding: 5px;
			background: #ff6;
			width: 7rem;
		}
		#votesremainingcount {
			font-size: 2.75rem;
			font-weight: bold;
			text-align: center;
		}
	</style>
</head>
<body>

<?
//Check to see if the database has been set up. If not, run the installation program and nothing else.
if (!$admincheck = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `is_admin` = 'Y'")))
{
	require('install.php');
	exit;
}

if (is_numeric($_SESSION['id']))
{
	//double-check admin status every time page refreshes
	if ($self = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `id` = " . mrs($_SESSION['id']))))
	{
		$_SESSION['is_admin'] = $self['is_admin'];
	}
}
?>
