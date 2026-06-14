<?php

class TdCFDITypeValidator extends XMLS
{
    public $tagName = 'tdCFDI:Tipos';
    public $addattributes = 'xmlns:tdCFDI="http://www.sat.gob.mx/sitio_internet/cfd/tipoDatos/tdCFDI"';

    /**
     * Rule map generated from sat/xsd/tdCFDI.xsd simpleType definitions.
     */
    private static array $simpleTypeRules = [
        't_CURP' => [
            'length' => 18,
            'pattern' => '[A-Z][AEIOUX][A-Z]{2}[0-9]{2}(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])[MHX]([ABCMTZ]S|[BCJMOT]C|[CNPST]L|[GNQ]T|[GQS]R|C[MH]|[MY]N|[DH]G|NE|VZ|DF|SP)[BCDFGHJ-NP-TV-Z]{3}[0-9A-Z][0-9]',
        ],
        't_Importe' => [
            'minInclusive' => '0.000000',
            'fractionDigits' => 6,
            'pattern' => '[0-9]{1,18}(.[0-9]{1,6})?',
        ],
        't_Fecha' => [
            'pattern' => '((19|20)[0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])',
        ],
        't_ImporteMXN' => [
            'minInclusive' => '0.00',
            'fractionDigits' => 2,
            'pattern' => '[0-9]{1,18}(.[0-9]{1,2})?',
        ],
        't_CuentaBancaria' => [
            'pattern' => '[0-9]{10,18}',
        ],
        't_RFC' => [
            'minLength' => 12,
            'maxLength' => 13,
            'pattern' => '[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{2}[0-9A]',
        ],
        't_RFC_PM' => [
            'minLength' => 12,
            'pattern' => '[A-Z&Ñ]{3}[0-9]{2}(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{2}[0-9A]',
        ],
        't_RFC_PF' => [
            'minLength' => 13,
            'pattern' => '[A-Z&Ñ]{4}[0-9]{2}(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{2}[0-9A]',
        ],
        't_FechaHora' => [
            'pattern' => '((19|20)[0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T(([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9])',
        ],
        't_FechaH' => [
            'pattern' => '(20[1-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T(([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9])',
        ],
        't_Descrip100' => [
            'minLength' => 1,
            'maxLength' => 100,
            'pattern' => '[^|]{1,100}',
        ],
        't_NumeroDomicilio' => [
            'minLength' => 1,
            'maxLength' => 55,
            'pattern' => '[^|]{1,55}',
        ],
        't_Referencia' => [
            'minLength' => 1,
            'maxLength' => 250,
            'pattern' => '[^|]{1,250}',
        ],
        't_Descrip120' => [
            'minLength' => 1,
            'maxLength' => 120,
            'pattern' => '[^|]{1,120}',
        ],
        't_TipoCambio' => [
            'minInclusive' => '0.00',
            'fractionDigits' => 6,
            'pattern' => '[0-9]{1,18}(.[0-9]{1,6})?',
        ],
    ];

    public static function availableTypes(): array
    {
        return array_keys(self::$simpleTypeRules);
    }

    public static function validateSimpleType(string $typeName, string $value, array &$errors = []): bool
    {
        $errors = [];

        if (!isset(self::$simpleTypeRules[$typeName])) {
            $errors[] = 'Unknown simple type: ' . $typeName;
            return false;
        }

        $rules = self::$simpleTypeRules[$typeName];
        $value = self::collapseWhitespace($value);

        if (isset($rules['length']) && strlen($value) !== (int)$rules['length']) {
            $errors[] = sprintf('Length must be %d', (int)$rules['length']);
        }

        if (isset($rules['minLength']) && strlen($value) < (int)$rules['minLength']) {
            $errors[] = sprintf('Length must be >= %d', (int)$rules['minLength']);
        }

        if (isset($rules['maxLength']) && strlen($value) > (int)$rules['maxLength']) {
            $errors[] = sprintf('Length must be <= %d', (int)$rules['maxLength']);
        }

        if (isset($rules['pattern'])) {
            $regex = '/^' . str_replace('/', '\\/', (string)$rules['pattern']) . '$/u';
            if (!preg_match($regex, $value)) {
                $errors[] = 'Pattern validation failed';
            }
        }

        if (isset($rules['minInclusive'])) {
            if (!is_numeric($value) || (float)$value < (float)$rules['minInclusive']) {
                $errors[] = 'Value must be >= ' . $rules['minInclusive'];
            }
        }

        if (isset($rules['fractionDigits']) && is_numeric($value)) {
            $parts = explode('.', (string)$value, 2);
            $fraction = $parts[1] ?? '';
            if (strlen($fraction) > (int)$rules['fractionDigits']) {
                $errors[] = sprintf('Fraction digits must be <= %d', (int)$rules['fractionDigits']);
            }
        }

        return empty($errors);
    }

    public static function assertSimpleType(string $typeName, string $value): void
    {
        $errors = [];
        if (!self::validateSimpleType($typeName, $value, $errors)) {
            throw new InvalidArgumentException($typeName . ' invalid: ' . implode('; ', $errors));
        }
    }

    private static function collapseWhitespace(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }
}
