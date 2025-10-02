<?
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}

require '../common2.php';

$nframework->usecommon=true;
$pages=['/'=>'Home'];
foreach($m->{$config['sitedb']}->pages->find() as $d){
	$pages[$d['path']]=$d['title'];
}

$pagess=new select(['options'=>$pages,'id'=>'page','caption'=>'Link:']);
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->menus,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);


$name=new inputtext(['dataset'=>&$dataset,'field'=>'name','caption'=>'Name:',]);
$code=new textarea(['dataset'=>&$dataset,'field'=>'code','caption'=>'Code:',]);



if ($nframework->isAjax()) {
	$nframework->usecommon=false;
	if ($_POST['op']=='save') {
        try {
            $result=[
                'error'=>$dataset->save(),
            ];
        } catch (Exception $e) {
            $result=[
            	'error'=>$e->getMessage()
        	];
        }
    }
} else {
	$nframework->csss['9991']='https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css';
	$nframework->jss['9991']='https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js';
	
	
	$link=new inputtext(['id'=>'node_link','caption'=>'Link']);
	$icon=new inputtext(['id'=>'node_icon','caption'=>'Icon']);
	$onfunction=new textarea(['id'=>'node_onfunction','caption'=>'OnFunction',]);
	
	$nframework->usecommon=true;
	$noobfuscate=true;
	
	$codestr=(empty($dataset->code)?'[{ "id" : "home", "parent" : "#", "text" : "Home" ,"data":{"link":"/" , "icon": "mif-home" }},]':$dataset->code);
	
	$javas->addjs(<<<js
	let selectedNode;
	
	const mtree=$('#jstree_div').jstree({
  "core" : {
    "animation" : 0,
    "check_callback" : true,
    "themes" : { "stripes" : true },
    'data' : {$codestr}
  },
  "types" : {
    "#" : {
      //"max_children" : 1,
      "max_depth" : 4,
      //"valid_children" : ["root"]
    },
    "root" : {
      "icon" : "",
      "valid_children" : ["default"]
    },
    "default" : {
      "valid_children" : ["default","file"]
    },
    "file" : {
      "icon" : "glyphicon glyphicon-file",
      "valid_children" : []
    }
  },
  "plugins" : [
    "contextmenu", "dnd", "search",
    "state", "types", "wholerow","changed"
  ]
});
	
$('#add_root').on('click', function () {
  const tree = $('#jstree_div').jstree(true);
  const newNode = tree.create_node('#', {
	id: 'new_root_' + Date.now(),
	text: 'Nuevo nodo raíz',
	data:{
		link: '#',
		icon: '',
		onfunction: ''
	},
	}, 'last');
  tree.redraw(true);
  if (newNode) tree.edit(newNode);
  
});
	
	$('#jstree_div').on("changed.jstree", function (e, data) {
		if(data.action=='select_node'){
			selectedNode=data.node.id;
			let node_link='#';
			let node_icon='';
			let node_onfunction='';
			
			if (typeof data.node.data !== 'undefined' && data.node.data !== null){
				if(typeof data.node.data.link !== 'undefined' && data.node.data.link !== null){
					node_link=data.node.data.link;
				}
				if(typeof data.node.data.icon !== 'undefined' && data.node.data.icon !== null){
					node_icon=data.node.data.icon;
				}
				if(typeof data.node.data.onfunction !== 'undefined' && data.node.data.onfunction !== null){
					node_onfunction=data.node.data.onfunction;
				}
			}
			$('#node_link').val(node_link);
			$('#node_icon').val(node_icon);
			$('#node_onfunction').val(node_onfunction);
		}
		const tree = $('#jstree_div').jstree(true);
		const fullTree = tree.get_json('#', { flat: false }); // nested structure
		console.log(fullTree);
		let menuContainer = $('.app-bar-menu')
		menuContainer.empty();
		
		let treeData =tree.get_json('#', { flat: false });
		var jsonString = JSON.stringify(treeData);
		$('#data_code').val(jsonString);
		
		fullTree.forEach(node => {
    		const icon = node.data?.icon || '';
    		const url = node.data?.link || '#';
		    let mlink;
			if (node.children && node.children.length > 0) {
				console.log(node);
				mlink = '<li><a href="#" class="dropdown-toggle">'+node.text+'</a>'+
				buildMetroSubmenu(node.children)+
				'<li>';
			}else{
				mlink = \$(`
		    		<li>
		    			<a href="\${url}" class="app-bar-item">
		        			<span class="\${icon}"></span> \${node.text}
		    			</a>
		    		</li>
		    	`);
			}
			//
			menuContainer.append(mlink);
		});
		
	});
	
	
$('#node_link').on('change', function() {
	const node = $('#jstree_div').jstree(true).get_node(selectedNode);
	node.data.link=$(this).val();
	tree.redraw(true);
	
	let treeData =tree.get_json('#', { flat: false });
	var jsonString = JSON.stringify(treeData);
	$('#data_code').val(jsonString);
	
});
$('#node_icon').on('change', function() {
	const node = $('#jstree_div').jstree(true).get_node(selectedNode);
	node.data.icon=$(this).val();
	tree.redraw(true);
	
	let treeData =tree.get_json('#', { flat: false });
	var jsonString = JSON.stringify(treeData);
	$('#data_code').val(jsonString);
	
});

$('#node_onfunction').on('change', function() {
	const node = $('#jstree_div').jstree(true).get_node(selectedNode);
	node.data.onfunction=$(this).val();
	tree.redraw(true);
	
	let treeData =tree.get_json('#', { flat: false });
	var jsonString = JSON.stringify(treeData);
	$('#data_code').val(jsonString);
});


	
function buildMetroSubmenu(nodes) {
	let lis='';
	nodes.forEach(node => {
	    const icon = node.data?.icon || '';
	    const url = node.data?.url || '#';
		let childMenu='';
	    if (node.children && node.children.length > 0) {
	        childMenu = buildMetroSubmenu(node.children);
	    }
	     lis+= '<li><a href="'+url+'"><span class="'+icon+'"></span>'+node.text+'</a>'+childMenu+'</li>';
	});
	ul='<ul class="d-menu" data-role="dropdown">'+lis+'</ul>';
	return ul;
}



js
);
?>
<style>
	#jstree_div{
		min-height:400px;
		max-width: 100%;
	    overflow: auto;
	    font: 10px Verdana, sans-serif;
	    box-shadow: 0 0 5px #ccc;
	    padding: 10px;
	    border-radius: 5px;
	}
</style>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Menu</h4></div>
	<div class="bg-white p-3">
		<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell">
					
					<div data-role="app-bar" data-expand-point="md">
					    <a href="#" class="app-bar-item-static brand">
					        <span class="mif-crown icon"></span>
					        <span class="caption">Metro UI</span>
					    </a>
					    <ul class="app-bar-menu ml-auto">
					        
					    </ul>
					    <a href="#" class="app-bar-item"><span class="mif-github"></span></a>
					    </div>
					</div>
					
					
				</div>
			</div>
			<div class="row">
				<div class="cell"><?=$name?></div>
			</div>
			<div class="row">
				<div class="cell-md-6">
					<div id="jstree_div"></div>
				</div>
				<div class="cell-md-6">
					<div  class="button" id="add_root">Agregar Menu</div>
					<?=$link?>
					<?=$icon?>
					<?=$onfunction?>
					<?=$code?>
				</div>
			</div>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="./" class="button primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
	</div>
</div>
<?}?>