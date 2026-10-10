<?php
require '../common2.php';
$developermode=true;

$sdata=$m->{$config['sitedb']}->sessions->findOne(['_id'=>(string)($_GET['_id'] ?? '')]);
$tmp=$sdata ? $sdata['data']->getData() : '';
$tmp=substr($tmp,3);
$tmp=unserialize($tmp, ['allowed_classes' => false]);

?>
<div class="container">
	<pre><?php print_r($tmp)?></pre>	
</div>