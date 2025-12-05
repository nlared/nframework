<?php

class TableF
{
    public $filters = [];
    public $table;
    public $excelCell;
    public $excelFile;
    private $originalPipeline;
    public $codeid;
    public $query;
    public function __construct($options)
    {
        foreach ($options as $op => $v) {
            $this->{$op} = $v;
        }
        $_SESSION['datatable'][$this->table->id]['original'] = $_SESSION['datatable'][$this->table->id]['pipeline'];
    }

    public function __toString()
    {
        global $javas, $nframework;
        $_SESSION['datatable'][$this->table->id]['excelCell'] = $this->excelCell;
        $_SESSION['datatable'][$this->table->id]['excelFile'] = $this->excelFile;
        $nframework->csss['200'] = 'https://cdn.jsdelivr.net/npm/jQuery-QueryBuilder@2.7.0/dist/css/query-builder.default.min.css';
        $nframework->csss['201'] = 'https://cdn.nlared.com/nframework/4.5.1/filters.css';
        $nframework->jss['198'] = 'https://cdn.jsdelivr.net/npm/jquery-extendext@1.0.0/jquery-extendext.min.js';
        $nframework->jss['199'] = 'https://cdn.jsdelivr.net/npm/dot@1.1.3/doT.min.js';
        $nframework->jss['200'] = 'https://cdn.jsdelivr.net/npm/jQuery-QueryBuilder@2.7.0/dist/js/query-builder.min.js';
        $nframework->jss['201'] = 'https://cdn.jsdelivr.net/npm/jQuery-QueryBuilder@2.7.0/dist/i18n/query-builder.es.js';
        $nframework->jss['213'] = 'https://cdn.nlared.com/nframework/4.5.1/filters_template.js?d=' . date('Ymdhis');

        // https://www.bing.com/search?pglt=931&q=jquery-querybuilder+case+insensitive&cvid=c322539b0d9545f8897c62ee71ea95c1&gs_lcrp=EgRlZGdlKgYIABBFGDsyBggAEEUYOzIGCAEQABhAMgYIAhAAGEDSAQkxMTY4MGowajGoAgCwAgA&FORM=ANNTA1&PC=U531

        $addcodeid = (empty($this->codeid) ? '' : '
			$("#' . $this->codeid . '").val(JSON.stringify(pipelinequery));
		');
        $initquery = (empty($this->query) ? '' : "$('#builder-basic').queryBuilder('setRulesFromMongo'," . $this->query . ")");

        $javas->addjs(
            <<<js
       
        
        QueryBuilder.defaults({
        mongoOperators: {
            begins_withi: function(v) { return { '\$regex': '^' + Utils.escapeRegExp(v[0]), '\$options': 'i' }; },
            containsi: function(v) { return { '\$regex':  Utils.escapeRegExp(v[0]), '\$options': 'i' }; },
            ends_withi: function(v) { return { '\$regex': Utils.escapeRegExp(v[0]) + '\$', '\$options': 'i' }; }
        }
    });
js,
            'reaady'
        );

        $javas->addjs("
var rules_basic = {};

$('#builder-basic').queryBuilder({
  icons:{
  	add_group: 'mif-add',
  	add_rule: 'mif-add',
  	remove_group: 'mif-bin',
  	remove_rule: 'mif-bin',
  	error: 'mif-bug'
  },
  templates: {
	ruleValueSelect: templates_ruleValueSelect
  },
  filters:" . json_encode($this->filters) . " ,
  allow_empty :true,
  lang_code: 'es',
  mongoOperators: {
            begins_with: function(v) { return { '\$regex': '^' + Utils.escapeRegExp(v[0]), '\$options': 'i' }; },
            contains: function(v) { return { '\$regex': (v[0]), '\$options': 'i' }; },
            ends_with: function(v) { return { '\$regex': Utils.escapeRegExp(v[0]) + '\$', '\$options': 'i' }; }
        }
});

" . $initquery . "


var fields=[];
var columnstitles=[];
var pipelinequery=[];


$('#btn-reset').on('click', function() {
  $('#builder-basic').queryBuilder('reset');
});


function fieldschange(){
  //var result = $('#builder-basic').queryBuilder('getRules',{ skip_empty: true });
    pipelinequery = $('#builder-basic').queryBuilder('getMongo');
	console.log(pipelinequery);
	console.log(pipelinequery);
	" . $addcodeid . "
	columnstitles=[];
  	/*var rowData = campos.rows().data(); //t is my table
        $.each($(rowData), function(key,value){
            fields.push(value[0].trim()); //filter by Key column
            columnstitles.push({title: value[0].trim()});
        })*/
	console.log(fields);
	var other={
		'pipelineproject': fields,
	    'pipelinequery': pipelinequery,
	};
	console.log(other);
	$('#other').val(JSON.stringify(other));
	
	$.ajax({
	    url: '/nframework/datatablef.php?id=" . $this->table->id . "',
	    method: 'post',
	    data: {
	        pipelineproject: fields,
	        pipelinequery: pipelinequery,
	    }
	}).then(
	    function(response){
        console.log(response);
        datatables['" . $this->table->id . "'].clearPipeline().draw(false);
    	
    },
    function(xhr){
        console.log(xhr.status, xhr.statusText);
    })
};

");

        $result = <<<RESULT
		<div class="grid">
	<div class="row">
		<div class="cell-md-10">
			<div id="builder-basic"></div>
		</div>
		<div class="cell-md-2">
			<a href="#" class="button w-100" onclick="fieldschange();"><span class="mif-filter"></span>&nbsp;Filtrar</a>
			<a href="/nframework/datatablee.php?id={$this->table->id}" target="_blank" class="button w-100"><span class="mif-xls-file"></span>&nbsp;Excel</a>
			<div id="btn-reset" class="button w-100"><span class="mif-bin"></span>&nbsp;Limpiar</div>
		</div>
	</div>
</div>
RESULT;

        return $result;
    }
}
