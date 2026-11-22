<?php
/**
 * TablefIA: las consultas guardadas antes de la normalización de preguntas y de las favoritas reciben
 * promptnorm, fijada y favoritos (antes lo hacía nframework/test.php).
 */
return [
    'version' => '2026.1',
    'description' => 'TablefIA: normaliza las preguntas guardadas y agrega favoritas',
    'up' => function (MongoDB\Database $db, callable $log): void {
        require_once dirname(__DIR__) . '/includes/class.TablefIA.php';
        $col = $db->nftablesfia;
        $n = 0;
        foreach ($col->find(['promptnorm' => ['$exists' => false]], ['projection' => ['prompt' => 1]]) as $q) {
            $col->updateOne(['_id' => $q['_id']], ['$set' => ['promptnorm' => TablefIA::normalizePrompt((string) $q['prompt'])]]);
            $n++;
        }
        $n += $col->updateMany(['fijada' => ['$exists' => false]], ['$set' => ['fijada' => false, 'favoritos' => []]])->getModifiedCount();
        $log("$n consulta(s) actualizada(s)");
    },
];
