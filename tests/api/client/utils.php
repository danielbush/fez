<?php

namespace fezapi\client;

// Split out an http url into its parts.
//
// @return array Associative array of the parts, see parse_url (php).
// 
// array['params'] = parameters in the query string

function decomposeUri(&$uri)
{
    $parts = \parse_url($uri);
    $parts['params'] = array();
    \parse_str($parts['query'], $parts['params']);
    return $parts;
}

// Look for action-tag in workflow and similar xml output and convert
// to array format.
//
// Useful for crawling the api.

function getActions($xml)
{
    $sxml = new \SimpleXmlElement($xml);
    $result = array();

    // Look for <action> tags...
    $actions = $sxml->xpath('//action'); // array
    if ($actions) {
        foreach ($actions as $action) {
            $a = array();
            $name = (string)$action->name;
            $uri = (string)$action->uri;
            if (!$uri) continue;
            $a['name'] = $name;
            $a['uri'] = $uri;
            $parts = decomposeUri($uri);
            $a['uri_parts'] = $parts;
            $result[] = $a;
        }
    }

    // Look for <workflow> tags...
    $workflows = $sxml->xpath('//workflow'); // array
    if ($workflows) {
        foreach ($workflows as $workflow) {
            $a = array();
            $name = (string)$workflow->w_title;
            $uri = (string)$workflow->w_url;
            if (!$uri) continue;
            $parts = decomposeUri($uri);
            $a['name'] = trim($name);
            $a['uri'] = trim($uri);
            $a['uri_parts'] = $parts;
            $result[] = $a;
        }
    }
    return $result;
}

// Converted indexed array of assoc arrays to associative array using
// value of $key of each assoc array.
//
// This is checked: isset($arr[n][$key])

function byKey($key, &$arr) {
    $result = array();
    foreach ($arr as $i) {
        if(isset($i[$key])) {
            $result[$i[$key]] = $i;
        }
    }
    return $result;
}


