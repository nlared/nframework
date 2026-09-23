<?
require 'include.php';

function getParentPositions(array $nodes, string $startId): array
{
    $positions = [];
    $current = $startId;
    $visited = [];

    while (isset($nodes[$current]['nfparent'])) {
        if (isset($visited[$current])) {
            break;
        }
        $visited[$current] = true;

        $parentId = $nodes[$current]['nfparent'];
        if (!isset($nodes[$parentId])) {
            break;
        }

        $parentDocumentKey = $nodes[$parentId]['document_key'] ?? null;
        $currentDocumentKey = $nodes[$current]['document_key'] ?? null;
        if ($parentDocumentKey !== null && $currentDocumentKey !== null && $parentDocumentKey !== $currentDocumentKey) {
            break;
        }

        $positions[] = $nodes[$parentId]['pos'] ?? 0;
        $current = $parentId;
    }

    return array_reverse($positions);
}


use Twig\Environment;
use Twig\Extension\StringLoaderExtension;

$result = ['status' => 'init'];

function buildDocumentKey(array $info): string
{
    $database = $info['database'] ?? '';
    $collection = $info['collection'] ?? '';
    $documentId = isset($info['_id']) ? (string) $info['_id'] : '';
    $page = $info['page'] ?? $info['source_page'] ?? ($_SERVER['PHP_SELF'] ?? $_SERVER['REQUEST_URI'] ?? '');

    return hash('sha256', $database . '.' . $collection . '.' . $documentId . '.' . $page);
}

function validateEmbeddedValue(string $fieldName, $value, array $rules): array
{
    $valueText = is_array($value) ? '' : (string) $value;
    $ruleSet = is_array($rules) ? $rules : [];

    if (!empty($ruleSet['required']) && ($value === null || $value === '' || (is_array($value) && empty($value)))) {
        return ['valid' => false, 'message' => 'El campo ' . $fieldName . ' es obligatorio.'];
    }

    $validateRules = preg_split('/\s+/', trim((string) ($ruleSet['validate'] ?? '')));
    foreach ($validateRules as $rule) {
        if ($rule === 'required') {
            continue;
        }
        if ($rule === 'email' && !filter_var($valueText, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'El campo ' . $fieldName . ' no tiene un email válido.'];
        }
        if (($rule === 'number' || $rule === 'numeric') && !is_numeric($valueText)) {
            return ['valid' => false, 'message' => 'El campo ' . $fieldName . ' debe ser numérico.'];
        }
        if (($rule === 'integer' || $rule === 'digits') && !preg_match('/^-?\d+$/', $valueText)) {
            return ['valid' => false, 'message' => 'El campo ' . $fieldName . ' debe ser entero.'];
        }
    }

    if (!empty($ruleSet['pattern'])) {
        $pattern = '/' . str_replace('/', '\/', $ruleSet['pattern']) . '/';
        if ($value !== null && $value !== '' && preg_match($pattern, (string) $value) !== 1) {
            return ['valid' => false, 'message' => 'El campo ' . $fieldName . ' no cumple el formato requerido.'];
        }
    }

    return ['valid' => true, 'message' => ''];
}

function get_data($dataset, string $field)
{
    $parts = explode('.', $field);
    $ref   = $dataset;
    foreach ($parts as $part) {
        if (is_array($ref)) {
            // 1) existe la clave en el array?
            if (! array_key_exists($part, $ref)) {
                return null;
            }
            $ref = $ref[$part];
        } elseif (is_object($ref)) {
            // 2) es propiedad válida del objeto?
            if (! property_exists($ref, $part)) {
                return null;
            }
            $ref = $ref->$part;
        } else {
            // 3) ni array ni objeto → detenemos la navegación
            return null;
        }
    }
    return $ref;
}

