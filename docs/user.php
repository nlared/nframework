<?

require_once '../common2.php';

if($user->in('developers')){ //user in developer group list
	echo "You are a developer";
}else{
	echo "You are't a developer";
}


if($user->can('developer')){ //$user->permissions 
	echo "You are a developer";
}else{
	echo "You are't a developer";
}

?>
<pre><code class="html">
<?=tocode(__file__) ?>
</code></pre>
<h4>User functions</h4>
<pre><code class="html">

</code></pre>