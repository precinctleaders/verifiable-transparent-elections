<?php 
error_reporting(0);
require('header.php'); //connects to database, loads common functions, and displays page header

echo "<h1>Washtenaw GOP Election System</h1>";
//Users can always click this to return to the main menu
echo "<br>[<a href='index.php'>Home/Refresh page</a>]<br><br>"; 

require('login.php'); // execute various login/password tests

if ($_SESSION['is_logged_in'] != 'Y')
{
	//if user isn't logged in, prompt to login
	?>
	<form action='index.php' method='post'>
	<table><tr><td>User: </td><td><input type='text' name='email'></td></tr>
	<tr><td>Password: </td><td><input type='password' name = 'password'></td></tr>
	<tr><td><input class='big-btn' type='submit' name='submit' value='Log In'</td></tr></table>
	</form>
	(If you forgot your password, you can reset it at the check-in desk.)
	
	
	<?
	exit;
}

//administrator tools
if (in_array($_SESSION['is_admin'], array('X','Y')))
{
	require('admin.php');
}

///////main users


//VOTE
if ($govote = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` WHERE `status` = 'O' AND `id` = " . mrs($_REQUEST['vote']))))
{
	//verify eligibility
	$verify = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `id` = " . mrs($_SESSION['id'])));
	if (!$checkedin = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_checkin` WHERE `userid` = " . $_SESSION['id'] . " AND `status` = 'C'")))
	{	
		//verify they have checked in
		echo "ERROR: You have not yet been checked in by the Credentials Committee. You can vote after you have checked in.<br><br><a href='index.php?vote=" . $_REQUEST['vote'] . "'>Refresh this page</a><br><br>";
		exit;
	}
	if ($_REQUEST['submit'] == 'Cast Ballot')
	{
		//cast the ballot
		$now = date("Y-m-d H:i:s");
		//verify election is open
		if (!$chk = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` WHERE `id` = " . mrs($_REQUEST['vote']) . " AND `status` = 'O'")))
		{
			redalert("Error: This election is closed and a ballot can no longer be submitted.");
			exit;
		}		
		//tabulate the ballot
		$secretkey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT `seed` FROM `election_randomseed` WHERE `key` = '" . mrs($_REQUEST['vote']) . "'"));
		$cands = mysqli_query($_SERVER['con'], "SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']));
		while ($cand = mysqli_fetch_array($cands)) if ($_REQUEST['cand' . $cand['id']] == 'Y') $candvotes[] = $cand['id'];
		$insq = "INSERT INTO `election_ballots` (`electionid`,`alias`,`ballot`,`stamp`) VALUES (" . mrs($_REQUEST['vote']) . " , '" . mrs(crypt(md5(strtolower($_SESSION['alias']) . $secretkey['seed']),md5(strtolower($_SESSION['email'])))) . "' , '" . implode(",",$candvotes) . "', '" . mrs($now) . "' )";
		if (!$ins = mysqli_query($_SERVER['con'],$insq)) 
		{
			redalert("ERROR: You have already voted in this election.<br><br><a href='index.php'>Main menu</a>"); // duplicate entries will fail
			exit;
		}
		else {
			redalert("Your ballot was cast successfully.<br><br><a href='index.php'>Main menu</a>");
			exit;
		}
		
	}
	if ($_REQUEST['submit'] == 'Review Ballot')
	{
		//review ballot
		$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']) . " ORDER BY `lastname`,`firstname`");
		while ($cand = mysqli_fetch_array($cands))
		{
			if ($_REQUEST['cand' . $cand['id']] == 'Y')
			$counter++;
		}
		if ($counter > $govote['cand_num'])
		{
			//over-vote
			redalert("ERROR: You tried to vote for " . $counter . " candidates, but you can only vote for " . $govote['cand_num'] . " candidates! Please try again:");
		}
		else
		{
			if ($counter < $govote['cand_num'])
			{
				//under-vote - show a notice but allow voter to approve
				redalert("You chose " . $counter . " candidates, but you could vote for up to " . $govote['cand_num'] . ". If you wish to vote for more, use the form below to revise your ballot.");
			}
			echo "<form action = 'index.php' method='post'><input type='hidden' name='vote' value='" . mrs($_REQUEST['vote']) . "'><div style='padding:10px;background:#eef;'>Cast your ballot for the following candidates?<br><br>";
			$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']) . " ORDER BY `lastname`,`firstname`");
			while ($cand = mysqli_fetch_array($cands))
			{
				if ($_REQUEST['cand' . $cand['id']] == 'Y')
				{
					echo $cand['firstname'] . " " . $cand['lastname'] . "<br>";
					echo "<input type='hidden' name='cand" . $cand['id'] . "' value='Y'>";
				}
			}
			echo "<input class='big-btn' type='submit' name='submit' value='Cast Ballot'></div></form><br><br>";
		}
	}
	
	
	//generate ballot
	
	//Javascript to make box in lower-right that counts how many votes you have left
	echo "\n<script>
	function refreshLeft() {
		var maxVotes = ". $govote['cand_num'] . ";
		var currVotes = 0;\n";
	$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']) . " ORDER BY `lastname`,`firstname`");
	while ($cand = mysqli_fetch_array($cands))
	{
		echo "if (document.getElementById('cand" . $cand['id'] . "').checked == true) currVotes++;\n";
	}		
	
echo "\nif ((maxVotes - currVotes) == 0) document.getElementById('votesremainingcount').innerHTML = '<span style=\'color:#090;\'>0</span>';
	else if ((maxVotes - currVotes) < 0) document.getElementById('votesremainingcount').innerHTML = '<span style=\'color:#f00;\'>' + (maxVotes - currVotes) + '</span>';
	else document.getElementById('votesremainingcount').innerHTML = (maxVotes - currVotes);
	}
	</script>
	";
	
	//little box in lower right showing how many votes you have left
	echo "<div id='votesremaining'>Votes Left<br><div align='center'><b><span id='votesremainingcount'>";
	$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']) . " ORDER BY `lastname`,`firstname`");
	$voted = 0;
	while ($cand = mysqli_fetch_array($cands))
	{
		if ($_REQUEST['cand' . $cand['id']] == 'Y') $voted++;
	}		
	if (($govote['cand_num'] - $voted) == 0) echo "<span style='color:#090;'>" . ($govote['cand_num'] - $voted) . "</span>";
	else if (($govote['cand_num'] - $voted) < 0) echo "<span style='color:#f00;'>" . ($govote['cand_num'] - $voted) . "</span>";
	else echo ($govote['cand_num'] - $voted);
	echo "</span></b></div></div>";

	//ballot form
	echo "<form class='ballot' action='index.php' method='post'><b>Vote for no more than " . $govote['cand_num'] . " candidates</b><br><br>";
	echo "<input type='hidden' name='vote' value='" . $_REQUEST['vote'] . "'>";
	
	//list of candidates
	echo "<table>";
	$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['vote']) . " ORDER BY `lastname`,`firstname`");
	while ($cand = mysqli_fetch_array($cands))
	{
		echo "<tr><td class='candidate'>" .
			"<input type='checkbox' class='checkbox' name='cand" . $cand['id'] . "' id='cand" . $cand['id'] . "' onchange='refreshLeft()' value='Y'>";
		echo "<label class='label' for='cand" . $cand['id'] . "'>";
		echo $cand['firstname'] . " " . $cand['lastname'] . "</label></td></tr>";
	}
	echo "</table><br><br><input class='big-btn' type='submit' name='submit' value='Review Ballot'></form><br><br>";
	exit;
}

echo "<b>Change Password</b><br><br>"; // change password link
echo "<a href='index.php?mode=changepw'>Change your password</a><br><br>";

echo "<b>Open Elections</b><br><br>"; //display list of open elections where people can vote
$ues = mysqli_query($_SERVER['con'], "SELECT * FROM `election_elections` WHERE `status` = 'O' ORDER BY `date`");
if (mysqli_num_rows($ues) == 0) echo "No open elections at this time.<br><br>";
else 
{
	echo "<table>";
	while ($ue = mysqli_fetch_array($ues))
	{
		$secretkey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT `seed` FROM `election_randomseed` WHERE `key` = '" . mrs($ue['id']) . "'"));
		echo "<tr><td valign='top'>" . $ue['name'] . "</td><td valign='top'>" . nicedate($ue['date']) . "</td><td valign='top'>Choose " . $ue['cand_num'] . "</td><td>";
		if (!$checkedin = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_checkin` WHERE `userid` = " . $_SESSION['id'] . " AND `status` = 'C'"))) echo " (you need to check in with the Credentials Committee and then you can vote [<a href='index.php'>Refresh this page</a>])";
		else if (!$ballot = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `alias` = '" . mrs(crypt(md5(strtolower($_SESSION['alias']) . $secretkey['seed']),md5(strtolower($_SESSION['email'])))) . "' AND `electionid` = " . mrs($ue['id'])))) 
		{
			echo "<big><b><a href='index.php?vote=" . $ue['id'] . "'>VOTE</a></b></big>";
		}
		else 
		{
			$ballotnum = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT `id` FROM `election_ballots` WHERE `alias` LIKE '" . mrs(crypt(md5(strtolower($_SESSION['alias']) . $secretkey['seed']),md5(strtolower($_SESSION['email'])))) . "'"));
			echo " (You have voted &ndash; ballot no. " . $ballotnum['id'] . ")";
		}
		echo "</td></tr>";
	}
	echo "</table><br><br>";
}

echo "<b>Upcoming Elections</b><br><br>"; //display list of upcoming elections where people can view candidates
$ues = mysqli_query($_SERVER['con'], "SELECT * FROM `election_elections` WHERE `status` = '' ORDER BY `date`");
if (mysqli_num_rows($ues) == 0) echo "No upcoming elections at this time.<br><br>";
else 
{
	echo "<table>";
	while ($ue = mysqli_fetch_array($ues))
	{
		echo "<tr><td valign='top'>" . $ue['name'] . "</td><td valign='top'>" . nicedate($ue['date']) . "</td><td valign='top'>Choose " . $ue['cand_num'] . "</td><td>";
		$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . $ue['id'] . " ORDER BY `lastname`,`firstname`");
		unset($candlist);
		while ($cand = mysqli_fetch_array($cands)) $candlist[] = $cand['firstname'] . " " . $cand['lastname'];
		echo implode("<br>",$candlist);
		echo "<br><i>(Additional candidates may 
		      <br>be nominated from the floor)</i>";
		echo "</td></tr>";
	}
	echo "</table>";
}

