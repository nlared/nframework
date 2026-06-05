<?php
$noobfuscate = true;
require_once '../common2.php';
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$codes = [
    [
        'code' => "new inputText([
        	'name'=>'inputext1',
        	'caption'=>'inputtext',
        ]);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext2','caption'=>'InputText with value','value'=>'Some text']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext3','caption'=>'InputText read only','readonly'=>true,'value'=>'Some text']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext4','caption'=>'InputText placeholder','placeholder'=>'placeholder']);",
    ],

    [
        'code' => "new inputText(['name'=>'inputext4','caption'=>'InputText prepend, append','prepend'=>'prepend','append'=>'append']);",
    ],

    [
        'code' => "new inputText(['name'=>'inputext4','caption'=>'InputText prepend, append','prepend_options'=>'https://,http://','append_options'=>'.com,.net,.org']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext5','caption'=>'InputText password','type'=>'password']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext6','caption'=>'InputText disabled','disabled'=>true]);",

    ],
    [
        'code' => "new inputText(['name'=>'inputext7','caption'=>'InputText Readonly','readonly'=>true]);",

    ],
    [
        'code' => "new inputText(['name'=>'inputext8','caption'=>'InputText backreadonly','backreadonly'=>true]);",

    ],

    [
        'code' => "new inputText(['name'=>'inputext9','caption'=>'InputText uppercase','uppercase'=>true]);",

    ],
    [
        'code' => "new inputText(['name'=>'inputext10','caption'=>'InputText mask','mask'=>'___-___-____', 'mask_pattern'=>'\d']);",

    ],
    [
        'title' => 'inputNumber',
    ],
    [

        'code' => "new inputNumber(['name'=>'number1','caption'=>'Simple']);",
    ],
    [
        'code' => "new inputNumber(['name'=>'inpunumber2', 'caption'=>'With Value','value'=>33]);",
    ],
    [
        'code' => "new inputNumber(['name'=>'inputnumber3','caption'=>'InputText min max step','min'=>-5,'max'=>30,'step'=>1]);",
    ],
    [
        'code' => "new inputNumber(['name'=>'inputnumber4','caption'=>'InputNumber prepend, append','prepend'=>'prepend','append'=>'append']);",
    ],
    [
        'title' => 'TinyMCE',
    ],
    [

        'code' => "new inputMCE([
        	'name'=>'MCE',
        	'mediadir'=>realpath( '../tmp').'/',
			'baseurl'=>'/tmp/',
			'id'=>'uniqueid',
			'upload'=>true,
			'caption'=>'inputmce',
        ]);",

    ],
    [
        'title' => 'Radios',
    ],
    [

        'code' => "new inputRadios(['name'=>'radio1','caption'=>'Simple radios', 'options'=>['1'=>'Option 1','2'=>'Option 2']]);",

    ],

    [
        'title' => 'Checkboxs',
    ],
    [
        'code' => "new inputCheckbox(['name'=>'checkbox1','caption'=>'Checkbox1']);",

    ],

    [

        'code' => "new inputCheckboxs(['name'=>'checkboxArray1','caption'=>'Multiple Checkbox in array', 'options'=>['1'=>'Option 1','2'=>'Option 2']]);",

    ],

    [
        'title' => 'Select',
        'code' => '',
    ],
    [

        'code' => "new select([
        'caption'=>'Simple',
		'name'=>'select1',
		'options'=>[''=>'Select....','1'=>'Option 1','2'=>'Option 2']]);",
    ],

    [
        'title' => 'Multiple selection',
        'code' => "new select([
		'name'=>'select2',
		'multiple'=>true,
		'options'=>[''=>'Select....','1'=>'Option 1','2'=>'Option 2']]);",
    ],
    [
        'title' => 'Select ajax',
        'code' => "new select(['name'=>'select3','caption'=>'Select ajax','ajax'=>['url'=>'ajax.php','data'=>['type'=>'select']]]);",
    ],
    [
        'title' => 'InputText - mas propiedades',
        'code' => '',
    ],
    [
        'code' => "new inputText(['name'=>'inputext11','caption'=>'InputText email','type'=>'email','placeholder'=>'usuario@dominio.com','required'=>true]);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext12','caption'=>'InputText regex pattern','pattern'=>'^[A-Z]{3}-\\d{4}$','placeholder'=>'ABC-1234']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext13','caption'=>'InputText lowercase + autotrim','lowercase'=>true,'autotrim'=>true,'value'=>'  TEXTO MIXTO  ']);",
    ],
    [
        'code' => "new inputText(['name'=>'inputext14','caption'=>'InputText con validacion y titulo','validate'=>'required minlength=5','title'=>'Minimo 5 caracteres']);",
    ],
    [
        'title' => 'Textarea',
        'code' => '',
    ],
    [
        'code' => "new textarea(['name'=>'textarea1','caption'=>'Textarea simple','placeholder'=>'Escribe aqui...']);",
    ],
    [
        'code' => "new textarea(['name'=>'textarea2','caption'=>'Textarea uppercase + contador','uppercase'=>true,'charscounter'=>200,'charscountertemplate'=>'\$charsUsed/\$charsTotal']);",
    ],
    [
        'title' => 'Fechas y tiempo',
        'code' => '',
    ],
    [
        'code' => "new inputDate(['name'=>'date3','caption'=>'InputDate con valor','value'=>'2026-06-03']);",
    ],
    [
        'code' => "new inputTime(['name'=>'time2','caption'=>'InputTime con valor','value'=>'14:30']);",
    ],
    [
        'code' => "new inputDateTime(['name'=>'datetime2','caption'=>'InputDateTime con prepend','prepend'=>'Programar','value'=>'2026-06-03T14:30']);",
    ],
    [
        'title' => 'Controles visuales',
        'code' => '',
    ],
    [
        'code' => "new inputColor(['name'=>'color1','caption'=>'InputColor','value'=>'#ff8800']);",
    ],
    [
        'code' => "new inputRating(['name'=>'rating1','caption'=>'InputRating','value'=>3,'data-values'=>'1,2,3,4,5']);",
    ],
    [
        'code' => "new inputSpinner(['name'=>'spinner1','caption'=>'InputSpinner integer','value'=>10,'validate'=>'integer','datasize'=>'large']);",
    ],
    [
        'title' => 'Checkboxs y radios - mas opciones',
        'code' => '',
    ],
    [
        'code' => "new inputCheckbox(['name'=>'checkbox2','caption'=>'Checkbox checked por defecto','value'=>true]);",
    ],
    [
        'code' => "new inputCheckboxs(['name'=>'checkboxArray2','caption'=>'Checkboxs horizontal','horizontal'=>true,'options'=>['a'=>'Alpha','b'=>'Beta','c'=>'Gamma'],'value'=>['a'=>true,'c'=>true]]);",
    ],
    [
        'code' => "new inputRadios(['name'=>'radio2','caption'=>'Radios con valor inicial','options'=>['low'=>'Low','medium'=>'Medium','high'=>'High'],'value'=>'medium']);",
    ],
    [
        'title' => 'Select - mas propiedades',
        'code' => '',
    ],
    [
        'code' => "new select(['name'=>'select4','caption'=>'Select con grupos','options'=>['Frontend'=>['html'=>'HTML','css'=>'CSS'],'Backend'=>['php'=>'PHP','node'=>'Node.js']],'value'=>'php']);",
    ],
    [
        'code' => "new select(['name'=>'select5','caption'=>'Select combobox','combobox'=>true,'canadd'=>true,'options'=>[''=>'Select....','one'=>'One','two'=>'Two'],'value'=>'two']);",
    ],
    [
        'code' => "new select(['name'=>'select6','caption'=>'Select multiple preseleccionado','multiple'=>true,'options'=>['php'=>'PHP','js'=>'JavaScript','go'=>'Go'],'value'=>['php','js']]);",
    ],
    [
        'title' => 'Otros',
        'code' => '',
    ],
    [
        'code' => "new inputHidden(['name'=>'hidden1','value'=>'token-demo-123']);",
    ],/*

   
    [
        'title'=>'html',
        'code'=>"new inputrte(['name'=>'html']);"
    ],*/
    [
        'title' => 'mapmarker',
        'code' => "new mapmarker(['name'=>'map1']);"
    ],
    [
        'title' => 'Signature_pad',
        'code' => "new Signature_pad(['name'=>'sigpad1','backgroundColor'=>'rgb(255,255,255)','path'=>__DIR__.'/tmp/sign.png']);"
    ]
];

$result = '';
foreach ($codes as $code) {
    if ($code['code'] != '') {
        $tmp = eval('return ' . $code['code']);
        if (isset($code['title'])) {
            $result . '<div class="row">
                    <div class="cell"><h4>' . $code['title'] . '</h4></div>
                </div>';
        }


        $result .= '		
		<div class="row">
			<div class="cell-sm">' . $tmp . '</div>
			<div class="cell-sm">' . str_replace("\r", '<br>', htmlspecialchars($code['code'])) . '</div>
		</div>';
    } else {
        $result .= '
		<div class="row">
			<div class="cell"><h3><br>' . $code['title'] . '</h3></div>
		</div>';
    }
}
echo '<div class="container">
<div class="grid p-5">' . $result . '</div></div>';
