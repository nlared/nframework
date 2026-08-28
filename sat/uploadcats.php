<?
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell;
use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;

$archivos=[
	'catNomina'=>realpath('cats/catNomina.xls'),
	'catCFDI'=>realpath('cats/catCFDI_V_4_02112023.xls'),
];


require 'include.php';
$developermode=true;
ini_set('memory_limit','10240M');
set_time_limit(0);
ignore_user_abort(true);
foreach($archivos as $prefix=>$archivo){
	$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
	$reader->setReadDataOnly(true);
	$spreadsheet = $reader->load($archivo);
	
	$worksheetNames =$spreadsheet->getSheetNames();
	
	foreach($worksheetNames as $index=>$worksheetName){
		if(str_contains($worksheetName,'_Parte')){
			$cn=substr($worksheetName,0,-8);
		}elseif(is_numeric(substr($worksheetName,-1))){
			$cn=substr($worksheetName,0,-2);
		}else{
			$cn=$worksheetName;
		}
		if(substr($cn,0,1)=='C'){
			$cn='c'.substr($cn,1);
		}
		$cn=$prefix.substr($cn,1);
		
		echo "$cn\n";
		
		
		$hoja=$spreadsheet->setActiveSheetIndex($index);
		$columnasno=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
		$renglonesno=$hoja->getHighestRow();
		echo "$archivo $worksheetName $renglonesno\n";
		$ren=5;
	
		do{
			$campos=[];
			$tindex=[];
			for($columna=1;$columna<=$columnasno;$columna++){
				$valor=$hoja->getCellByColumnAndRow($columna ,$ren)->getValue();
				if(str_starts_with($valor,'c_')){
					$tindex[]=$valor;
				}
				if(!empty($valor)){
					$campos[]=$valor;
				}
			}
			$ren++;
		}while(count($campos)<=1);
		print_r($campos);
		
		do{
			$data=[];
			foreach($campos as $idx=>$campo){
				$valor=$hoja->getCellByColumnAndRow($idx+1 ,$ren)->getValue();
				if(!empty($valor)){
					$data[$campo]=$valor;
				}
			}
	//		print_r($data);
			$ren++;
			if(count($data)>1){
				$m->sat->{$cn}->insertOne($data);
			}
		}while(count($data)>1);
		//*/
		foreach($tindex as $ttindex){
			$m->sat->{$cn}->createIndex([$ttindex=>1]);
		}
	}	

}