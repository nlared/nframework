<?php

class Sidebar
{
    public string $title = 'Sidebar';
    public string $sidemenu = '';
    public string $content = '';
    public string $menuAdd = '';
    public string $color = 'cyan';
    public string $contentclass = '';
    public string $focuscolor = 'cyan';
    public string $darkcolor = 'darkCyan';
    public string $footer = '';
    public bool $expand = false;
    public bool $static = false;

    public function __construct($options)
    {
        foreach ($options as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function __toString(): string
    {
        global $javas, $user, $nframework, $config;
        // $nframework->csss['101']='https://cdn.nlared.com/pandora/css/index.css';
        $javas->addjs("
$('#buscar').keyup(function(){
    $('#side-menu>li>a').hide();
    
    var a=$(this).val();
    console.log(a);
    if (a!=''){
		$('#side-menu>li>a:not(:containsi(\"'+a+'\")').show();
    }else{
    	$('#side-menu>li>a').show();
    }
});
$('.item').click(function(e) {
  	window.location.href = $(this).attr('link') ;
   	e.preventDefault();
    e.stopPropagation();
    return false;
});
");

        $nframework->docend[] = '</main></div></div>';
        $BreadCrumbs = new BreadCrumbs;

        if (!empty($this->menuAddRight)) {
            $menuAddRight = '<div class="app-bar-container mr-auto">' . $this->menuAddRight . '</div>';
        }
        if (!empty($this->menuAddCenter)) {
            $menuAddCenter = '<div class="app-bar-container mx-auto">' . $this->menuAddCenter . '</div>';
        }


        return <<<return
<body class="m4-cloak h-vh-100">
<div data-role="navview" data-expanded="md">
    <div class="thenavview navview-pane" style="z-index:1000">
        <div class="controlnavview d-flex flex-align-center">
            <button class="pull-button m-0">
                <span class="mif-menu"></span>
            </button>
            <h2 class="text-light m-0 pl-7" style="line-height: 52px">{$this->title}</h2>
        </div>
        <div class="suggest-box">
            <input type="text" id="buscar" data-role="input" data-clear-button="false" data-search-button="true">
            <button class="holder">
                <span class="mif-search"></span>
            </button>
        </div>
        <ul class="thenavviewmenu navview-menu nav flex-column mt-4" id="side-menu">
           {$this->sidemenu}
        </ul>
        <div class="thenavviewcredit w-100 text-center text-small data-box p-2 border-top bd-grayMouse" style="position: absolute; bottom: 0">
            {$this->footer}
            
           <ul class="henavviewmenu navview-menu nav flex-column mt-4">
        		<li>
            		<a href="https://nframework.nlared.com" target="_blank" class="side-menu__item text-muted fg-white-hover no-decor">
            			<span class="icon"><span class="mif-earth2"></span></span>
            			<span class="caption">nframework</span>
            		</a>
            	</li>
            </ul>
        </div>
    </div>
    
    <div class="navview-content">
        <div data-role="appbar" class="container-query border-bottom bd-default app-bar app-bar-expand">
            {$menuAddRight}
            {$menuAddCenter}
            <div class="app-bar-container ml-auto">
            	{$this->menuAdd}
            </div>
            <div class="app-bar-container">
                {$user->usermenu()}
            </div>
        </div>
        <main id="page-content{$this->contentclass}">
return;
    }
}
