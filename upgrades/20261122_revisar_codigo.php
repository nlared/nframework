<?php
/**
 * Revisión del código de los sitios por los cambios de 2026.1 (ver UPGRADING.md). Solo informa, salvo el
 * nombre de clase de inputMCE, que se corrige solo.
 */
return [
    'version' => '2026.1',
    'description' => 'Revisión de código: cambios de comportamiento de 2026.1',
    'code' => [
        [
            'description' => 'notify() ahora muestra el título: revise que el texto del primer argumento sea el que quiere ver',
            'pattern' => '/\bnotify\(\s*[\'"][^\'"]+[\'"]\s*,/',
        ],
        [
            'description' => '$user[\'campo\'] = ... ahora se guarda en MongoDB (antes solo quedaba en memoria)',
            'pattern' => '/\$user\[[^\]]+\]\s*=[^=]/',
        ],
        [
            'description' => 'allUpper/allLower ahora sí convierten el texto de los controles del dataset',
            'pattern' => '/[\'"]all(Upper|Lower)[\'"]\s*=>\s*true/',
        ],
        [
            'description' => 'embededArray: los subcampos inputNumber se guardan como número y las casillas como true/false',
            'pattern' => '/new\s+embededArray\s*\(/i',
        ],
        [
            'description' => 'inputMCE con upload necesita mediadir absoluto fuera del framework y baseurl',
            'pattern' => '/[\'"]upload[\'"]\s*=>\s*true/',
        ],
        [
            'description' => 'Nombre de clase: inputmce → inputMCE',
            'pattern' => '/\bnew\s+inputmce\s*\(/',
            'replace' => 'new inputMCE(',
        ],
    ],
];
