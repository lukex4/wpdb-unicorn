<?php

/*

Uninstalls UNICORN-DB and cleans up a few things

*/

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

function unicornUninstall() {

  /* Delete hidden files used for DB state tracking */
  array_map('unlink', glob(dirname(__FILE__) . '/data/.*', GLOB_BRACE));

  /* Delete version.sql file if it exists */
  unlink(dirname(__FILE__) . '/version.sql');

}

register_uninstall_hook( __FILE__, 'unicornUninstall');

?>