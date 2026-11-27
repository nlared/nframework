<?php
/**
 * Colas: los trabajos encolados antes de Job::$priority no tienen el campo priority. El worker ordena por
 * priority descendente y un campo ausente queda al final (incluso después de los de prioridad negativa):
 * se les asigna 0, la prioridad por omisión.
 */
return [
    'version' => '2026.3',
    'description' => 'Colas: priority = 0 en los trabajos encolados antes de Job::$priority',
    'up' => function (MongoDB\Database $db, callable $log): void {
        $n = $db->jobs->updateMany(['priority' => ['$exists' => false]], ['$set' => ['priority' => 0]])->getModifiedCount();
        if ($n > 0) {
            $log("jobs: priority = 0 en {$n} trabajo(s)");
        }
    },
];
