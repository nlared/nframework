<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasTransportemaritimo extends \XMLS
{
    public $tagName = 'TransporteMaritimo';
    public $attributes = array (
  0 => 'PermSCT',
  1 => 'NumPermisoSCT',
  2 => 'NombreAseg',
  3 => 'NumPolizaSeguro',
  4 => 'TipoEmbarcacion',
  5 => 'Matricula',
  6 => 'NumeroOMI',
  7 => 'AnioEmbarcacion',
  8 => 'NombreEmbarc',
  9 => 'NacionalidadEmbarc',
  10 => 'UnidadesDeArqBruto',
  11 => 'TipoCarga',
  12 => 'Eslora',
  13 => 'Manga',
  14 => 'Calado',
  15 => 'Puntal',
  16 => 'LineaNaviera',
  17 => 'NombreAgenteNaviero',
  18 => 'NumAutorizacionNaviero',
  19 => 'NumViaje',
  20 => 'NumConocEmbarc',
  21 => 'PermisoTempNavegacion',
);
    public $_sequence = array (
  0 => 'Contenedor',
);

    public $PermSCT = '';
    public $NumPermisoSCT = '';
    public $NombreAseg = '';
    public $NumPolizaSeguro = '';
    public $TipoEmbarcacion = '';
    public $Matricula = '';
    public $NumeroOMI = '';
    public $AnioEmbarcacion = '';
    public $NombreEmbarc = '';
    public $NacionalidadEmbarc = '';
    public $UnidadesDeArqBruto = '';
    public $TipoCarga = '';
    public $Eslora = '';
    public $Manga = '';
    public $Calado = '';
    public $Puntal = '';
    public $LineaNaviera = '';
    public $NombreAgenteNaviero = '';
    public $NumAutorizacionNaviero = '';
    public $NumViaje = '';
    public $NumConocEmbarc = '';
    public $PermisoTempNavegacion = '';
    public $Contenedor = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
