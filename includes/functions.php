<?php

function assignArrayByPath(&$arr, $path, $value, $separator = '.')
{
    $keys = explode($separator, $path);
    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }
    $arr = $value;
}
function remove_trailing_separator($path)
{
    return rtrim($path, DIRECTORY_SEPARATOR);
}
function array_diff_recursive($array1, $array2)
{
    $result = [];
    foreach ($array1 as $key => $value) {
        if (is_array($value)) {
            if (! isset($array2[$key]) || ! is_array($array2[$key])) {
                $result[$key] = $value;
            } else {
                $recursiveDiff = array_diff_recursive($value, $array2[$key]);
                if (! empty($recursiveDiff)) {
                    $result[$key] = $recursiveDiff;
                }
            }
        } elseif (! array_key_exists($key, $array2) || $array2[$key] !== $value) {
            $result[$key] = $value;
        }
    }

    return $result;
}
function unsetNestedKey(&$array, $path)
{
    $keys = explode('\\', $path);
    $temp = &$array;

    foreach ($keys as $key) {
        if (! isset($temp[$key])) {
            return; // Stop if the path doesn't exist
        }
        $parent = &$temp;
        $temp = &$temp[$key]; // Traverse deeper
    }

    unset($parent[$key]); // Unset last key
}
function addVarToGarbage($key, $time)
{
    $_SESSION['_gc_tracker'][$key] = $time;
}

function ifset($array, $key): mixed
{
    return isset($array[$key]) ? $array[$key] : null;
}

function buildMetroMenu(array $nodes, string $menuClass = '', string $data_role = ''): string
{
    $html = $menuClass ? "<ul class=\"$menuClass\" data-role=\"$data_role\">\n" : "<ul>\n";

    foreach ($nodes as $node) {
        $text = htmlspecialchars($node['text']);
        $link = htmlspecialchars($node['data']['link'] ?? '#');
        $onfunction = $node['data']['onfunction'] ?? null;

        // Evaluate condition if present
        $shouldRender = true;
        if ($onfunction) {
            try {
                $shouldRender = eval("$onfunction");
            } catch (Throwable $e) {
                $shouldRender = false;
            }
        }

        if (!$shouldRender) continue;

        if (!empty($node['children'])) {
            $html .= "<li><a href=\"#\" class=\"dropdown-toggle\">$text</a>" . buildMetroMenu($node['children'], "d-menu", "dropdown");
        } else {
            $html .= "<li><a href=\"$link\">$text</a>";
        }

        $html .= "</li>\n";
    }

    $html .= "</ul>\n";
    return $html;
}


function nfMetroMenu($menuName,  $menuClass = 'h-menu'): string
{
    global $m, $config;
    $menu = $m->{$config['sitedb']}->menus->findOne(['name' => $menuName]);
    if ($menu) {
        $json = $menu->code;
        $nodes = json_decode($json, true);
        $html = buildMetroMenu($nodes, $menuClass);
    } else {
        $html = '';
    }
    return $html;
}

function renderEmbeddedFunctions(string $html): string
{
    return preg_replace_callback('/{{\s*(\w+)\((.*?)\)\s*}}/', function ($matches) {
        $funcName = $matches[1];
        $args = array_map('trim', explode(',', $matches[2]));

        // Remove quotes from string arguments
        $args = array_map(function ($arg) {
            return trim($arg, "'\" ");
        }, $args);

        // Check if function exists and call it
        if (function_exists($funcName)) {
            return call_user_func_array($funcName, $args);
        }

        return "[undefined function: $funcName]";
    }, $html);
}

function normalizeBsonValue($value): mixed
{
    switch (true) {
        case $value instanceof MongoDB\BSON\UTCDateTime:
            return $value->toDateTime()->format(DATE_ATOM);
        case $value instanceof MongoDB\BSON\ObjectId:
            return (string) $value;
        case $value instanceof MongoDB\BSON\Binary:
            return base64_encode($value->getData());
        case $value instanceof MongoDB\BSON\Regex:
            return $value->getPattern();
        case $value instanceof MongoDB\BSON\Decimal128:
            return (string) $value;
        case $value instanceof MongoDB\BSON\Javascript:
            return $value->getCode();
        case $value instanceof MongoDB\BSON\Timestamp:
            return "Timestamp({$value->getTimestamp()}, {$value->getIncrement()})";
        case $value instanceof MongoDB\BSON\MinKey:
            return 'MinKey';
        case $value instanceof MongoDB\BSON\MaxKey:
            return 'MaxKey';
        default:
            return $value;
    }
}

function flattenDocument($document, $prefix = '')
{
    $flat = [];
    foreach ($document as $key => $value) {
        $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";

        if (is_array($value) || is_object($value)) {
            $flat += flattenDocument((array)$value, $fullKey);
        } else {
            $flat[$fullKey] = normalizeBsonValue($value);
        }
    }
    return $flat;
}


/**
 * @param mixed $doc 
 * @param mixed $query 
 * @return bool 
 *  True if the document matches the query, false otherwise
 * 
 * Supported operators:
 *  - $and
 *  - $or
 *  - $nor
 *  - $gt
 *  - $lt
 *  - $gte
 *  - $lte
 *  - $eq
 *  - $ne
 *  - $in
 *  - $nin
 *  - $regex
 *  - $exists
 *  - $size
 *  - $type (only basic types: string, integer, array, object, boolean, double)
 *  - $options (for regex)      
 */
