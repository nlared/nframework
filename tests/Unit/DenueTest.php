<?php

namespace Tests\Unit;

use Denue;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.Denue.php';

/**
 * Lógica del autocompletado de domicilios con el DENUE que no depende de la red.
 */
class DenueTest extends TestCase
{
    private function record(array $over = []): array
    {
        return $over + [
            'Calle' => 'VENUSTIANO CARRANZA',
            'Colonia' => 'VIRREYES RESIDENCIAL',
            'CP' => '25230',
            'Ubicacion' => 'SALTILLO                      , Saltillo, COAHUILA DE ZARAGOZA',
            'Latitud' => '25.45769231',
            'Longitud' => '-100.98435688',
        ];
    }

    public function testNormalizeQuitaAcentosYSignos(): void
    {
        $this->assertSame('republica poniente', Denue::normalize('  República,  PONIENTE '));
        $this->assertSame('nunez', Denue::normalize('Núñez'));
    }

    public function testTitleDejaArticulosEnMinuscula(): void
    {
        $this->assertSame('Virreyes Residencial de la Sierra', Denue::title('VIRREYES RESIDENCIAL DE LA SIERRA'));
        $this->assertSame('Ñuñoa', Denue::title('ÑUÑOA'));
    }

    public function testStateCodeReconoceNombresComunes(): void
    {
        $this->assertSame('05', Denue::stateCode('Coahuila'));
        $this->assertSame('05', Denue::stateCode('COAHUILA DE ZARAGOZA'));
        $this->assertSame('19', Denue::stateCode('nuevo leon'));
        $this->assertSame('09', Denue::stateCode('CDMX'));
        $this->assertSame('02', Denue::stateCode('Baja California'));
        $this->assertSame('03', Denue::stateCode('Baja California Sur'));
        $this->assertSame('', Denue::stateCode('Texas'));
    }

    public function testParseRecordSeparaUbicacion(): void
    {
        $a = Denue::parseRecord($this->record());
        $this->assertSame('Coahuila de Zaragoza', $a['state']);
        $this->assertSame('Saltillo', $a['municipality']);
        $this->assertSame('Saltillo', $a['city']);
        $this->assertSame('Virreyes Residencial', $a['neighborhood']);
        $this->assertSame('Venustiano Carranza', $a['street']);
        $this->assertSame('25230', $a['zipcode']);
        $this->assertSame(25.45769231, $a['lat']);
    }

    public function testParseRecordDescartaCalleNingunoYCpInvalido(): void
    {
        $a = Denue::parseRecord($this->record(['Calle' => 'NINGUNO', 'CP' => '0']));
        $this->assertSame('', $a['street']);
        $this->assertSame('', $a['zipcode']);
    }

    public function testConditionsUsaPalabrasCompletasYContexto(): void
    {
        $ctx = ['neighborhood' => 'Virreyes', 'street' => '', 'city' => '', 'municipality' => 'Saltillo'];
        $this->assertSame(['venustiano carr', 'venustiano', 'virreyes'], Denue::conditions('street', 'Venustiano Carr', $ctx));
        // El CP no se puede buscar en el API: solo el contexto.
        $this->assertSame(['virreyes', 'saltillo'], Denue::conditions('zipcode', '252', $ctx));
        // Palabras muy cortas no se consultan.
        $this->assertSame([], Denue::conditions('street', 'av', ['neighborhood' => '', 'street' => '', 'city' => '', 'municipality' => '']));
    }

    public function testRankFiltraOrdenaPorContextoYAgrupa(): void
    {
        $records = [
            $this->record(['Colonia' => 'CENTRO', 'CP' => '27440', 'Ubicacion' => 'MATAMOROS,Matamoros,COAHUILA DE ZARAGOZA']),
            $this->record(),
            $this->record(),
            $this->record(['Calle' => 'XICOTENCATL']),
        ];
        $items = Denue::rank('street', 'venus', ['municipality' => 'Saltillo', 'state' => 'Coahuila'], $records);
        $this->assertCount(2, $items);
        $this->assertSame('Venustiano Carranza', $items[0]['value']);
        $this->assertSame('Virreyes Residencial', $items[0]['address']['neighborhood']);
        $this->assertStringContainsString('CP 25230', $items[0]['label']);
        $this->assertArrayNotHasKey('external_number', $items[0]['address']);
    }

    public function testRankCodigoPostalPorPrefijoYFrecuencia(): void
    {
        $records = [$this->record(['CP' => '25100']), $this->record(), $this->record(), $this->record(['CP' => '64000'])];
        $items = Denue::rank('zipcode', '25', ['neighborhood' => 'Virreyes Residencial'], $records);
        $this->assertSame(['25230', '25100'], array_column($items, 'value'));
        $this->assertArrayNotHasKey('street', $items[0]['address']);
    }

    public function testSuggestEstadosSinToken(): void
    {
        $items = Denue::suggest('state', 'nuevo', []);
        $this->assertSame('Nuevo León', $items[0]['value']);
        $this->assertSame([], Denue::suggest('street', 'juarez', []));
        $this->assertSame([], Denue::suggest('state', 'tex', ['country' => 'Estados Unidos']));
        $this->assertSame([], Denue::suggest('external_number', '1', []));
    }
}
