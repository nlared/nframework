<?
require '../common2.php';
$developermode=true;

$sdata=$m->{$config['sitedb']}->sessions->findOne(['_id'=>$_GET['_id']]);
$tmp=$sdata['data']->getData();
$tmp=substr($tmp,3);
$tmp=unserialize($tmp);

?>
<div class="container">
	<pre><?print_r($tmp)?></pre>	
</div>