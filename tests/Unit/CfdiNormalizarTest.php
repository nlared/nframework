<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../sat/cfdinormalizar.php';

class CfdiNormalizarTest extends TestCase
{
  private function documento(): array
  {
    return [
      '_id' => 1,
      'Fecha' => '2024-03-01T10:15:00',
      'SubTotal' => '1000.00',
      'Descuento' => '',
      'Total' => '1160.00',
      'Serie' => 'A',
      'FormaPago' => '01',
      'Conceptos' => ['Concepto' => [
        ['Cantidad' => '2', 'ValorUnitario' => '500', 'Importe' => '1000', 'Descripcion' => 'Servicio'],
        ['Cantidad' => 'abc'],
      ]],
      'Complemento' => [
        'TimbreFiscalDigital' => ['UUID' => 'u', 'FechaTimbrado' => '2024-03-01T10:16:00'],
        'Pagos' => [
          'Totales' => ['MontoTotalPagos' => '50'],
          // Un solo DoctoRelacionado se guarda como objeto, no como lista.
          'Pago' => [
            ['FechaPago' => '2024-02-01T12:00:00', 'Monto' => '50', 'DoctoRelacionado' => ['NumParcialidad' => '2', 'ImpPagado' => '50']],
            ['Monto' => '7'],
          ],
        ],
      ],
    ];
  }

  public function testConvierteImportesYFechasYConservaClaves(): void
  {
    $set = cfdiTiposDocumento($this->documento())['set'];

    $this->assertSame(1160.0, $set['Total']);
    $this->assertNull($set['Descuento']);
    $this->assertArrayNotHasKey('Serie', $set);
    $this->assertArrayNotHasKey('FormaPago', $set);
    $this->assertSame(2.0, $set['Conceptos.Concepto'][0]['Cantidad']);
    $this->assertSame('Servicio', $set['Conceptos.Concepto'][0]['Descripcion']);
    $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $set['Fecha']);
    // Hora local de México (UTC-6) guardada en UTC.
    $this->assertSame('2024-03-01T16:15:00+00:00', $set['Fecha']->toDateTime()->format(DATE_ATOM));
    $this->assertSame(2, $set['Complemento']['Pagos']['Pago'][0]['DoctoRelacionado']['NumParcialidad']);
    $this->assertSame(7.0, $set['Complemento']['Pagos']['Pago'][1]['Monto']);
    $this->assertSame(50.0, $set['Complemento']['Pagos']['Totales']['MontoTotalPagos']);
    $this->assertSame(CFDI_TIPOS_VERSION, $set['tipos_version']);
  }

  public function testReportaValoresQueNoSonNumeros(): void
  {
    $calculo = cfdiTiposDocumento($this->documento());

    $this->assertSame(['Conceptos.Concepto.Cantidad=abc'], $calculo['errores']);
    $this->assertSame('abc', $calculo['set']['Conceptos.Concepto'][1]['Cantidad']);
  }

  public function testEsIdempotente(): void
  {
    $primera = cfdiTiposDocumento($this->documento())['set'];
    $doc = $this->documento();
    $doc['Total'] = $primera['Total'];
    $doc['Fecha'] = $primera['Fecha'];

    $segunda = cfdiTiposDocumento($doc)['set'];

    $this->assertSame(1160.0, $segunda['Total']);
    $this->assertEquals($primera['Fecha'], $segunda['Fecha']);
  }
}
