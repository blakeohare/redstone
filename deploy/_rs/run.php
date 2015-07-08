<?
	echo "Red Stone test site.";
	echo '<br />';
	echo $_GET['rs_url'].'<br />';
	echo (intval($_GET['rs_latest']) == 1 ? 'YES' : 'NO');
?>