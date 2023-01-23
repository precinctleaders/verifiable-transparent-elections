<?php

//Finalize the election at the end of convention.

$uri = explode("/",$_SERVER['PHP_SELF']);
if ($uri[(count($uri)-1)] != 'index.php') die('Hacking attempt.');   //this page can only be called from the one master web page and will otherwise stop
if (!in_array($_SESSION['is_admin'], array("Y"))) die('Hacking attempt.'); //only chair can access this page

if ($_REQUEST['goforit'] != 'Y')
{
	//display big on screen (for the audience) what exactly is about to happen
	?>
	<table align='center' style='border:10px #600; border-style:ridge; border-radius:15px; width:80%; min-height:80%; box-shadow: 12px 12px 12px #999;'><tr><td style='border-radius:5px; background-image:linear-gradient(#d90, #ff0);color:#000;padding:20px;'><big><big><b>You are about to FINALIZE the election results.
	<br><br>This will:<br>
	<ul>
	<li><u>Destroy all ballot encryption keys.</u> This will <u>permanently</u> anonymize all ballots, so make certain everyone has verified their ballots to their satisfaction <u>first</u>.<br><br>
	<li>Generate a <u>final</u> canvassing report that everybody can view, download, and print&mdash;including results, database dump, and source code.
	</ul><div align='center'><big>ARE YOU READY?</big><br><br><br><a style='text-decoration:none' href='index.php?do=closeprogram&goforit=Y'><span style='background:#600;color:#FFF;padding:30px;border: 5px double #000; border-radius:50%'>DO IT!</span></a></b></big></big><br><br><br></td></tr></table><br><br>
	
	<?
	exit;
}

//Here we go

require('pdf.php');	// PDF generation library

$outfilename = date("Y-m-d") . "convention.pdf";
if (file_exists($outfilename))
{
	//if the report has already been generated, do not generate it again
	echo "<table align='center' style='border:10px #600; border-style:ridge; border-radius:15px; width:80%; min-height:80%; box-shadow: 12px 12px 12px #999;'><tr><td style='border-radius:5px; background-image:linear-gradient(#d90, #ff0);color:#000;padding:20px;'><div align='center' valign='middle'>
<big><big><big><big><big><b>The report was already generated.<br><br>It's available here:<br><br><a style='color:#300' href='https://" . $_SERVER['HTTP_HOST'] . "/" . $outfilename . "' target='_blank'>" . $_SERVER['HTTP_HOST'] . "/" . $outfilename . "</a>
</b></big></big></big></big></big></td></tr></table><br><br>";
	exit;
}

