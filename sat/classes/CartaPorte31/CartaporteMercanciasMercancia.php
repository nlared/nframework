<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasMercancia extends \XMLS
{
    public $tagName = 'Mercancia';
    public $attributes = array (
  0 => 'BienesTransp',
  1 => 'ClaveSTCC',
  2 => 'Descripcion',
  3 => 'Cantidad',
  4 => 'ClaveUnidad',
  5 => 'Unidad',
  6 => 'Dimensiones',
  7 => 'MaterialPeligroso',
  8 => 'CveMaterialPeligroso',
  9 => 'Embalaje',
  10 => 'DescripEmbalaje',
  11 => 'SectorCOFEPRIS',
  12 => 'NombreIngredienteActivo',
  13 => 'NomQuimico',
  14 => 'DenominacionGenericaProd',
  15 => 'DenominacionDistintivaProd',
  16 => 'Fabricante',
  17 => 'FechaCaducidad',
  18 => 'LoteMedicamento',
  19 => 'FormaFarmaceutica',
  20 => 'CondicionesEspTransp',
  21 => 'RegistroSanitarioFolioAutorizacion',
  22 => 'PermisoImportacion',
  23 => 'FolioImpoVUCEM',
  24 => 'NumCAS',
  25 => 'RazonSocialEmpImp',
  26 => 'NumRegSanPlagCOFEPRIS',
  27 => 'DatosFabricante',
  28 => 'DatosFormulador',
  29 => 'DatosMaquilador',
  30 => 'UsoAutorizado',
  31 => 'PesoEnKg',
  32 => 'ValorMercancia',
  33 => 'Moneda',
  34 => 'FraccionArancelaria',
  35 => 'UUIDComercioExt',
  36 => 'TipoMateria',
  37 => 'DescripcionMateria',
);
    public $_sequence = array (
  0 => 'DocumentacionAduanera',
  1 => 'GuiasIdentificacion',
  2 => 'CantidadTransporta',
  3 => 'DetalleMercancia',
);

    public $BienesTransp = '';
    public $ClaveSTCC = '';
    public $Descripcion = '';
    public $Cantidad = '';
    public $ClaveUnidad = '';
    public $Unidad = '';
    public $Dimensiones = '';
    public $MaterialPeligroso = '';
    public $CveMaterialPeligroso = '';
    public $Embalaje = '';
    public $DescripEmbalaje = '';
    public $SectorCOFEPRIS = '';
    public $NombreIngredienteActivo = '';
    public $NomQuimico = '';
    public $DenominacionGenericaProd = '';
    public $DenominacionDistintivaProd = '';
    public $Fabricante = '';
    public $FechaCaducidad = '';
    public $LoteMedicamento = '';
    public $FormaFarmaceutica = '';
    public $CondicionesEspTransp = '';
    public $RegistroSanitarioFolioAutorizacion = '';
    public $PermisoImportacion = '';
    public $FolioImpoVUCEM = '';
    public $NumCAS = '';
    public $RazonSocialEmpImp = '';
    public $NumRegSanPlagCOFEPRIS = '';
    public $DatosFabricante = '';
    public $DatosFormulador = '';
    public $DatosMaquilador = '';
    public $UsoAutorizado = '';
    public $PesoEnKg = '';
    public $ValorMercancia = '';
    public $Moneda = '';
    public $FraccionArancelaria = '';
    public $UUIDComercioExt = '';
    public $TipoMateria = '';
    public $DescripcionMateria = '';
    public $DocumentacionAduanera = [];
    public $GuiasIdentificacion = [];
    public $CantidadTransporta = [];
    public $DetalleMercancia = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
