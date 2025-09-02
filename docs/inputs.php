<?php
$noobfuscate=true;
require_once '../common2.php';
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$codes=[
    [
        'code'=>"new inputText([
        	'name'=>'inputext1',
        	'caption'=>'inputtext',
        ]);",
    ],
    [
        'code'=>"new inputText(['name'=>'inputext2','caption'=>'InputText with value','value'=>'Some text']);",
    ],
    [
        'code'=>"new inputText(['name'=>'inputext3','caption'=>'InputText read only','readonly'=>true,'value'=>'Some text']);",
    ],
    [
        'code'=>"new inputText(['name'=>'inputext4','caption'=>'InputText placeholder','placeholder'=>'placeholder']);",
    ],
    
    [
        'code'=>"new inputText(['name'=>'inputext4','caption'=>'InputText prepend, append','prepend'=>'prepend','append'=>'append']);",
    ],
    
    [
        'code'=>"new inputText(['name'=>'inputext4','caption'=>'InputText prepend, append','prepend_options'=>'https://,http://','append_options'=>'.com,.net,.org']);",
    ],
    [
        'code'=>"new inputText(['name'=>'inputext5','caption'=>'InputText password','type'=>'password']);",
    ],
    [
        'code'=>"new inputText(['name'=>'inputext6','caption'=>'InputText disabled','disabled'=>true]);",
    
    ],
    [
        'code'=>"new inputText(['name'=>'inputext7','caption'=>'InputText Readonly','readonly'=>true]);",
    
    ],
    [
        'code'=>"new inputText(['name'=>'inputext8','caption'=>'InputText backreadonly','backreadonly'=>true]);",
    
    ],
    
    [
        'code'=>"new inputText(['name'=>'inputext9','caption'=>'InputText uppercase','uppercase'=>true]);",
    
    ],
    [
        'code'=>"new inputText(['name'=>'inputext10','caption'=>'InputText mask','mask'=>'___-___-____', 'mask_pattern'=>'\d']);",
    
    ],
    [
        'title'=>'inputNumber',
    ],
    [
        
        'code'=>"new inputNumber(['name'=>'number1','caption'=>'Simple']);",
    ],
    [
        'code'=>"new inputNumber(['name'=>'inpunumber2', 'caption'=>'With Value','value'=>33]);",
    ],
    [
        'code'=>"new inputNumber(['name'=>'inputnumber3','caption'=>'InputText min max step','min'=>-5,'max'=>30,'step'=>1]);",
    ],
    [
        'code'=>"new inputNumber(['name'=>'inputnumber4','caption'=>'InputNumber prepend, append','prepend'=>'prepend','append'=>'append']);",
    ],
    [
        'title'=>'TinyMCE',
    ],
    [
        
        'code'=>"new inputMCE([
        	'name'=>'MCE',
        	'mediadir'=>realpath( '../tmp').'/',
			'baseurl'=>'/tmp/',
			'id'=>'uniqueid',
			'upload'=>true,
			'caption'=>'inputmce',
        ]);",
    
    ],
    [
        'title'=>'Radios',
    ],
    [
        
        'code'=>"new inputRadios(['name'=>'radio1','caption'=>'Simple radios', 'options'=>['1'=>'Option 1','2'=>'Option 2']]);",
    
    ],
    
    [
        'title'=>'Checkboxs',
    ],
    [
        'code'=>"new inputCheckbox(['name'=>'checkbox1','caption'=>'Checkbox1']);",
    
    ],
    
    [
        
        'code'=>"new inputCheckboxs(['name'=>'checkboxArray1','caption'=>'Multiple Checkbox in array', 'options'=>['1'=>'Option 1','2'=>'Option 2']]);",
    
    ],
    
    [
        'title'=>'Select',
        'code'=>'',
    ],
    [
        
        'code'=>"new select([
        'caption'=>'Simple',
		'name'=>'select1',
		'options'=>[''=>'Select....','1'=>'Option 1','2'=>'Option 2']]);",
    ],
   
    [
        'title'=>'Multiple selection',
        'code'=>"new select([
		'name'=>'select2',
		'multiple'=>true,
		'options'=>[''=>'Select....','1'=>'Option 1','2'=>'Option 2']]);",
    ],/*
    [
        'title'=>'html',
        'code'=>"new inputrte(['name'=>'html']);"
    ],*/
    [
        'title'=>'mapmarker',
        'code'=>"new mapmarker(['name'=>'map1']);"
    ],
     [
        'title'=>'Signature_pad',
        'code'=>"new Signature_pad(['name'=>'sigpad1','backgroundColor'=>'rgb(255,255,255)','path'=>__DIR__.'/tmp/sign.png']);"
    ]
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
