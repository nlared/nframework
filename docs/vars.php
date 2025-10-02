<?php
require_once '../linkstree.php';
?>
<div class="container-fluid">

	<h1><strong>$pecial vars</strong></h1>
		<h4>$xframe</h4>
		The X-Frame-Options HTTP response header can be used to indicate whether or not a browser 
		should be allowed to render a page in a &amp &lt;frame&gt;, &lt;iframe&gt;, &lt;embed&gt; or &lt;object&gt;.<br>
		Sites can use this to avoid clickjacking attacks, by ensuring that their content is not embedded into other sites.
		
		<br><br>The values can be:
		<li>DENY(default)</li>
		<li>sameorigin </li>
		<li>An array of domains for use</li>
		<li>remove(to disable)</li>
		<h4>$noobfuscate</h4>
		Disable the javascript objustation for develoment purposes.
		
		
</div>