echo "<b>Closed Elections</b><br><br>"; //display list of closed elections so people can see the results
$ues = mysqli_query($_SERVER['con'], "SELECT * FROM `election_elections` WHERE `status` = 'C' ORDER BY `date`");
if (mysqli_num_rows($ues) == 0) echo "No completed elections at this time.<br><br>";
else 
{
	echo "<table>";
	while ($ue = mysqli_fetch_array($ues))
	{
		$secretkey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT `seed` FROM `election_randomseed` WHERE `key` = '" . mrs($ue['id']) . "'"));
		echo "<tr><td valign='top'>" . $ue['name'] . "</td><td valign='top'>" . nicedate($ue['date']) . "</td><td valign='top'>Choose " . $ue['cand_num'] . "</td><td>[<a href='index.php?viewres=" . $ue['id'] . "#results'>View Results</a>]";
		//if they voted, tell them their ballot number -- unless they are chairman displaying full results on screen
		if (($_SESSION['is_admin'] != 'Y') and ($ballotnum = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($ue['id']) . " AND `alias` = '" . mrs(crypt(md5(strtolower($_SESSION['alias']) . $secretkey['seed']),md5(strtolower($_SESSION['email'])))) . "'")))) echo " (You voted: Ballot number " . $ballotnum['id'] . ")";

		echo "</td></tr>";
	}
	echo "</table><br><br>";
}

