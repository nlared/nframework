<?php

class embededArray
{
	public $action;
	public $id;
	public $containerid;
	public $dialogid;
	public $dataset;
	public $embededArray;
	private $database;
	private $collection;
	public $nameprefix;
	public $field;
	private $historic;
	private $_id;
	private $simpleid;
	public $elements = [];
	public $nfparent;
	public $target;
	public $template;
	public $nfchilds = [];
	public function addElement(&$object)
	{
		//$this->elements[] = $object;
	}

	public function __construct($options = [])
	{
		global $nframework;
		foreach ($options as $option => $value) {
			$this->{$option} = $value;
		}

		$this->database = $this->dataset->collection->getDatabaseName();
		$this->collection = $this->dataset->collection->getCollectionName();
		$this->nameprefix = $this->dataset->nameprefix;
		$this->historic = $this->dataset->historic;
		$this->simpleid = $this->dataset->simpleid;
		$this->_id = $this->dataset->_id;
		if (empty($this->id)) {
			$this->id = 'ArrayFront_' . hash('crc32', $_SERVER['PHP_SELF'] . $this->_id) . '_' . $nframework->counters('ajaxdialog');
		}
	}

	public function function_new()
	{

		return $this->id . '_show()';
	}

	public function __toString()
	{
		global $nframework, $javas;
		$elements = [];
		foreach ($this->elements as $e) {
			$elements[] = $e;
		}
		$_SESSION['nfembeded'][$this->id] = [
			'database' => $this->database,
			'collection' => $this->collection,
			'nameprefix' => $this->nameprefix,
			'historic' => $this->historic,
			'simpleid' => $this->simpleid,
			'_id' => $this->_id,
			'field' => $this->field,
			'template' => $this->template,
			'nfparent' => $this->nfparent,
			'nfchilds' => $this->nfchilds,
			'target' => $this->target,
			'elements' => $elements
		];

		//addVarToGarbage('nfembeded\\' . $this->id, time() + (60 * 60));


		if (!$nframework->onces['embededArray']) {
			$javas->addjs('var nfembededs=[];');
			$nframework->onces['embededArray'] = true;
		}
		if (!empty($this->target)) {
			$target = 'target:"' . $this->target . '",';
		}
		$parent = (empty($this->nfparent) ? "''" : '$("#' . $this->nfparent . '_form input[name=\'pos\']").val()');
		$childs = '';
		foreach ($this->nfchilds as $c) {
			$childs .= "\n" . $c . '_load();';
		}

		$java = <<<JAVA
	function {$this->id}_show(){
		
		$.ajax({
			url: "/nframework/embeded.php?_id={$this->id}",
			method: 'post',
			cache: false,
			headers: { 'Cache-Control': 'no-cache' },
			data:{
				op: 'pos',
				pos: nfembededs['{$this->id}'],
				t: $.now() 
			}
		}).done(function(result) {
			$("#{$this->dialogid}_form")[0].reset();
	    	$('#{$this->dialogid}_op').val('update');
	    	$('#{$this->dialogid}_pos').val(nfembededs['{$this->id}']);
	    	{$childs}
	    	{$this->dialogid}.showModal();
		});
	}
	
	function {$this->id}_load(){
		$.ajax({
			url: "/nframework/embeded.php?_id={$this->id}",
			method: 'post',
			cache: false,
			headers: { 'Cache-Control': 'no-cache' },
			data:{
				t: $.now() 
			}
		}).done(function(result) {
			$('#{$this->containerid}').html(result.container);
			nfembededs['{$this->id}']=result.items.length;
		});
	}
	function {$this->id}_get(pos){
		$.ajax({
			url: "/nframework/embeded.php?_id={$this->id}",
			method: 'post',
			cache: false,
			headers: { 'Cache-Control': 'no-cache' },
			data:{
				op: 'load',
				pos: pos,
				t: $.now() 
			}
		}).done(function(result) {
			{$this->dialogid}.showModal();
			$("#{$this->dialogid}_op").val('update');
			$('#{$this->dialogid}_pos').val(pos);
			
			{$childs}
			
			Object.keys(result.item).forEach(key => {
				console.log(key);
				console.log(result.item[key]);
			    var mo = Metro.getPlugin('#{$this->nameprefix}_'+key, "select");
			    if (mo){
			    	mo.val(result.item[key]);
			    }else{
			    	mo=Metro.getPlugin('#{$this->nameprefix}_'+key+'_0', "radio");
			    	if(mo){
			    		var value=result.item[key];
			    		selector = 'input[id^="{$this->nameprefix}_'+key+'_"][type="radio"][value="'+value+'"]';
			    		$(selector).prop('checked', true);
			    	}else{
				    	const input = document.querySelector('#{$this->nameprefix}_'+key);
				    	if (input) {
				        	input.value = result.item[key];
				    	}
			    	}
			    }
			});
		});
			
	}
	/*function {$this->id}_ok(){
	
	
			formData = $("#{$this->dialogid}_form").serialize()+ '&t=' + $.now();
			$.ajax({
				url: "/nframework/embeded.php?_id={$this->id}",
				method: 'post',
				cache: false,
				headers: { 'Cache-Control': 'no-cache' },
				data: formData
			}).done(function(result) {
				$('#{$this->containerid}').html(result.tabla);
				$('#{$this->containerid}').html(result.container);
				nfembededs['{$this->id}']=result.items.length;
				{$this->dialogid}.close();
			});
	
		
	}*/
	function {$this->id}_delete(pos){
		Swal.fire({
			title: 'Estas seguro?',
			text: 'No podras deshacer esto!',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Si, borrar!',
			{$target}
		}).then((result) => {
			if (result.isConfirmed) {
				
				$.ajax({
					url: "/nframework/embeded.php?_id={$this->id}",
					method: 'post',
					headers: { 'Cache-Control': 'no-cache' },
					cache: false,
					data:{
						op: 'delete',
						pos: pos,
						t: $.now() 
					}
				}).done(function(result) {
					$('#{$this->containerid}').html(result.container);
					nfembededs['{$this->id}']=result.items.length;
				});
			}
		});
	}
	{$this->id}_load();
	$('#{$this->dialogid}_btnAcept').on("click", function(){
		//parentpos={$parent};
		const validator = Metro.validator;
		
		let d= {
            val: 0,
            log: []
        }
		
		let valid=true;
		let errormsg='';
		
		$("#{$this->dialogid}_form").find("input[data-validate], select[data-validate], textarea[data-validate]").each(function () {
			const label = $(this).data("label") || this.name || this.id;
			Metro.validator.validate(this, d, 
		    	() => console.log('valid'),
		    	() => {valid=false;errormsg+='Error en '+label;},
		    	true // requiredMode
		  );
		});
		console.log(d);
			
		if(valid){	
			console.log('here true');
			formData = $("#{$this->dialogid}_form").serialize()+ '&t=' + $.now();;
			$.ajax({
				url: "/nframework/embeded.php?_id={$this->id}",
				method: 'post',
				headers: { 'Cache-Control': 'no-cache' },
				cache: false,
				data: formData
			}).done(function(result) {
				$('#{$this->containerid}').html(result.container);
				
				nfembededs['{$this->id}']=result.items.length;
			});
			{$this->dialogid}.close();
		}else{
			//toast(errormsg,null,5000, "warning");
			alert(errormsg);
		}
	});
	
	
	$( "#{$this->dialogid}_btnClose" ).on( "click", function() {
		{$this->id}_load();
		
	});
	
JAVA;

		$javas->addjs($java);

		return '';
	}
}