$pdf=new PDF('P');
$pdf->AliasNbPages();
$pdf->SetTopMargin(30);	
$pdf->SetAutoPageBreak(1,15);
$pdf->AddPage();
//cover page
$pdf->SetFont('Arial','B',20);
$output = "\n\n" . $_SERVER['title'] . "\n" . date("F j, Y") . "\n";
$pdf->Multicell(0,9,$output,0,"C");
$pdf->SetFont('Arial','',12);
$output = "Report generated " . date("F j, Y g:i:s a") . "\nChairman: " . $_SESSION['firstname'] . " " . $_SESSION['lastname'] . "\n";
$pdf->Multicell(0,8,$output,0,"C");
$pdf->SetFont('Arial','B',16);
$output = "Attendance Report\n\n";
$pdf->Multicell(0,8,$output,0,"L");
$pdf->SetFont('Arial','',12);
//attendance report
unset($output);
$voters = mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters` ORDER BY `lastname`,`firstname`");
while ($voter = mysqli_fetch_array($voters))
{
	if ($present = mysqli_fetch_array(mysqli_query($_SERVER['con'], "SELECT * FROM `election_checkin` WHERE `userid` = " . mrs($voter['id'])))) $output .= "<b>" .
	$voter['firstname'] . " " . $voter['lastname'] . "..........................Present</b><br>";
	else $output .= $voter['firstname'] . " " . $voter['lastname'] . "..........................Not Present<br>";
}
$output .= "<br><br>";
$pdf->WriteHTML($output);
$elections = mysqli_query($_SERVER['con'],"SELECT * FROM `election_elections` ORDER BY `id`");
while ($election = mysqli_fetch_array($elections))
{
	//report for each election
	$pdf->SetFont('Arial','',20);
	$output = "<b>Election " . $election['id'] . ": " . $election['name'] . "</b> (Choose " . $election['cand_num'] . " candidate";
	if ($election['cand_num'] != '1') $output .= "s";
	$output .= ")<br><br>";
	$pdf->WriteHTML($output);
	$pdf->SetFont('Arial','',12);
	//list of candidates
	unset($cands);
	unset($candvotes);
	$candidates = mysqli_query($_SERVER['con'], "SELECT * FROM `election_cands` WHERE `electionid` = " . mrs($election['id']) . " ORDER BY `lastname`,`firstname`");
	while ($candidate = mysqli_fetch_array($candidates)) $cands[] = $candidate['firstname'] . " " . $candidate['lastname'];
	$output = "<b>Candidates:</b> " . implode(", ", $cands) . "<br><br>";
	$pdf->WriteHTML($output);
	//results
	$pdf->SetFont('Arial','B',16);
	$output = "Results<br><br>";
	$pdf->WriteHTML($output);
	$pdf->SetFont('Arial','',12);
	$ballots = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($election['id']));	
	while ($ballot = mysqli_fetch_array($ballots))
	{
		if ($ballot['ballot'] == '') continue; //extra protection against blank ballot
		$candids = explode(",",$ballot['ballot']);
		foreach ($candids as $candvote)
		{
			$candvotes[$candvote]++;
		}
	}
	arsort($candvotes);		//sort by most votes to least
	$output = "(Note: Ties are not displayed in any particular order within a rank.)<br><br>";
	$num = 1;
	$currvotes = 0;
	foreach ($candvotes as $cand=>$votes)
	{
		$candinfo = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `id` = " . mrs($cand)));
		if ($votes == $currvotes) $output .= "Rank " . $currrank . "..........................";
		else 
		{		//only update the rank when the vote total is different
			$output .= "Rank " . $num . "..........................";
			$currrank = $num;
			$currvotes = $votes;
		}
		$output .= $candinfo['firstname'] . " " . $candinfo['lastname'] . ".........................." . $votes . " votes<br>";
		$num++;		
	}
	$output .= "<br>";
	$pdf->WriteHTML($output);
	$pdf->SetFont('Arial','B',16);
	//report of who did and did not vote
	$output = "Voter Canvass<br><br>";
	$pdf->WriteHTML($output);
	$pdf->SetFont('Arial','',12);
	unset($voterlist);
	$didvote=0;
	$didntvote=0;
	$voters = mysqli_query($_SERVER['con'],"SELECT * FROM `election_voters` ORDER BY `lastname`,`firstname`");
	while ($voter = mysqli_fetch_array($voters))
	{
		
		if ($ballot = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots_cast` WHERE `voterid` = " . mrs($voter['id']) . " AND `electionid` = " . mrs($election['id']))))
		{
			$voterlist[] =  $voter['firstname'] . " " . $voter['lastname'] . ": Voted";
			$didvote++;
		}
		else 
		{
			if ($checkin = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_checkin` WHERE `userid` = " . $voter['id']))) 
			{
				//only count non-voters if they checked in and are present
				$voterlist[] = "<b>" . $voter['firstname'] . " " . $voter['lastname'] . ": Did not vote</b>";
				$didntvote++;
			}
		}
	}
	$output = implode(", ", $voterlist);
	$output .= "<br><br>" . $didvote . " ballots cast; " . $didntvote . " voters abstained<br><br>";
	$pdf->WriteHTML($output);	
	//list of ballots
	$pdf->SetFont('Arial','B',16);
	$output = "Individual Ballots<br><br>";
	$pdf->WriteHTML($output);
	$pdf->SetFont('Arial','',10);
	$output = '';
	$ballots = mysqli_query($_SERVER['con'],"SELECT * FROM `election_ballots` WHERE `electionid` = " . mrs($election['id']) . " ORDER BY `id`");
	while ($ballot = mysqli_fetch_array($ballots))
	{
		$output .= "<b>Ballot " . $ballot['id'] . "</b>: ";
		$candids = explode(",",$ballot['ballot']);
		unset($candnames);
		foreach ($candids as $candvote)
		{
			$cand = mysqli_fetch_array(mysqli_query($_SERVER['con'],"SELECT * FROM `election_cands` WHERE `id` = " . mrs($candvote)));
			$candnames[] = $cand['firstname'] . " " . $cand['lastname'];
		}
		$output .= implode(", ",$candnames) . "<br><br>";
	}
	
	$pdf->WriteHTML($output);
}
$pdf->AddPage();
$pdf->SetFont('Arial','B',20);
$pdf->WriteHTML("Database Contents<br><br>"); //output the entire database

//but first, destroy the election keys forever
$ekeys = mysqli_query($_SERVER['con'],"SELECT * FROM `election_randomseed`");
while ($ekey = mysqli_fetch_array($ekeys))
{
	if ($ekey['key'] == 'Global') continue; //don't delete the key for user passwords
	$del = mysqli_query($_SERVER['con'],"UPDATE `election_randomseed` SET `seed` = '(deleted)' WHERE `key` = '" . mrs($ekey['key']) . "'");
}

$tables = mysqli_query($_SERVER['con'],"SHOW TABLES");
while ($table = mysqli_fetch_row($tables))
{
	$pdf->SetFont('Arial','',14);
	$output = "<b>Table Name: " . $table[0] . "</b><br><br>";
	$cols = mysqli_query($_SERVER['con'],"SHOW FIELDS FROM `" . mrs($table[0]) . "`");
	$output .= "<b>Columns:</b> ";
	unset($columns);
	unset($colname);
	unset($coltype);
	while ($col = mysqli_fetch_row($cols)) 
	{
		$columns[] = $col[0] . " (" . $col[1] . ")";
		$colname[] = $col[0];
		$coltype[] = $col[1];
	}
	$output .= implode(", ", $columns) . "<br><br><b>Data:</b><br><br>";
	$pdf->WriteHTML($output);
	$output = '';
	$pdf->SetFont('Courier','',8);
	$data = mysqli_query($_SERVER['con'], "SELECT * FROM `" . mrs($table[0]) . "`");
	while ($datum = mysqli_fetch_array($data))
	{
		unset($cells);
		foreach ($datum as $key => $var)
		{
			if (!is_numeric($key)) continue; //go by column ID
			if (substr($coltype[$key],0,3) == 'int') $cells[] =  str_pad($var,5," ",STR_PAD_LEFT);
			else if (substr($coltype[$key],0,8) == 'datetime') $cells[] =  str_pad($var,19," ",STR_PAD_LEFT);
			else if (substr($coltype[$key],0,4) == 'date') $cells[] =  str_pad($var,10," ",STR_PAD_LEFT);
			else if ((substr($coltype[$key],0,4) == 'varc') or (substr($coltype[$key],0,4) == 'text'))
			{
				//figure out how wide this column needs to be--pull every record and find the longest
				if (!$testl = mysqli_query($_SERVER['con'],"SELECT `" . mrs($colname[$key]) . "` FROM `" . mrs($table[0]) . "`")) die(mysqli_error($_SERVER['con']));
				$maxlen = 0;
				while ($test2 = mysqli_fetch_array($testl)) 
				{
					if (strlen($test2[$colname[$key]]) > $maxlen) $maxlen = strlen($test2[$colname[$key]]);
				}
				$cells[] = str_pad($var,$maxlen," ",STR_PAD_LEFT);
			}
		}
		$output .= implode(", ", $cells) . "<br>";
	}
	$output .= "<br><br>";
	$pdf->WriteHTML($output);
}
$pdf->AddPage();
$pdf->SetFont('Arial','B',20);
$pdf->WriteHTML("Code Files<br><br>"); //output the folder of code files
$dir = scandir(dirname(__FILE__));
foreach ($dir as $file)
{
	if ($file == '.') continue;
	if ($file == '..') continue;
	if ($file == $outfilename) continue; //this is the PDF being generated
	//handle filenames with periods
	$pieces = explode(".",$file);
	if (count($pieces) == 1) continue; //folder not file
	if (!$currfile = fopen($file,'r')) redalert("Error: cannot open file " . $file);
	$txt=fread($currfile,filesize($file));
	fclose($currfile);	
	$pdf->SetFont('Arial','B',16);
	$pdf->WriteHTML($file . "<br><br>");
	$pdf->SetFont('Courier','',6);
	$pdf->MultiCell(0,3,$txt);
	$pdf->WriteHTML("<br><br>");
}
$pdf->Output($outfilename,'F');	//file output
$pdf->Close();

//output the link to the PDF

echo "<table align='center' style='border:10px #600; border-style:ridge; border-radius:15px; width:80%; min-height:80%; box-shadow: 12px 12px 12px #999;'><tr><td style='border-radius:5px; background-image:linear-gradient(#d90, #ff0);color:#000;padding:20px;'><div align='center' valign='middle'>
<big><big><big><big><big><b>Success!<br><br>The election report is available here:<br><br><a style='color:#300' href='https://" . $_SERVER['HTTP_HOST'] . "/" . $outfilename . "' target='_blank'>" . $_SERVER['HTTP_HOST'] . "/" . $outfilename . "</a>
</b></big></big></big></big></big></td></tr></table><br><br>";
	
	

?>