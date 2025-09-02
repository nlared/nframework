<?php
require_once '../common2.php';
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
	$toupper=new inputText(['id'=>'toupperid']);
	$javas->addjs('
		document.getElementById("toupperid").addEventListener("keypress", forceKeyPressUppercase, false);
	');
    $javas->addjs('alert("on resize");', 'resize');
	$javas->addjs('alert("on scroll");', 'scroll');
	$javas->addjs('alert("on ready");', 'ready');

?>

<div class="container-fluid">
	<h1><strong>$javas</strong></h1>
		Is a php object that collect the code to use at page to show with predefinded functions to use
	<?=$toupper?>
	<h4>Add code to onload function</h4>
	$javas->addjs('
	document.getElementById("toupperid").addEventListener("keypress",forceKeyPressUppercase, false);
	');</br>
	
	<h4>Add code to on resize function</h4>
	$javas->addjs('alert("on resize");','resize');</br>
	<h4>Add code to on scroll function</h4>
	$javas->addjs('alert("on ready");','ready');</br>
	$javas->addjs('alert("on scroll");','scroll');</br>


	$nframework['css']['000']='https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.min.css';<br>
	$nframework['css']['005rte']='//cdnjs.cloudflare.com/ajax/libs/jquery-te/1.4.0/jquery-te.min.css';<br>
	$nframework['css']['010']='//cdn.nlared.com/mixcss.php?f='.implode(';',$mixcss[0]);<br>
	$nframework['css']['050']=$config['metropath'].'/css/metro-all.min.css';<br>
	$nframework['css']['060']='//cdn.nlared.com/datatables.net-responsive-dt/css/responsive.dataTables.min.css';<br>
	$nframework['css']['100']='//cdn.nlared.com/mixcss.php?f='.implode(';',$mixcss[1]);<br>
	$nframework['css']['101']=$config['scheme'];<br>
	
	$nframework['js']<br>

</div>