try {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader, [
        'cache' => false, //__DIR__.'/../compilation_cache',
        'debug' => true,
    ]);
    $twig->addExtension(new StringLoaderExtension());
    if (empty($_SESSION['nfembeded']) || empty($_GET['_id']) || empty($_SESSION['nfembeded'][$_GET['_id']])) {
        die('Invalid embeded ID');
    }
    $id = $_GET['_id'];
    $info = $_SESSION['nfembeded'][$id];
    if (empty($info['_id']) || empty($info['database']) || empty($info['collection'])) {
        throw new RuntimeException('The embedded array session is incomplete.');
    }

    $sourcePage = $info['page'] ?? $info['source_page'] ?? ($_SERVER['PHP_SELF'] ?? $_SERVER['REQUEST_URI'] ?? '');
    $info['page'] = $sourcePage;
    $computedDocumentKey = buildDocumentKey($info);
    $requestedDocumentKey = $_POST['document_key'] ?? $_GET['document_key'] ?? null;
    if (!empty($requestedDocumentKey) && $requestedDocumentKey !== $computedDocumentKey) {
        throw new RuntimeException('Embedded array document reference mismatch.');
    }
    $_SESSION['nfembeded'][$id]['page'] = $sourcePage;
    $_SESSION['nfembeded'][$id]['document_key'] = $computedDocumentKey;

    //$result['debug'] = $info;
    //$result['ss'] = $_SESSION['nfembeded'];
    //$result['session_id'] = session_id();


    $dataset = new dataset(
        [
            'collection' => $m->{$info['database']}->{$info['collection']},
            '_id' => $info['_id'],
            'simpleid' => $info['simpleid'],
            'historic' => $info['historic'],
            'nameprefix' => $info['nameprefix']
        ]
    );


    $field = $info['field'];
    $parents = getParentPositions($_SESSION['nfembeded'], $id);
    $result['parents'] = $parents;

    if (!empty($parents)) {
        foreach ($parents as $value) {
            $field = preg_replace('/\$/', $value, $field, 1);
        }
    }



    //$data=mongotoArray($dataset->{$field});

    $data = mongotoArray($dataset->info);
    $result['ssssssss'] = $data;
    if (str_contains($field, '.')) {
        //	if(empty($_POST['op'])||){
        $items = get_data($data, $field);
        //	}

    } else {
        $items = $dataset->{$field};
    }
    $items = mongotoArray($items);

    $op = $_POST['op'] ?? '';
    $pos = isset($_POST['pos']) ? (int) $_POST['pos'] : null;

    if ($op === 'pos') {
        if ($pos !== null) {
            $_SESSION['nfembeded'][$id]['pos'] = $pos;
        }
        $result['items'] = $items;
    } elseif ($op === 'load') {
        if ($pos !== null && isset($items[$pos])) {
            $result['item'] = $items[$pos];
            $_SESSION['nfembeded'][$id]['pos'] = $pos;
        } else {
            throw new InvalidArgumentException('No se encontró la posición solicitada.');
        }
    } else {
        if ($op === 'update') {
            $fieldRules = $info['field_rules'] ?? [];
            $payload = $_POST[$info['nameprefix']] ?? [];
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Los datos enviados no tienen el formato esperado.');
            }

            foreach ($payload as $k => $pfield) {
                $rules = $fieldRules[$k] ?? [];
                $validation = validateEmbeddedValue($k, $pfield, $rules);
                if (!$validation['valid']) {
                    throw new InvalidArgumentException($validation['message']);
                }

                $set[$field . '.' . $pos . '.' . $k] = $pfield;
                $items[$pos][$k] = $pfield;
            }
            $result['set'] = $set;
            $m->{$info['database']}->{$info['collection']}->updateOne(['_id' => ($info['simpleid'] ? $info['_id'] : tomongoid($info['_id']))], ['$set' => $set], ['upsert' => true]);
        } elseif ($op === 'delete') {
            if ($pos === null || !isset($items[$pos])) {
                throw new InvalidArgumentException('No se encontró la posición a eliminar.');
            }

            unset($items[$pos]);
            $items = array_values($items);
            $m->{$info['database']}->{$info['collection']}->updateOne(['_id' => ($info['simpleid'] ? $info['_id'] : tomongoid($info['_id']))], ['$set' => [$field => $items]]);
        }
        $result['field'] = $field;
        $template = $twig->createTemplate($info['template']);
        $result['items'] = $items;
        $result['container'] = $template->render([
            'function_get' => $id . '_get',
            'function_delete' => $id . '_delete',
            'items' => mongotoarray($items),
        ]);
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
