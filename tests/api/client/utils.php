<?php

namespace fezapi\client;

// Split out an http url into its parts.
//
// @return array Associative array of the parts, see parse_url (php).
// 
// array['params'] = parameters in the query string

function decompose_uri(&$uri)
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

function get_actions($xml)
{
    $sxml = new \SimpleXmlElement($xml);
    $result = array();
    $actions = $sxml->xpath('//' . 'action'); // array
    $by_name = array();
    foreach ($actions as $action) {
        $action = (array)$action;
        $a = array();
        foreach ($action as $k => $v) {
            $v = (string)$v;
            $a[$k] = $v;
            if ($k == 'uri') {
                $parts = decompose_uri($v);
                $a['uri_parts'] = $parts;
            }
        }
        $result[] = $a;
    }
    return $result;
}

// Converted indexed array of assoc arrays to associative array using
// value of $key of each assoc array.
//
// This is checked: isset($arr[n][$key])

function by_key($key, &$arr) {
    $result = array();
    foreach ($arr as $i) {
        if(isset($i[$key])) {
            $result[$i[$key]] = $i;
        }
    }
    return $result;
}


