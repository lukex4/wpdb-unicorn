<?php

/*

A collection of helper functions for WP-DBVC.

*/

function isValidTableName($str) {

  if (!$str || gettype($str) !== 'string' || strpos($str, ' ') > 0) {
    return false;
  } else {
    return true;
  }

  /*$allowed = array(".", "-", "_");

  if ( ctype_alnum( str_replace($allowed, '', $str ) ) ) {
    return true;
  } else {
    return false;
  }*/

}

/*

This is a stand-in for mysql_real_escape_string
- WordPress provides $wpdb->prepare, but it's awkward and clunky and replaces some things which it shouldn't, providing not enough control to the developer

*/
function escapeString($value) {
    $return = '';
    for($i = 0; $i < strlen($value); ++$i) {
        $char = $value[$i];
        $ord = ord($char);
        if($char !== "'" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126)
            $return .= $char;
        else
            $return .= '\\x' . dechex($ord);
    }
    return $return;
}

?>