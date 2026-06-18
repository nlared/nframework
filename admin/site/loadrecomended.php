<?
require '../common2.php';

$json = file_get_contents(__DIR__ . '/security_rules.json');
$data = json_decode($json, true);

if ($data && isset($data['rules'])) {
    foreach ($data['rules'] as $rule) {
        $m->{$config['sitedb']}->nfsecurityrules->updateOne(
            ['name' => $rule['name']],
            [
                '$set' => [
                    'name' => $rule['name'],
                    'enabled' => $rule['enabled'],
                    'rule' => json_encode($rule['query'])
                ]
            ],
            ['upsert' => true]
        );
    }
}

header('Location: security.php');