if ($results = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` WHERE `status` = 'C' AND `id` = " . mrs($_REQUEST['viewres']))))
{
	//report election results
	echo "<a name='results'></a><br>";
	$ballots = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($_REQUEST['viewres']));
	while ($ballot = mysqli_fetch_array($ballots))
	{
		$candids = explode(",",$ballot['ballot']);
		foreach ($candids as $candvote)
		{
			$candvotes[$candvote]++;
		}
	}
	arsort($candvotes);
	echo "<big><b>Election Results for: " . $results['name'] . ", " . nicedate($results['date']) . "</b></big><br><br>";
	echo "<table style='border:3px double #000;'><tr style='background:#000;color:#fff;'><th style='padding:10px;'>Rank</th><th style='padding:10px;'>Candidate</th><th style='padding:10px;'>Votes</th></tr>";
	$num = 1;
	foreach ($candvotes as $cand=>$votes)
	{
		if ($odd) $odd = false; //oscillate shading of rows
		else $odd = true;
		$candinfo = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `id` = " . mrs($cand)));
		if ($odd) echo "<tr style='background:#eee;'><td style='padding:10px;'>";
		else echo "<tr><td style='padding:10px;'>";
		if ($votes == $currvotes) echo $currrank;
		else 
		{		//only update the rank when the vote total is different
			echo $num;
			$currrank = $num;
			$currvotes = $votes;
		}
		echo "</td><td style='padding:10px;'>" . $candinfo['firstname'] . " " . $candinfo['lastname'] . "</td><td>" . $votes . "</td></tr>";
		$num++;		
	}
	echo "</table><br><br>";
	echo "<b>Individual Ballots</b><br><br>";
	echo "<table style='border:3px double #000;width:100%'><tr style='background:#000;color:#fff'><th>Ballot No.</th><th>Encrypted Key</th><th>Candidates Voted For</th></tr>";
	$ballots = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($_REQUEST['viewres']) . " ORDER BY `id`");
	$secretkey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT `seed` FROM `election_randomseed` WHERE `key` = '" . mrs($_REQUEST['viewres']) . "'"));
	while ($ballot = mysqli_fetch_array($ballots))
	{
		if ($odd) $odd = false;  //oscillate shading of rows
		else $odd = true;
		echo "<tr";
		if ((crypt(md5(strtolower($_SESSION['alias']) . $secretkey['seed']),md5(strtolower($_SESSION['email']))) == $ballot['alias']) and ($_SESSION['is_admin'] != 'Y')) echo " style='background:#ff0;'"; // highlight for individuals but not chairman displaying results
		else if ($odd) echo " style='background:#eee;'";
		echo "><td valign='top' style='padding:5px;'>Ballot " . $ballot['id'] . "</td><td valign='top' style='padding:5px;'>" . $ballot['alias'] . "</td><td valign='top' style='padding:5px;'>";
		$candids = explode(",",$ballot['ballot']);
		unset($candnames);
		foreach ($candids as $candvote)
		{
			$cand = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `id` = " . mrs($candvote)));
			$candnames[] = $cand['firstname'] . " " . $cand['lastname'];
		}
		echo implode(", ",$candnames);
		echo "</td></tr>";
	}
	echo "</table><br><br>";
	echo "<b>Voter Turnout</b><br><br>";
	echo "<table style='border:3px double #000;'><tr style='background:#000;color:#fff'><th>Name</th><th>Key</th><th>Ballot Status</th></tr>";
	$voters = mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` ORDER BY `lastname`,`firstname`");
	while ($voter = mysqli_fetch_array($voters))
	{
		if ($odd) $odd = false; //oscillate shading of rows
		else $odd = true;
		if ($odd) echo "<tr>";
		else echo "<tr style='background:#eee;'>";
		echo "<td>" . $voter['firstname'] . " " . $voter['lastname'] . "</td><td>" . $voter['alias'] . "</td><td>";
		if ($ballot = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `alias` LIKE '" . mrs(crypt(md5(strtolower($voter['alias']) . $secretkey['seed']),md5(strtolower($voter['email'])))) . "' AND `electionid` = " . mrs($_REQUEST['viewres'])))) echo "<span style='color:#090;'><b>VOTED</b></span>";
		else 
		{
			if ($checkin = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_checkin` WHERE `userid` = " . $voter['id'] . " AND `status` = 'C'"))) echo "<span style='color:#900;'><b>Did not vote</b></span>";
			else echo "Not present";
		}
		echo "</td></tr>";
	}
	echo "</table><br><br>";
	
}

echo "[<a href='index.php'>Home/Refresh page</a>]&nbsp;&nbsp;&nbsp;&nbsp;[<a href='index.php?logout=Y'>Logout</a>]";
