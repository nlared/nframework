<?

require 'common2.php';


$textInput = new inputText([
    'name' => 'example',
    'id' => 'example',
    'ajax' => (object)[
        'adduri' => '&extra=1'
    ]
]);


echo $textInput;
