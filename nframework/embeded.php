<?
require 'include.php';
function getParentPositions(array $nodes, string $startId): array
{
    $positions = [];
    $current   = $startId;

    while (isset($nodes[$current]['nfparent'])) {
        $parentId = $nodes[$current]['nfparent'];
        if (!isset($nodes[$parentId])) {
            // Si el padre no existe, detenemos el bucle
            break;
        }
        $positions[] = $nodes[$parentId]['pos'] ?? 0;
        $current     = $parentId;
    }

    // Invertimos para que vaya de id1 → id2 (en lugar de id2 → id1)
    return array_reverse($positions);
}


use Twig\Environment;
use Twig\Extension\StringLoaderExtension;

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
    $result['debug'] = $info;


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
    //$result['ss']=$_SESSION['nfembeded'];
    if (!empty($parents)) {
        foreach ($parents as $value) {
            $field = preg_replace('/\$/', $value, $field, 1);
        }
    }



    //$data=mongotoArray($dataset->{$field});
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

    if (isset($_POST['pos'])) {
        $pos = (int)$_POST['pos'];
    }
    if ($_POST['op'] == 'pos') {
        $_SESSION['nfembeded'][$id]['pos'] = $pos;
        $result['items'] = $items;
    } elseif ($_POST['op'] == 'load') {
        $result['item'] = $items[$pos];
        $_SESSION['nfembeded'][$id]['pos'] = $pos;
    } else {
        if ($_POST['op'] == 'update') {
            foreach ($_POST[$info['nameprefix']] as $k => $pfield) {
                $set[$field . '.' . $pos . '.' . $k] = $pfield;
                $items[$pos][$k] = $pfield;
            }
            $result['set'] = $set;
            $m->{$info['database']}->{$info['collection']}->updateOne(['_id' => ($info['simpleid'] ? $info['_id'] : tomongoid($info['_id']))], ['$set' => $set], ['upsert' => true]);
        } elseif ($_POST['op'] == 'delete') {
            unset($items[$pos]);
            $items = array_values($items);
            // $m->{$info['database']}->{$info['collection']}->updateOne(['_id'=>($info['simpleid']?$info['_id']:tomongoid($info['_id']))],['$unset'=>[$field.'.'.$pos=>1]]);
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
header('Content-Type: application/json');
echo json_encode($result);
