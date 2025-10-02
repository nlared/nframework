<?php
require_once '../common2.php';


$codes=[

    [
        'title'=>'inputDate',
        'code'=>'',
    ],
    [
        'title'=>'InputDate',
        'code'=>"new inputDate(['name'=>'date1']);",
    ],
    [
        'title'=>'InputDate Calendar',
        'code'=>"new inputDate(['name'=>'date2','type'=>'calendarpicker']);",
    ]
    ,
    [
        'title'=>'InputDateTime Calendar',
        'code'=>"new inputDateTime(['name'=>'datetime1']);",
    ],
   /*
    [
        'title'=>'InputTime',
        'code'=>"new inputTime(['name'=>'time1']);",
    ]*/
];

foreach ($codes as $code) {
    if ($code['code']!='') {
       $tmp=eval('return '.$code['code']);
        $result.='
		<div class="row">
			<div class="cell"><h4>'.$code['title'].'</h4></div>
		</div>
		<div class="row">
			<div class="cell-sm">'.$tmp.'</div>
			<div class="cell-sm">'.str_replace("\r", '<br>', htmlspecialchars($code['code'])).'</div>
		</div>';
    } else {
        $result.='
		<div class="row">
			<div class="cell"><h3><br>'.$code['title'].'</h3></div>
		</div>';
    }
}
echo '<div class="container">
<div class="grid p-5">'.$result.'</div></div>';