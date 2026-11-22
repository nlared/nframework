<?php
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


/**
 * Convierte el valor de un subcampo según su control: casilla → bool, número → int/float ('' → null).
 */
function embeddedConvert($value, string $class)
{
    if (strcasecmp($class, 'inputCheckBox') === 0) {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    if (strcasecmp($class, 'inputNumber') === 0 && is_string($value)) {
        $value = trim($value);
        return $value === '' ? null : (is_numeric($value) ? $value + 0 : $value);
    }
    return $value;
}

/**
 * true si el arreglo guardado ya tiene la posición $pos (editar) y no hay que agregarla.
 */
function embeddedHasPosition($dataset, string $field, int $pos): bool
{
    $stored = get_data(mongotoArray($dataset->info), $field);
    return is_array($stored) && array_key_exists($pos, $stored);
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

            if ($pos === null) {
                throw new InvalidArgumentException('Posición inválida.');
            }
            // Una casilla sin marcar no se envía: sin esto nunca se podría desmarcar.
            foreach ($fieldRules as $k => $rules) {
                if (strcasecmp((string) ($rules['class'] ?? ''), 'inputCheckBox') === 0 && !array_key_exists($k, $payload)) {
                    $payload[$k] = false;
                }
            }
            $set = [];
            $item = [];
            foreach ($payload as $k => $pfield) {
                // Solo nombres de campo simples: '.' o '$' permitirían escribir en cualquier parte del documento.
                if (!is_string($k) || !preg_match('/^[A-Za-z0-9_\-]+$/', $k) || (!empty($fieldRules) && !array_key_exists($k, $fieldRules))) {
                    continue;
                }
                $rules = $fieldRules[$k] ?? [];
                $validation = validateEmbeddedValue($k, $pfield, $rules);
                if (!$validation['valid']) {
                    throw new InvalidArgumentException($validation['message']);
                }

                $pfield = embeddedConvert($pfield, (string) ($rules['class'] ?? ''));
                $set[$field . '.' . $pos . '.' . $k] = $pfield;
                $item[$k] = $pfield;
                $items[$pos][$k] = $pfield;
            }
            if (empty($set)) {
                throw new InvalidArgumentException('No hay datos para guardar.');
            }
            $filter = ['_id' => ($info['simpleid'] ? $info['_id'] : tomongoid($info['_id']))];
            if (!embeddedHasPosition($dataset, $field, $pos)) {
                // Elemento nuevo: $push crea el arreglo si no existe ($set con 'campo.0.x' crearía un objeto {"0": ...}).
                $m->{$info['database']}->{$info['collection']}->updateOne($filter, ['$push' => [$field => $item]], ['upsert' => true]);
            } else {
                $m->{$info['database']}->{$info['collection']}->updateOne($filter, ['$set' => $set], ['upsert' => true]);
            }
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
