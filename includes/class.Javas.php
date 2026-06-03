<?php

class Javas
{
	public array $js; // =['general'=>'','resize'=>'','ready'=>''];

	public bool $flushed = false;

	public array $docend = [];

	public function __construct()
	{
		$this->js = ['general' => '', 'resize' => '', 'ready' => '', 'scroll' => ''];
		// $this->docend=[];
	}

	public function addjs($jss, $seccion = 'general')
	{
		$this->js[$seccion] .= $jss;
	}

	public function __toString(): string
	{
		global $nframework, $csrftoken;
		$lng = $nframework->language;
		if (! $this->flushed) {
			$this->flushed = true;

			/*$("input[data-custom-buttons=\'customCalendarButton\']").datetimepicker({
                  format:\'Y-m-d H:i\',mask:false,lang:\'es\'
              });*/

			$javasonce = implode("\r\n", array_reverse($nframework->javasonce));

			$js = <<<JS
	    		
	    		var nbacklink="/";
			var datatables=[];
var tomselects=[];
var ajaxdialogs=[];
{$this->js['general']}


function nfWindowResize() {
{$this->js['resize']}
};
var nfWindowResizeTimer;
$(window).resize(function() {
    clearTimeout(nfWindowResizeTimer);
    nfWindowResizeTimer = setTimeout(nfWindowResize, 100);
});


const lightThemeVars = {
    '--button-background': '##ebebeb',
    '--button-background-hover': '#dadada',
    '--button-color': '#ebebeb',
    '--button-border-color': '#191919',
    
    '--logo-background': '#f6f6f6',
    '--logo-color': '#292826',
    'accent-color': '292826',
    'caret-color': '292826'
}
//.dark-side {
const darkThemeVars = {
    '--button-background': '#2b2d30',
    '--button-background-hover': '#333439',
    '--button-color': '#f3fcff',
    '--button-border-color': '#4e5157',
    '--logo-background': '#2c2d30',
    '--logo-color': '#faf5f5',
    'accent-color': '292826',
    'caret-color': '292826'
}

Object.entries(lightThemeVars).forEach(([key, value]) => {
  //document.documentElement.style.setProperty(key, value);
});

// Apply to .dark-side (dark theme container)
const darkContainer = document.querySelector('.dark-side');
if (darkContainer) {
  Object.entries(darkThemeVars).forEach(([key, value]) => {
	//darkContainer.style.setProperty(key, value);
  });
}


function nfonFormError(form, errors) {
    console.log("Form has errors:", errors);	
	var msg=' {$lng['error_capture']} <br>';	
	$.each(form, function(){
		var label=$('label[for=\'+this.input.id+\']').text();
		var minput=this.input;
		if (minput.hasAttribute("labelid")){
			label=$('label[id=\''+minput.getAttribute('labelid')+'\']').text();
		}
		msg+=label  +'<br>';
		for (const [i, value] of this.errors.entries()){
			console.log(value);			
			switch(value){
				case 'pattern':
					msg+='-{$lng['pattern']} ' + minput.pattern + '<br>';
					break;
				case 'required':
					msg+='-{$lng['required']}<br>';
					break;
			}
		};
		msg+='<br>';
	});	
	toast(msg,null,5000);
}
function nfHide(element){
	element.hide();
	if (element.hasAttribute("required")){
		element.removeAttribute("required");	
		metro.validation.reset(element);
	}
}
function nfShow(element){
	element.show();
	if (element.hasAttribute("data-required")){
		element.setAttribute("required",true);	

	}
}

function syscalls() {
    $.ajax({
      url: "/nframework/kernel.php", // URL to send the request to
      type: "GET", // Type of request (GET or POST)
      success: function(result){
	      console.log(result);
	    if (result.hasOwnProperty("pids")){
	        console.log("pids");
	        $(".bg_process").each(function() {
				var pid=$(this).attr("id").substring(10);
				if (!result.pids.hasOwnProperty(pid)){
					var icon = $(this).find("span");
					icon.removeClass("mif-stop");
					icon.addClass("mif-play");
				}
			});	
	    }else{
			$(".bg_process").each(function() {
				var icon = $(this).find("span");
				icon.removeClass("mif-stop");
				icon.addClass("mif-play");
			});
	    }
      },
      error: function(xhr, status, error){
        console.error("Error: " + error); // Handle any errors
      }
    });
}
//setInterval(syscalls, 10000);


$(window).scroll(function(){
{$this->js['scroll']}
});


function speak(text,callback){
	  if ('speechSynthesis' in window) {
	  	var u = new SpeechSynthesisUtterance();
    u.text = text;
    u.lang = 'es-MX';
    u.onend = function () {
        if (callback) {
            callback();
        }
    };
    u.onerror = function (e) {
        if (callback) {
            callback(e);
        }
    };
    speechSynthesis.speak(u);
	  } else {
	    console.log("Oops! Your browser does not support HTML SpeechSynthesis.")
	  }
}
const dialogLoading = document.querySeleector("#dialogLoading");
//const showButton = document.querySelector("dialog + button");

$("#dialogCancel").on("click", function(){
  dialogLoading.close();
});

$(document).ready(function() {


	$.extend(jQuery.expr.pseudos, {
	  'containsi': function(elem, i, match, array)
	  {
	    return (elem.textContent || elem.innerText || '').toLowerCase()
	    .indexOf((match[3] || "").toLowerCase()) >= 0;
	  }
	});
    window.addEventListener("keyup", function(e){
	    if(e.keyCode == 27)
	    window.location.href=nbacklink;
    }, false);
    
    $("input[data-role='spiner']").spinner();
    $("div[data-role-aux='file-progress-bar']").hide();
    $("input[data-sequential-uploads='true']").each(function( index ) {
		var mid=$(this).attr("id");
	    $.ajax({
			url: '/nframework/uploadfile.php',
			method:"POST",
			data: "mid="+mid, 
			dataType: 'json',
			success: function(data) {
				nfFileMakeTable(mid, data);
				
			}
		});
    });
    $("input[uppercase='true']").each(function(index){
      this.addEventListener("keypress", forceKeyPressUppercase, false);
    });
     $("input[lowercase='true']").each(function(index){
      this.addEventListener("keypress", forceKeyPressLowercase, false);
    });
  
	
	$('.nfinfoicon').click(function() {
	 var content=$(this).attr('content');
	  Metro.infobox.create(content);
	});
	
	$(".ajaxform").submit(function(e) {
	    var form=$(this);
	    var url = form.attr( "action" );; // the script where you handle the form input.
	    var f=form.attr("data-on-success");
	    if (f === undefined || f === null) {
	    	f="nAjaxFormDone"
	    }
	    $.ajax({
			type: "post",
			url: url,
			beforeSend: function(xhr) { 
		  	  xhr.setRequestHeader("X-CSRF-Token", "{$csrftoken}"); 
			}, 
			data: form.serialize(), // serializes the forms elements.
			success: function(data){
			   	Metro.utils.callback(f,[data]);
			},
			error:function(jqXHR, textStatus) {
				  alert( "Request failed: " + textStatus );
			}
		});
		
	    e.preventDefault(); // avoid to execute the actual submit of the form.
	});
	$(".ajaxform2").submit(function(e) {
	    var form=$(this);
	    var url = form.attr( "action" );; // the script where you handle the form input.
	    var f=form.attr("data-on-success");
	    if (f === undefined || f === null) {
	    	f="nAjaxFormDone"
	    }
	    $.ajax({
			type: "post",
			url: url,
			data: form.serialize(), // serializes the forms elements.
			success: function(data){
			   	Metro.utils.callback(f,[data]);
			},
			error:function(jqXHR, textStatus) {
				  //alert( "Request failed: " + textStatus );
				  toast("Datos Guardados.");
			}
		});
		
	    e.preventDefault(); // avoid to execute the actual submit of the form.
	});
	$(".secureop").click(function() {
		var op = $(this).closest("form").find("input[name=\"op\"]");
		op.val($(this).val());
	});
	 {$javasonce}
	 {$this->js['initializecomponent']}
	 {$this->js['ready']}
	 nfWindowResize();
});
JS;

			/*

            jQuery.datetimepicker.setLocale(\''.$nframework->langshort.'\');
               $(\'.datetimepicker2date\').datetimepicker({
              timepicker:false,
function onFormError(form, errors) {
    console.log("Form has errors:", errors);
}

              format:\'Y-m-d\',
               i18n:{
              '.$nframework->langshort.':{
               months:'.json_encode($nframework->languages[$nframework->lang]['calendar']['months']).',
               dayOfWeek:'.json_encode($nframework->languages[$nframework->lang]['calendar']['days']).'
              }
             },
            });
                $(".ui-spinner").addClass("w-100");

            });

            */

			// $packer = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
			// $packed_js = $packer->pack();
			return implode("\r\n", array_reverse($this->docend)) . '
<script>
' . $js . ' 
</script>';
		} else {
			return '';
		}
	}
}
