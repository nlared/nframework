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

function buildMetroMenu(array $nodes, string $menuClass = '',string $data_role=''): string {
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
		
		if(!empty($node['children'])){
			 $html .= "<li><a href=\"#\" class=\"dropdown-toggle\">$text</a>".buildMetroMenu($node['children'],"d-menu", "dropdown");
		}else{
        	$html .= "<li><a href=\"$link\">$text</a>";
		}

        $html .= "</li>\n";
    }

    $html .= "</ul>\n";
    return $html;
}


function nfMetroMenu( $menuName,  $menuClass = 'h-menu'): string{
    global $m,$config;
	$menu=$m->{$config['sitedb']}->menus->findOne(['name'=>$menuName]);
	if($menu){
    	$json=$menu->code;
	    $nodes = json_decode($json, true);
	    $html = buildMetroMenu($nodes,$menuClass);
	}else{
		$html='';
	}
    return $html;
}

function renderEmbeddedFunctions(string $html): string {
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

function flattenDocument($document, $prefix = '') {
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
