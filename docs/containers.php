<?php
require '../linkstree.php';

$panel=new Panel([
    'content'=>'This is the content'
]);
$panel->caption="Panel1";
$o=new icon("user");
$panel->icon=$o;
echo $panel;



$accordion=new Accordion();
$frame=new AccordionFrame(['content'=>"contenid"]);
$frame->heading='veamos';
$accordion->addframe($frame);



echo $accordion;
