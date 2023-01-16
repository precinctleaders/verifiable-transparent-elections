<?php

//Administrative tools. The master administrator (ie the chair) has full access.
//Credentials committee members can only edit the voter roll and check voters in.
//The $_SESSION['is_admin'] variable is set to Y for master admin and X for CC members.

$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.');
if (!in_array($_SESSION['is_admin'], array("Y","X"))) die('Hacking attempt.');
echo "<div style='background:#efe;padding:10px;border:1px solid #000;'><b>Administrative Tools</b><br><br>";

//only master admin can do these

if ($_SESSION['is_admin'] =='Y')
{
	if ($surr = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `id` = " . mrs($_REQUEST['surrender']))))
	{
		//process surrendering of chair
		$make = mysqli_query($_SERVER['con'], "UPDATE `election_users` SET `is_admin` = 'Y' WHERE `id` = " . mrs($_REQUEST['surrender']));
		$unmake = mysqli_query($_SERVER['con'], "UPDATE `election_users` SET `is_admin` = '' WHERE `id` = " . mrs($_SESSION['id']));
		$_SESSION['is_admin'] = '';
		redalert("You have passed the torch. Now go sit down.");
		exit;
	}
	
	if ($chgkey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_randomseed` WHERE `key` = '" . mrs($_REQUEST['newkey']) . "'")))
	{
		//change random secret key that encrypts aliases
		$newkey = random_str();
		$chg = mysqli_query($_SERVER['con'],"UPDATE `election_randomseed` SET `seed` = '" . mrs($newkey) . "' WHERE `key` = '" . mrs($_REQUEST['newkey']) . "'");
	}
	
	if ($upd = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` WHERE `id` = " . mrs($_REQUEST['open']) . " AND `status` = ''")))
	{
		//open an election
		$updR = mysqli_query($_SERVER['con'],"UPDATE `election_elections` SET `status` = 'O' WHERE `id` = " . mrs($_REQUEST['open']));
	}

	if ($upd = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` WHERE `id` = " . mrs($_REQUEST['close']) . " AND `status` = 'O'")))
	{
		//close an election
		$updR = mysqli_query($_SERVER['con'],"UPDATE `election_elections` SET `status` = 'C' WHERE `id` = " . mrs($_REQUEST['close']));
	}

	if ($del = mysqli_query($_SERVER['con'], "DELETE FROM `election_elections` WHERE `status` = '' AND `id` = " . mrs($_GET['del'])))
	{
		//delete an election that was never open -- will not work once and election is open or closed
		redalert("Election deleted successfully.");
	}

	if ($_REQUEST['mode'] == 'createelection')
	{
		//create an election
		if ($_POST['submit'] == 'Create Election')
		{
			if (!$create = mysqli_query($_SERVER['con'], "INSERT INTO `election_elections` (`name`,`date`,`cand_num`) VALUES ('" . mrs($_REQUEST['name']) . "' , '" . mrs($_REQUEST['date']) . "', " . mrs($_REQUEST['cand_num']) . ")")) redalert("Failed to create election.");
			else
			{
				//election created successfully, generate random secret alias key
				$randomness = random_str();
				$electionnum = mysqli_insert_id($_SERVER['con']);
				$newkey = mysqli_query($_SERVER['con'],"INSERT INTO `election_randomseed` (`key`,`seed`) VALUES ('" . mrs($electionnum) . "' , '" . mrs($randomness) . "')");
				redalert("Election created successfully. <a href='index.php'>Return to menu</a>");
				exit;
			}
		}
		echo "<form action='index.php' method='post'><input type='hidden' name='mode' value='createelection'>
		<table><tr><td>Election Name: </td><td><input type='text' name='name'></td></tr>
		<tr><td>Election Date YYYY-MM-DD: </td><td><input type='text' name='date'></td></tr>
		<tr><td>Number of candidates to vote for: </td><td><input type='text' name='cand_num'></td></tr>
		<tr><td><input type='submit' name='submit' value='Create Election'></td></tr></table>
		</form>
		<br><a href='index.php'>Return to main menu</a>";
		exit;
	}
}

if ($_REQUEST['mode'] == 'voters')
{
	if ($_REQUEST['submit'] == 'Import Voters')
	{
		$voters = explode("\n",$_REQUEST['voters']);
		foreach ($voters as $voter)
		{
			//Generate random alias
			$alias = random_str(10);
			while ($dupe = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `alias` = '" . $alias . "'")))
			{
				//retry until unique combination
				$alias = random_str(10);
			}
			$names = explode(",", $voter);
			$now = date("Y-m-d H:i:s");
			$insq = "INSERT INTO `election_users` (`firstname`,`lastname`,`email`, `created`,`alias`) VALUES ('" . mrs(trim(str_replace('"','',$names[0]))) . "' , '" . mrs(trim(str_replace('"','',$names[1]))) . "' , '" . mrs(trim(str_replace('"','',$names[2]))) . "', '" . mrs($now) . "', '" . mrs($alias) . "' )";
			if (!$ins = mysqli_query($_SERVER['con'],$insq)) echo "Voter already exists: " . trim(str_replace('"','',$names[0])) . " " .trim(str_replace('"','',$names[1])) . " &lt;" . trim(str_replace('"','',$names[2])) . "&gt;<br>";
			else 
			{
				echo "Added " . trim(str_replace('"','',$names[0])) . " " .trim(str_replace('"','',$names[1])) . " &lt;" .trim(str_replace('"','',$names[2])) . "&gt;<br>";
				$count++;
			}			
		}
		echo $count . " voters added successfully.<br><a href='index.php'>Home</a>";
		exit;
	}
	
	//import eligible voters
	echo "<b>Import Voters</b> &ndash; [<a href='index.php'>Back to main menu</a>]<br><br>";
	echo "Paste the contents of a CSV file to import eligible voters. Use this format: First Name, Last Name, Email<br><br>
	<form action='index.php' method='post'><input type='hidden' name='mode' value='voters'>
	<textarea name='voters' rows='10' cols='80'></textarea>
	<br><input type='submit' name='submit' value='Import Voters'>
	</form><br>";
	exit;
}

if ($_REQUEST['mode'] == 'checkin')
{
	$now = date("Y-m-d H:i:s"); //outputs current time in standard format, eg 2022-12-13 12:55:32
	echo "<a name='checkin'></a>";
	if ($tocheckin = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `id` = " . mrs($_REQUEST['tocheckin']))))
	{
		//execute checkin and generate temp password
		$temppw = rand(100000,999999);
		$temp_enc = crypt(md5(strtolower($temppw) . $_SERVER['randomness']),md5(strtolower($tocheckin['email'])));
		$upduser = mysqli_query($_SERVER['con'],"UPDATE `election_users` SET `tmp_password` = '" . mrs($temp_enc) . "' WHERE `id` = " . mrs($tocheckin['id']));
		$chkin = mysqli_query($_SERVER['con'],"INSERT INTO `election_checkin` (`userid`,`checkin_time`,`status`) VALUES ( " . mrs($tocheckin['id']) . " , '" . mrs($now) . "', 'C')");
		echo "<button id='printme' name='printme' value='Click to print check-in info' onclick = 'printcontent()'>Print check-in info for " . $tocheckin['firstname'] . " " . $tocheckin['lastname'] . "</button>";
		?>
		<script>
		function printcontent(){
			var restorepage=document.body.innerHTML;
			var printcontent="Welcome to our County Convention.<br><br>If you have a phone with a web browser,<br><br>Step 1: Go to<br>thewebsite.com/election/<br><br>Enter this as the User: <div align='center'><b><?php echo $tocheckin['email']; ?></b></div>And this password:<br><div align='center'><big><big><big><b><?php echo $temppw;?></b></big></big></big></div> and login.<br><br>Step 2: Change your <br>password immediately.<br><br>Step 3: Wait for the polls to open, refresh the <br>home page, and vote.<br><br>Step 4: Wait for the polls to close, refresh the <br>home page, and verify <br>the results.<br><br> If you do NOT have a phone, you can do all of this at a provided computer once voting opens.";
			document.body.innerHTML = printcontent;
			window.print();
			document.body.innerHTML = restorepage;
		}
		</script>
		<?
	}
		
	if ((is_numeric($_REQUEST['makeadmin'])) and ($_SESSION['is_admin'] == 'Y'))
	{
		//promote user to admin. Only master admin can do this.
		if ($make = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_users` WHERE `is_admin` = '' AND `id` = " . mrs($_REQUEST['makeadmin']))))
		{
			if ($promote = mysqli_query($_SERVER['con'],"UPDATE `election_users` SET `is_admin` = 'X' WHERE `id` = " . mrs($make['id']))) redalert($make['firstname'] . " " . $make['lastname'] . " promoted to Credentials Committee successfully.");
		}
	}
		
	if (is_numeric($_REQUEST['remove'])) 
	{
		if ($toremove = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_users` WHERE `is_admin` = '' AND `id` = " . mrs($_REQUEST['remove']))))
		{
		//delete a duplicate user
		
			if ($del = mysqli_query($_SERVER['con'],"DELETE FROM `election_users` WHERE `id` = " . mrs($toremove['id']))) redalert($toremove['firstname'] . " " . $toremove['lastname'] . " deleted successfully.");
		}
	}
	
	echo "<b>Check In Voters</b><br><br>";
	echo "<b>Instructions: </b> For a registered voter, click the \"check in \" link by their name. When the page reloads a button will appear to print their instructions.
	
	<br><br>That's it!
	
	<br><br>";	
	echo "<table>";
	$users = mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` ORDER BY `lastname`,`firstname`");
	while ($user = mysqli_fetch_array($users))
	{
		if ($odd) $odd = false;
		else $odd = true; //oscillate shading of rows
		if ($odd) echo "<tr style='background:#eee;'>";
		else echo "<tr style='background:#fff;'>";
		echo "<td>" . $user['firstname'] . "</td><td>" . $user['lastname'] . "</td><td>" . $user['email'] . "</td>";
		if ($chk = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_checkin` WHERE `userid` = " . mrs($user['id']) )))
		{
			echo "<td style='background:#efe;'><b>Checked in</b> <a href='index.php?mode=checkin&tocheckin=" . $user['id'] . "'>Reset temp login</a></td><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='index.php?mode=checkin&remove=" . $user['id'] . "'>Delete voter</a></td>";
		}
		else if ($user['tmp_password'] == '')
		{
			echo "<td>Registered. <a href='index.php?mode=checkin&tocheckin=" . $user['id'] . "'>Check in voter</a></td><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='index.php?mode=checkin&remove=" . $user['id'] . "'>Delete voter</a></td>";
		}
		if ($_SESSION['is_admin'] == 'Y')
		{
			//only master admin can make credentials committee members
			if ($user['is_admin'] != 'Y') echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='index.php?mode=checkin&makeadmin=" . $user['id'] . "'>Make C.C.</a></td>";
			else echo "<td><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Is C.C.</b></td>";
		}
		echo "</tr>";
	}
	echo "</table><br><br>";
}

if ($_SESSION['is_admin'] == 'Y')
{
	if ($viewcands = mysqli_query($_SERVER['con'], "SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['cands']) . " ORDER BY `lastname`,`firstname`"))
	{
		//tools for adding, editing, and deleting candidates
		if ($delcand = mysqli_query($_SERVER['con'], "DELETE FROM `election_cands` WHERE `id` = " . mrs($_REQUEST['delcand']))) redalert("Candidate deleted successfully.");
		if ($_REQUEST['submit'] == 'Update candidate')
		{
			if ($upd = mysqli_query($_SERVER['con'],"UPDATE `election_cands` SET `firstname` = '" . mrs($_REQUEST['firstname']) . "' , `lastname` = '" . mrs($_REQUEST['lastname']) . "' WHERE `id` = " . mrs($_REQUEST['candid']))) redalert("Candidate updated successfully.");
			else redalert("Error: Duplicate candidate name.");
		}
		if ($editcand = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_cands` WHERE `id` = " . mrs($_REQUEST['editcand'])))) 
		{
			//form to edit candidate's name
			echo "<form action='index.php' method='post'><b>Edit candidate's name</b>
			<br><br>First name: <input type='text' name='firstname' value='" . str_replace("'","&apos;",$editcand['firstname']) . "'> 
			<br><br>Last name: <input type='text' name='lastname' value='" . str_replace("'","&apos;",$editcand['lastname']) . "'>
			<br><br><input type='hidden' name='candid' value='" . mrs($_REQUEST['editcand']) . "'>
			<input type='hidden' name='cands' value='" . mrs($_REQUEST['cands']) . "'>
			<input type='submit' name='submit' value='Update candidate'></form><br><br>";
			echo "<a href='index.php?cands=" . mrs($_REQUEST['cands']) . "'>Return to candidate list</a>";
			exit;
		}
		if ($_POST['submit'] == 'Add candidates')
		{
			//add candidates. automatically removes leading and trailing spaces
			$cands = explode("\n",$_POST['addcands']);
			foreach ($cands as $cand)
			{
				$names = explode(",",$cand);
				if (!$ins = mysqli_query($_SERVER['con'],"INSERT INTO `election_cands` (`electionid`,`firstname`,`lastname`) VALUES (" . mrs($_REQUEST['cands']) . " , '" . mrs(trim($names[1])) . "' , '" . mrs(trim($names[0])) . "')")) echo "Error: Duplicate name on " . $names[1] . " " . $names[0] . "<br>";
				else echo $names[1] . " " . $names[0] . " added successfully.<br>";
			}
			echo "<br>";
		}
		$viewcands = mysqli_query($_SERVER['con'], "SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($_REQUEST['cands']) . " ORDER BY `lastname`,`firstname`");
		//form to view and edit candidates for an election
		if (mysqli_num_rows($viewcands) == 0) echo "<b>There are no candidates for this election yet.</b><br><br>";
		else 
		{
			echo "<table>";
			while ($viewcand = mysqli_fetch_array($viewcands)) echo "<tr><td>" . $viewcand['firstname'] . "</td><td>" . $viewcand['lastname'] . "</td><td><a href='index.php?cands=" . $_REQUEST['cands'] . "&editcand=" . $viewcand['id'] . "'>Edit</a></td><td><a href='index.php?cands=" . $_REQUEST['cands'] . "&delcand=" . $viewcand['id'] . "'>Delete</a></td></tr>";
			echo "</table><br><br>";
		}
		echo "Add candidates, one candidate per line, lastname comma firstname<br>
		<form action='index.php' method='post'><input type='hidden' name='cands' value='" . $_REQUEST['cands'] . "'><textarea name='addcands' rows='20' cols='80'></textarea>
		<br><input type='submit' name='submit' value='Add candidates'></form><br><a href='index.php'>Return to menu</a>";
		exit;	
	}
}	

//administrator menu
if ($_SESSION['is_admin'] == 'Y') echo "<a href='index.php?mode=createelection'>Create an election</a><br><br>";
echo "<a href='index.php?mode=voters'>Register voters</a><br><br>";
echo "<a href='index.php?mode=checkin'>Check in voters</a><br><br>";
$elections = mysqli_query($_SERVER['con'], "SELECT * FROM `election_elections` ORDER BY `date`");
if ((mysqli_num_rows($elections) > 0) and ($_SESSION['is_admin'] == 'Y'))
{
	echo "<b>Elections</b><br><br><table><tr><th>Name</th><th>Date</th><th>Candidates</th><th>VoteMax</th><th>Ballots Cast</th><th>Registered</th><th>Present</th><th>Status</th><th>Operations</th><th>Key (partial)</th></tr>";
	while ($election = mysqli_fetch_array($elections)) 
	{
		$ekey = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_randomseed` WHERE `key` = '" . mrs($election['id']) . "'"));
		echo "<tr><td><b>" . $election['name'] . "</b></td><td>" . nicedate($election['date']) . "</td>";
		$cands = mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($election['id']));
		echo "<td><a href='index.php?cands=" . $election['id'] . "'>" . mysqli_num_rows($cands) . " [edit]</a></td>";
		echo "<td>" . $election['cand_num'] . "</td>";
		$votecount = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($election['id']));
		echo "<td>" . mysqli_num_rows($votecount) . "</td>";
		$votercount = mysqli_query($_SERVER['con'],"SELECT * FROM `election_users`");
		echo "<td>" . mysqli_num_rows($votercount) . "</td>";
		$checkedin = (mysqli_query($_SERVER['con'],"SELECT DISTINCT `userid` FROM `election_checkin`"));
		echo "<td>" . mysqli_num_rows($checkedin) . "</td>";
		if ($election['status'] == '') echo "<td>New</td>";
		if ($election['status'] == 'O') echo "<td>Open</td>";
		if ($election['status'] == 'C') echo "<td>Closed</td>";
		if ($election['status'] == '') echo "<td><a href='index.php?open=" . $election['id'] . "'>Open election</a> <a href='index.php?del=" . $election['id'] . "'>Delete election</a> <a href='index.php?newkey=" . $election['id'] . "'>Generate new random key</a></td><td>" . substr($ekey['seed'],0,32) . "...</td>";
		if ($election['status'] == 'O') echo "<td><a href='index.php?close=" . $election['id'] . "'>Close election</a></td><td>" . substr($ekey['seed'],0,32) . "...</td>";
		if ($election['status'] == 'C') echo "<td><a href='index.php?viewres=" . $election['id'] . "#results'>View results</a></td><td>" . substr($ekey['seed'],0,32) . "...</td>";
		echo "</tr>";
	}
	echo "</table>";
}

if ($_SESSION['is_admin'] == 'Y')
{
	//form for chairman to surrender chair to someone else
	echo "<br><br><form action='index.php' method='post'><b>Surrender the chair?!</b> 
	<select name='surrender'><option value=''>No way!</option>";
	$users = mysqli_query($_SERVER['con'],"SELECT * FROM `election_users` WHERE `is_admin` != 'Y' ORDER BY `lastname`,`firstname`");
	while ($user = mysqli_fetch_array($users)) echo "<option value='" . $user['id'] . "'>" . $user['lastname'] . ", " . $user['firstname'] . "</option>";
	echo "</select> <input type='submit' name='submit' value='Don&apos;t click this accidentally'></form>";
}

echo "</div><br>";
