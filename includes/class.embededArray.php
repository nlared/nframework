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
	public $onchange = '';
	private $documentKey;

	private function buildFieldRules(): array
	{
		$fieldRules = [];
		foreach ($this->elements as $element) {
			if (!is_object($element) || empty($element->field)) {
				continue;
			}

			$fieldName = (string) $element->field;
			$fieldRules[$fieldName] = [
				'required' => !empty($element->required),
				'pattern' => $element->pattern ?? '',
				'validate' => $element->validate ?? '',
				'type' => $element->type ?? get_class($element),
			];
		}

		return $fieldRules;
	}

	private function buildScopedIdentifier(string $prefix, ?string $base = null): string
	{
		$base = trim((string) ($base ?? ''));
		if ($base === '') {
			return $prefix . '_' . substr($this->documentKey, 0, 16) . '_' . md5(($this->field ?? '') . ($this->nameprefix ?? '') . ($this->dataset->_id ?? '') . $prefix);
		}

		return $base;
	}

	public function addElement(&$object)
	{
		$this->elements[] = $object;
	}

	private function buildDocumentKey(): string
	{
		if (!isset($this->dataset)) {
			return 'anonymous';
		}

		$collection = $this->dataset->collection ?? null;
		$database = $collection instanceof \MongoDB\Collection ? $collection->getDatabaseName() : ($this->database ?? '');
		$collectionName = $collection instanceof \MongoDB\Collection ? $collection->getCollectionName() : ($this->collection ?? 'unknown');
		$documentId = isset($this->dataset->_id) ? (string) $this->dataset->_id : 'new';
		$page = $_SERVER['PHP_SELF'] ?? $_SERVER['REQUEST_URI'] ?? '';

		return hash('sha256', $database . '.' . $collectionName . '.' . $documentId . '.' . $page);
	}

	private function cleanupSessionEntries(): void
	{
		if (empty($_SESSION['nfembeded']) || empty($this->documentKey)) {
			return;
		}

		foreach ($_SESSION['nfembeded'] as $sessionId => $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$sessionDocumentKey = $entry['document_key'] ?? null;
			if ($sessionDocumentKey === $this->documentKey && $sessionId !== $this->id) {
				unset($_SESSION['nfembeded'][$sessionId]);
				continue;
			}

			if ($sessionDocumentKey !== null && $sessionDocumentKey !== $this->documentKey) {
				unset($_SESSION['nfembeded'][$sessionId]);
			}
		}
	}

	public function __construct($options = [])
	{
		global $nframework;
		foreach ($options as $option => $value) {
			$this->{$option} = $value;
		}

		if (!isset($this->dataset) || !is_object($this->dataset)) {
			throw new InvalidArgumentException('embededArray requires a valid dataset object.');
		}

		$this->database = $this->dataset->collection->getDatabaseName();
		$this->collection = $this->dataset->collection->getCollectionName();
		$this->nameprefix = $this->dataset->nameprefix ?? '';
		$this->historic = $this->dataset->historic ?? false;
		$this->simpleid = $this->dataset->simpleid ?? false;
		$this->_id = $this->dataset->_id ?? null;
		$this->documentKey = $this->buildDocumentKey();

		$this->id = $this->buildScopedIdentifier('ArrayFront', $this->id ?? null);
		$this->containerid = $this->buildScopedIdentifier('container', $this->containerid ?? null);
		$this->dialogid = $this->buildScopedIdentifier('dialog', $this->dialogid ?? null);

		$this->cleanupSessionEntries();
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
			$elements[] = [
				'class' => get_class($e),
				//'options' => $e->getOptions()
			];
		}
		$fieldRules = $this->buildFieldRules();
		$_SESSION['nfembeded'][$this->id] = [
			'database' => $this->database,
			'collection' => $this->collection,
			'nameprefix' => $this->nameprefix,
			'historic' => $this->historic,
			'simpleid' => $this->simpleid,
			'_id' => $this->_id,
			'page' => $_SERVER['PHP_SELF'] ?? $_SERVER['REQUEST_URI'] ?? '',
			'document_key' => $this->documentKey,
			'field' => $this->field,
			'template' => $this->template,
			'nfparent' => $this->nfparent,
			'nfchilds' => $this->nfchilds,
			'target' => $this->target,
			'field_rules' => $fieldRules,
		];

		addVarToGarbage('nfembeded\\' . $this->id, time() + (60 * 60));


		if (empty($nframework->onces['embededArray'])) {
			$javas->addjs('var nfembededs=[];');
			$nframework->onces['embededArray'] = true;
		}
		$target = '';
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
			url: "/nframework/embeded.php?_id={$this->id}&document_key={$this->documentKey}",
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
			url: "/nframework/embeded.php?_id={$this->id}&document_key={$this->documentKey}",
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
			url: "/nframework/embeded.php?_id={$this->id}&document_key={$this->documentKey}",
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
					url: "/nframework/embeded.php?_id={$this->id}&document_key={$this->documentKey}",
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
					{$this->onchange}
					{$childs}
				}).fail(function(jqXHR, textStatus, errorThrown) {
					let errormsg='Error al eliminar';
					if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
						errormsg = jqXHR.responseJSON.error;
					}
					alert(errormsg);
				});

			}
		});
	}
	{$this->id}_load();
	$('#{$this->dialogid}_btnAcept').on("click", function(){
		const validator = Metro.validator;
		const form = $("#{$this->dialogid}_form");
		let d = { val: 0, log: [] };
		let valid = true;
		let errormsg = '';

		form.find("input[data-validate], select[data-validate], textarea[data-validate]").each(function () {
			const \$input = $(this);
			const label = \$input.data("label") || this.name || this.id;
			\$input.removeClass('is-invalid');
			validator.validate(this, d,
				() => { /* valid */ },
				() => {
					valid = false;
					\$input.addClass('is-invalid');
					errormsg += (errormsg ? '\\n' : '') + 'Error en ' + label;
				},
				true
			);
		});

		if(valid){
			const formData = form.serialize() + '&document_key=' + encodeURIComponent('{$this->documentKey}') + '&t=' + $.now();
			$.ajax({
				url: "/nframework/embeded.php?_id={$this->id}&document_key={$this->documentKey}",
				method: 'post',
				headers: { 'Cache-Control': 'no-cache' },
				cache: false,
				data: formData
			}).done(function(result) {
				if (result && result.error) {
					alert(result.error);
					return;
				}
				$('#{$this->containerid}').html(result.container);
				nfembededs['{$this->id}']=result.items.length;
				{$this->dialogid}.close();
				{$this->onchange}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				let backendError = 'Error al guardar';
				if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
					backendError = jqXHR.responseJSON.error;
				} else if (jqXHR.responseText) {
					backendError = jqXHR.responseText;
				}
				alert(backendError);
			});
		}else{
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
