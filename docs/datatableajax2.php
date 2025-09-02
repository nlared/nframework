<?php
require 'common.php';

$datatable=new Table();
$datatable->Ajax([
    'id'=>'testid',
    'db'=>'nframework',
    'collection'=>'exampledata',
    'header'=>'<th>Session id</th><th>Text</th><th>Number</th><th>Date</th><th>Checkbox</th>',
    'columns'=>[
        '_id','text','number','date','checkbox'
    ]
]);

echo $datatable;
?>
<pre><code class="html">
<?=tocode(__file__) ?>
</code></pre>