function matchesQuery($doc, $query)
{
    // Handle logical operators first
    if (isset($query['$and'])) {
        foreach ($query['$and'] as $subQuery) {
            if (!matchesQuery($doc, $subQuery)) {
                return false;
            }
        }
        unset($query['$and']);
    }

    if (isset($query['$or'])) {
        $orMatched = false;
        foreach ($query['$or'] as $subQuery) {
            if (matchesQuery($doc, $subQuery)) {
                $orMatched = true;
                break;
            }
        }
        if (!$orMatched) return false;
        unset($query['$or']);
    }

    if (isset($query['$nor'])) {
        foreach ($query['$nor'] as $subQuery) {
            if (matchesQuery($doc, $subQuery)) {
                return false;
            }
        }
        unset($query['$nor']);
    }

    // Handle field-level queries
    foreach ($query as $field => $condition) {
        if (!matchesField($doc, $field, $condition)) {
            return false;
        }
    }

    return true;
}

function matchesField($doc, $field, $condition)
{
    // Get field value (supports dot notation)
    $fieldValue = getNestedValue($doc, $field);

    // Handle direct value comparison
    if (!is_array($condition)) {
        return $fieldValue === $condition;
    }

    // Handle operators
    foreach ($condition as $op => $value) {
        switch ($op) {
            case '$gt':
                if (!($fieldValue > $value)) return false;
                break;
            case '$lt':
                if (!($fieldValue < $value)) return false;
                break;
            case '$gte':
                if (!($fieldValue >= $value)) return false;
                break;
            case '$lte':
                if (!($fieldValue <= $value)) return false;
                break;
            case '$eq':
                if ($fieldValue !== $value) return false;
                break;
            case '$ne':
                if ($fieldValue === $value) return false;
                break;
            case '$in':
                if (!in_array($fieldValue, $value, true)) return false;
                break;
            case '$nin':
                if (in_array($fieldValue, $value, true)) return false;
                break;
            case '$regex':
                $pattern = '/' . str_replace('/', '\/', $value) . '/';
                if (isset($condition['$options'])) {
                    $pattern .= $condition['$options'];
                }
                if (!preg_match($pattern, (string)$fieldValue)) return false;
                break;
            case '$exists':
                $exists = hasNestedKey($doc, $field);
                if ($value && !$exists) return false;
                if (!$value && $exists) return false;
                break;
            case '$size':
                if (!is_array($fieldValue) || count($fieldValue) !== $value) return false;
                break;
            case '$type':
                if (gettype($fieldValue) !== $value) return false;
                break;
            case '$options':
                // Skip options, handled by $regex
                break;
            default:
                // Unknown operator
                return false;
        }
    }

    return true;
}

function getNestedValue($array, $key)
{
    if (strpos($key, '.') === false) {
        return $array[$key] ?? null;
    }

    $keys = explode('.', $key);
    $current = $array;

    foreach ($keys as $k) {
        if (!is_array($current) || !isset($current[$k])) {
            return null;
        }
        $current = $current[$k];
    }

    return $current;
}

function hasNestedKey($array, $key)
{
    if (strpos($key, '.') === false) {
        return isset($array[$key]);
    }

    $keys = explode('.', $key);
    $current = $array;

    foreach ($keys as $k) {
        if (!is_array($current) || !isset($current[$k])) {
            return false;
        }
        $current = $current[$k];
    }

    return true;
}


function fixSingleQuery($query)
{
    if (isset($query['$and']) && count($query['$and']) == 1) {
        return $query['$and'][0];
    } elseif (isset($query['$or']) && count($query['$or']) == 1) {
        return $query['$or'][0];
    }
    return $query;
}

/*
$rules[]=fixSingleQuery(json_decode('{"$and":[{"path":{"$regex":"wp-includes","$options":"i"}}]}',true));
$rules[]=fixSingleQuery(json_decode('{"$and":[{"host":{"$regex":"localhost","$options":"i"}}]}',true));
$rules[]=fixSingleQuery(json_decode('{"$and":[{"host":"localhost"}]}',true));

$query=fixSingleQuery(['$or'=>$rules]);*/




function nflogAttempt($ip, $limit = 5, $blockTime = 300)
{
    global $m, $config;
    $collection = $m->{$config['sitedb']}->nf_attempts;
    $now = new MongoDB\BSON\UTCDateTime(time() * 1000);
    $record = $collection->findOne(['ip' => $ip]);
    if ($record && isset($record['blocked_until']) && $record['blocked_until'] > $now) {
        return false; // Bloqueado
    }

    if (!$record) {
        $collection->insertOne([
            'ip' => $ip,
            'count' => 1,
            'last_attempt' => $now
        ]);
        return true;
    }

    $count = $record['count'] + 1;

    if ($count > $limit) {
        $blockedUntil = new MongoDB\BSON\UTCDateTime((time() + $blockTime) * 1000);
        $collection->updateOne(
            ['ip' => $ip],
            ['$set' => ['count' => $count, 'blocked_until' => $blockedUntil, 'last_attempt' => $now]]
        );
        return false;
    }

    $collection->updateOne(
        ['ip' => $ip],
        ['$set' => ['count' => $count, 'last_attempt' => $now]]
    );

    return true;
}

function encryptSessionId($sessionId, $key)
{
    $iv = openssl_random_pseudo_bytes(16); // vector de inicialización
    $encrypted = openssl_encrypt($sessionId, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted); // concatenamos IV + datos cifrados
}

function decryptSessionId($encryptedData, $key)
{
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
function isValidSession($encryptedSessionId, $key)
{
    if (empty($encryptedSessionId) || empty($key)) {
        return false;
    }

    $sessionId = decryptSessionId($encryptedSessionId, $key);
    if ($sessionId === false) {
        return false; // Desencriptación fallida
    }

    session_id($sessionId);
    session_start();

    return session_status() === PHP_SESSION_ACTIVE;
}
