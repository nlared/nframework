<?php
/**
 * nftables: guarda los campos con los nombres de tipo actuales (inputtext → inputText...) y convierte a arreglo
 * las listas embebidas que embeded.php guardó como objeto {"0": {...}, "1": {...}} cuando el arreglo no existía.
 */
return [
    'version' => '2026.1',
    'description' => 'nftables: normaliza los campos y convierte listas embebidas guardadas como objeto',
    'up' => function (MongoDB\Database $db, callable $log): void {
        require_once dirname(__DIR__) . '/includes/class.nfTableSchema.php';
        foreach ($db->nftables->find([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]) as $tabla) {
            $errors = [];
            $fields = nfTableSchema::normalizeFields($tabla['nffields'] ?? [], $errors);
            $db->nftables->updateOne(['_id' => $tabla['_id']], ['$set' => ['nffields' => $fields]]);
            $collection = (string) ($tabla['nfcollection'] ?? '');
            if (!nfTableSchema::validCollection($collection)) {
                continue;
            }
            foreach ($fields as $f) {
                if ($f['type'] !== 'embededArray') {
                    continue;
                }
                // Solo objetos cuyas llaves son posiciones (0, 1, 2...): un subdocumento real no se toca.
                $res = $db->{$collection}->updateMany(
                    // $type en la consulta también coincide con arreglos de objetos: se compara el tipo del campo.
                    ['$expr' => ['$and' => [
                        ['$eq' => [['$type' => '$' . $f['field']], 'object']],
                        ['$allElementsTrue' => [['$map' => [
                            'input' => ['$objectToArray' => ['$cond' => [['$eq' => [['$type' => '$' . $f['field']], 'object']], '$' . $f['field'], new stdClass()]]],
                            'in' => ['$regexMatch' => ['input' => '$$this.k', 'regex' => '^[0-9]+$']],
                        ]]]],
                    ]]],
                    [['$set' => [$f['field'] => ['$map' => [
                        'input' => ['$objectToArray' => '$' . $f['field']],   // conserva el orden en que se agregaron
                        'in' => '$$this.v',
                    ]]]]]
                );
                if ($res->getModifiedCount() > 0) {
                    $log("{$collection}.{$f['field']}: {$res->getModifiedCount()} documento(s) convertidos a arreglo");
                }
            }
            $log("{$collection}: " . count($fields) . ' campo(s)' . ($errors ? ' (avisos: ' . implode('; ', $errors) . ')' : ''));
        }
    },
];
