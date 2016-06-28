<?php

/**
 * @package WPDB Unicorn
 */

/*

Plugin Name: WPDB Unicorn
Plugin URI: https://...
Description: Looks out for database changes during development, and creates a snapshot-in-time version of the database schema in a .sql file.
Version: 1.0
Author: Luke Cohen
Author URI: https://github.com/lukex4
License: GPLv2 Copyright (c) 2016 Luke Cohen
Text Domain: wpdb-unicorn
Domain Path: /language

*/


include_once 'lib/exception.php';
include_once 'lib/dbStruct.php';
include_once 'lib/Source.php';

include_once 'helper.php';


class WPDBUnicorn {

  public $debug = 0;

  public $installed = false;

  public $dbCurTables = Array();
  public $dbCurTableStructures = Array();
  public $dbCurIndexes = Array();
  public $dbTablesToTrackData = Array();
  public $dbTableChecksums = Array();

  public $projectFilePath = 'data/.unicorn_project';
  public $projectFilePathTables = 'data/.unicorn_tables';
  public $projectFilePathStrucutres = 'data/.unicorn_structures';
  public $projectFilePathTablesTrack = 'data/.unicorn_tablestotrack';
  public $projectFilePathChecksums = 'data/.unicorn_checksums';
  public $projectFilePathTableData = 'data/.unicorn_tabledata';

  public $structureChangeCount = 0;
  public $deletedTablesCount = 0;
  public $addedTablesCount = 0;
  public $tableDataChangedCount = 0;
  public $dataTrackingTablesCount = 0;
  public $tableChangeCount = 0;

  public $genOptionsDefaults = Array(
    'entireDB'            => false,
    'ver_what_original'   => '',
    'ver_what_new'        => 'both',
    'includeDrops'        => true,
    'prefixTables'        => false,
    'tablePrefix'         => ''
  );

  public $hasCurrentStructure = false;


  /* Constructor */
  public function __construct() {

    if (is_admin()) {

      /* Hooks, filters, etc. */
      add_action('admin_init', array($this, 'adminInit'));
      add_action('admin_menu', array($this, 'adminMenu'));
      add_action('admin_footer', array($this, 'enqueueAssets'));
      add_action('admin_post_tableTrackPrefs', array($this, 'saveDataTrackPrefs'));
      add_action('admin_post_versionFilePrefs', array($this, 'saveVersionFilePrefs'));
      add_action('admin_post_resetButton', array($this, 'doMasterReset'));

      /* At the end */
      add_action('shutdown', array($this, 'onShutdown'));

    }

  }


  /* Admin side initialisation */
  public function adminInit() {

    if (self::checkUnicornInstalled() === true) {
      self::changeAlerter();
    } else {
      self::startupRoutine();
    }

  }


  /* onShutdown */
  public function onShutdown() {

    if (self::checkUnicornInstalled()===false) {
      return;
    }

    /* Generate version .sql file */
    $versSql = self::genVersionSQL();

    $filename = 'version.sql';

    $fileHandler = fopen(dirname(__FILE__) . '/' . $filename, 'w');
    fwrite($fileHandler, $versSql);
    fclose($fileHandler);

    /* Reset some vars we use for processing */
    $this->hasCurrentStructure = false;

  }


  /* Create our admin site menu link */
  public function adminMenu() {

    add_management_page('UNICORN-DB', 'UNICORN-DB', 'manage_options', 'wpdbunicorn-admin-options',
		array($this, 'createAdminPanel'));

  }


  /* Create our main page */
  public function createAdminPanel() {

    /* Update our current structure */
    self::ascertainCurrentStructure();

    /* Pull in the panel include */
    include('inc/panel.php');

  }


  /* Enqueue our CSS and Javascript */
  public function enqueueAssets() {

    /* Pure CSS */
    wp_enqueue_style('pure_css', plugin_dir_url( __FILE__ ) . 'pure-min.css', array(), time());

    /* Our custom styles for the admin panel */
    wp_enqueue_style('unicorn_css', plugin_dir_url( __FILE__ ) . 'wpdb-unicorn.5.css', array(), time());

    wp_register_script('unicorn_js', plugin_dir_url( __FILE__ ) . 'wpdb-unicorn.js', array('jquery'), rand(1,1000), true);
    wp_enqueue_script('unicorn_js');

  }


  /* This resets the entire thing - deleted all tracking files, resets internal variables, etc. */
  public function doMasterReset() {

    if (!empty($_POST) && check_admin_referer('resetButton', 'resetButton_nonce')) {

      /* Delete internal files */
      array_map('unlink', glob(dirname(__FILE__) . '/data/.*', GLOB_BRACE));

      /* Delete version.sql */
      unlink(dirname(__FILE__) . '/version.sql');

      /* Reset internal vars */
      $this->dbCurTables = Array();
      $this->dbCurTableStructures = Array();
      $this->dbCurIndexes = Array();
      $this->dbTablesToTrackData = Array();
      $this->dbTableChecksums = Array();

      $this->structureChangeCount = 0;
      $this->deletedTablesCount = 0;
      $this->addedTablesCount = 0;
      $this->tableDataChangedCount = 0;
      $this->dataTrackingTablesCount = 0;
      $this->tableChangeCount = 0;

      $this->hasCurrentStructure = false;

      $this->installed = false;

      /* Redirect out */
      wp_redirect(admin_url('tools.php?page=wpdbunicorn-admin-options'));

    }

  }


  /* Saves new project vars */
  public function saveProjectVars($newProjectVars) {

    if (gettype($newProjectVars)==='array') {

      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePath, 'w');
      fwrite($fileHandler, serialize($newProjectVars));
      fclose($fileHandler);

      return true;

    } else {
      return false;
    }

  }


  /* Loads our project vars from .unicorn_project */
  public function loadProjectVars() {

    $data = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePath);
    $projectVars = unserialize($data);

    return $projectVars;

  }


  /* Resave original checksums - for when we need to 'reset' the original checksum for a table (i.e when a table is being tracked and then the user opts to stop tracking it, we reset the checksum to match the current, latest state of the table) */
  public function resaveOriginalChecksums($newChecksumsArray) {

    if (gettype($newChecksumsArray) === 'array') {

      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePathChecksums, 'w');
      fwrite($fileHandler, serialize($newChecksumsArray));
      fclose($fileHandler);

      return true;

    } else {
      return false;
    }

  }


  /* Load original table checksums */
  public function loadOriginalChecksums() {

    $data = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePathChecksums);
    $checksums = unserialize($data);

    return $checksums;

  }


  /* Load original tables */
  public function loadOriginalTables() {

    $data = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePathTables);
    $tableList = unserialize($data);

    return $tableList;

  }


  /* Load original table structures */
  public function loadOriginalTableStructures() {

    $data = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePathStrucutres);
    $tableStructures = unserialize($data);

    return $tableStructures;

  }


  /* Load original table data */
  public function loadOriginalTableData() {

    $data = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePathTableData);
    $tablesData = unserialize($data);

    return $tablesData;

  }


  /* Load table data-tracking preferences */
  public function loadTablesToDataTrack() {

    $trackPrefs = file_get_contents(dirname(__FILE__) . '/' . $this->projectFilePathTablesTrack);
    $trackPrefs = unserialize($trackPrefs);

    return $trackPrefs;

  }


  /* Display table listing */
  public function outputTableList() {

    $tableList = self::loadOriginalTables();
    $tablesToDataTrack = self::loadTablesToDataTrack();

    echo '<form method="POST" action="' . admin_url('admin-post.php') . '">';

    echo '<hr /><ul>';

    echo '<li>';
      echo '<div class="sixty floatleft list-head">Table Name</div>';
      echo '<div class="forty floatleft list-head">Track Table Data <a href=""><span class="dashicons dashicons-editor-help" style="text-decoration:none;color:#444;"></span></a></div>';
    echo '</li>';

    foreach ($tableList as $tbl) {

      $checked = '';

      $isTrack = $tablesToDataTrack[$tbl];

      if ($isTrack['tracking'] == 'yes') {
        $checked = ' checked';
      }

      echo '<li>';

        echo '<div class="sixty floatleft"><span class="dashicons dashicons-editor-table"></span>&nbsp;' . $tbl . '</div>';

        echo '<div class="forty floatleft"><input type="checkbox" name="track_' . $tbl . '" id="track_' . $tbl . '" value="yes"' . $checked . ' />';

        if ($isTrack['tracking'] == 'yes') {
          echo '<span class="dashicons dashicons-clock" style="color:#C0C0C0;"></span><em style="color:#C0C0C0;">' . date('d-m-Y H:m', $isTrack['since']) . '</em>';
        }

        echo '</div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>';

      echo '</li>';

    }

    echo '</ul>';

    echo '<p>&nbsp;</p>';

    wp_nonce_field('tableTrackPrefs', 'tableTrackPrefs_nonce');

    echo '<p><input type="hidden" name="action" value="tableTrackPrefs" /><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Data Tracking Preferences &raquo;" /></p>';

    echo '</form>';

  }


  /* 'Exports' a table as INSERT, CREATE, DROP and DELETE commands */
  public function genExportTable($tableName, $return, $preserveTable, $noData, $noStructure, $tablePrefix, $includeDrops) {


    global $wpdb;

    $prefix = false;

    if ($tablePrefix && gettype($tablePrefix)==='string' && strlen($tablePrefix)>0) {
      $prefix = true;
      $tablePrefix = escapeString($tablePrefix);
    }

    if ($this->debug === 1) {
      echo "genExportTable();\r\n";

      $q = 'SELECT * FROM ' . escapeString($tableName);
      print_r($q);
      echo ";\r\n";
    }

    $result = $wpdb->get_results('SELECT * FROM ' . escapeString($tableName), ARRAY_N);
    $numFields = count((array)$result[0]);

    if ($preserveTable && $preserveTable===true) {

      if ($prefix===true) {
        $sql .= 'DELETE FROM ' . $tablePrefix . $tableName . ';';
        $sql .= "\r\n";
      } else {
        $sql .= 'DELETE FROM ' . $tableName . ';';
        $sql .= "\r\n";
      }

    } else {

      if ($noStructure && $noStructure === true) {

      } else {

        if ($this->debug === 1) {
          $q = 'SHOW CREATE TABLE ' . escapeString($tableName);
          print_r($q);
          echo ";\r\n";
        }

        if (!$includeDrops===false) {

          if ($prefix===true) {
            $sql .= 'DROP TABLE IF EXISTS ' . $tablePrefix . $tableName . ';';
          } else {
            $sql .= 'DROP TABLE IF EXISTS ' . $tableName . ';';
          }

        }

        $row2 = $wpdb->get_row('SHOW CREATE TABLE ' . escapeString($tableName), ARRAY_N);
        $thisSql = "\n\n" . $row2[1] . ";\n";

        if ($prefix===true) {
          $thisSql = str_replace(escapeString($tableName), $tablePrefix . escapeString($tableName), $thisSql);
        }

        $sql .= $thisSql;

      }

    }


    if ($noData === true) {

    } else {

      if (count($result)>0) {
        $sql .= "\r\n";
      }

      foreach ($result as $row) {

        if ($prefix===true) {
          $sql .= 'INSERT INTO ' . $tablePrefix . $tableName . ' VALUES(';
        } else {
          $sql .= 'INSERT INTO ' . $tableName . ' VALUES(';
        }

        for($j=0; $j<$numFields; $j++) {

            $row[$j] = addslashes($row[$j]);
            $row[$j] = ereg_replace("\n","\\n",$row[$j]);

            if (isset($row[$j])) {
                $sql .= "'" . $row[$j] . "'";
            } else {
                $sql.= "'";
            }

            if ($j < ($numFields-1)) {
                $sql .= ',';
            }

        }

        $sql .= ");\n";

      }

    }

    $sql.="\n";


    if ($return && $return === true) {
      return $sql;
    } else {
      $fileHandler = fopen(dirname(__FILE__) . '/data/.export_orig_' . $tableName, 'w');
      fwrite($fileHandler, $sql);
      fclose($fileHandler);
    }

  }


  /* Generates a HTML table with the provided array as the populating data source */
  public function printDataTable($data, $dataToComp) {

    if ($data) {

      $comp = false;
      $numFields = count((array)$data[0]);
      $recordOne = (array)$data[0];
      $fieldNames = Array();

      /*

      Are we to run comparison against a different dataset?

      Note: This is a simple like-for-like comparison against the same row in a different dataset.

      */
      if (count($dataToComp) > 0) {
        $comp = true;
      }

      /* Ascertain the field names */
      $x = 0;

      foreach ($recordOne as $field => $key) {
        $fieldNames[$x] = $field;
        $x++;
      }

      /* Start building our HTML table */
      $html = '<table class=\'pure-table data-table\'>';
      $html .= '  <thead>';
      $html .= '    <tr>';

      foreach($fieldNames as $field) {
        $html .= '      <th>' . $field . '</th>';
      }

      $html .= '    </tr>';
      $html .= '  </thead>';

      $html .= '  <tbody>';

      if ($numFields > 0) {

        /* Loop through the dataset */
        $x = 0;

        foreach((array)$data as $row) {

          $noMatch = false;

          $row = (array)$row;
          $row = array_values($row);

          if ($comp === true) {

            $compRow = (array)$dataToComp[$x];
            $compRow = array_values($compRow);

            if ($compRow == $row) {
              $noMatch = false;
            } else {
              $noMatch = true;
            }

            if ($noMatch === true) {
              $html .= '    <tr class=\'redRow\'>';
            } else {
              $html .= '    <tr>';
            }

          }

          $y = 0;

          foreach($fieldNames as $f) {

            $rowOutput = strip_tags($row[$y]);

            if (strlen($rowOutput) > 25) {
              $rowOutput = substr($rowOutput, 0, 25);
              $rowOutput .= '...';
            }

            if ($noMatch === false) {
              $html .= '      <td>';
            } else {

              if ($y === 0) {
                $html .= '      <td><span class=\'dashicons dashicons-warning\' style=\'font-size:14px;\'></span>';
              } else {
                $html .= '      <td>';
              }

            }

            $html .= '        ' . $rowOutput;
            $html .= '      </td>';

            $y++;

          }

          $html .= '    </tr>';

          $x++;

        }

      }

      $html .= '  </tbody>';
      $html .= '</table>';

      echo $html;

    } else {
      return false;
    }

  }


  /* Returns a dataset with the current table data */
  public function returnTableData($tableName) {

    if (isValidTableName($tableName)) {

      global $wpdb;


      if ($this->debug === 1) {
        echo "returnTableData();\r\n";

        $q = 'SELECT * FROM ' . escapeString($tableName);
        print_r($q);
        echo ";\r\n";
      }


      $res = $wpdb->get_results('SELECT * FROM ' . escapeString($tableName));
      return $res;

    } else {
      return false;
    }

  }


  /* Saves a snapshot of a given database table in a flat file */
  public function snapshotTable($tableName) {

    global $wpdb;


    if ($this->debug === 1) {
      echo "snapshotTable();\r\n";

      $q = 'SELECT * FROM ' . escapeString($tableName);
      print_r($q);
      echo ";\r\n";
    }


    $res = $wpdb->get_results('SELECT * FROM ' . escapeString($tableName));

    $dataset = serialize($res);
    $fileHandler = fopen(dirname(__FILE__) . '/data/.data_' . $tableName, 'w');
    fwrite($fileHandler, $dataset);
    fclose($fileHandler);

  }


  /* Load original data for a given table */
  public function loadTableSnapshot($tableName) {

    if (isValidTableName($tableName)) {

      $data = file_get_contents(dirname(__FILE__) . '/data/.data_' . $tableName);
      $tableData = unserialize($data);

      return $tableData;

    } else {
      return false;
    }

  }


  /* The following function is this plugin's bread-and-butter. This produces a .sql file 'version' of the database since we've been tracking it. This includes table ALTER queries, and INSERT INTO queries for data that has changed. */
  public function genVersionSQL() {


    global $wpdb;


    self::ascertainCurrentStructure();


    /* Load version file preferences */
    $projectVars = self::loadProjectVars();
    $projectVars = $projectVars['gen_options'];


    /* Starting generating the SQL version file */
    $sqlFile .= "/* This file was generated by WP-DBVC, a plugin to version control the WordPress database */\r\n\r\n";
    $sqlFile .= "/* SQL generated on: " . date('d-m-Y H:m', time()) . " */\r\n";


    /* Load our SQL generator options */
    $entireDB = $projectVars['entireDB'];
    $ver_what_original = $projectVars['ver_what_original'];
    $ver_what_new = $projectVars['ver_what_new'];
    $includeDrops = $projectVars['includeDrops'];
    $prefixTables = $projectVars['prefixTables'];
    $tablePrefix = $projectVars['tablePrefix'];


    $originalChecksums = self::loadOriginalChecksums();
    $tablesTrackCount = self::dataTrackingTablesCount();


    /* Generate list of tables we will be versioning right now */
    $tablesToVersion = Array();
    $originalTables = self::loadOriginalTables();
    $changedTables = self::tablesWithStructureChanges();

    if ($entireDB===true) {

      /* We're versioning every table in the DB */
      $tablesToVersion = $this->dbCurTables;

    } else {

      /* New tables */
      foreach ($this->dbCurTables as $tbl) {
        if (!in_array($tbl, $originalTables)) {
          /* this is a new table */
          array_push($tablesToVersion, $tbl);
        }
      }

      /* Selected non-new tables */
      foreach ($this->dbTablesToTrackData as $tbl => $key) {
        if (!in_array($tbl, $tablesToVersion)) {
          /* this is a non-new table that we've chosen to track */
          array_push($tablesToVersion, $tbl);
        }
      }

      /* Non-new tables that have had structural changes */
      foreach($changedTables as $tbl) {
        if (!in_array($tbl, $tablesToVersion)) {
          /* this is a non-new table that we've chosen to track */
          array_push($tablesToVersion, $tbl);
        }
      }


    }


    /* Loop through the versioning table list and generate the exports */
    foreach ($tablesToVersion as $tbl) {


      /* Is this table an original one, a new one, or an original one the user elected to track */
      $isOrig = false;
      $isTracked = false;

      if (in_array($tbl, $originalTables)) {
        $isOrig = true;
      }

      if (array_key_exists($tbl, $this->dbTablesToTrackData) || !in_array($tbl, $originalTables) || in_array($tbl, $changedTables)) {
        $isTracked = true;
      }


      /* If entireDB = true, we include this table whether or not it's isOrig or isTracked */
      if ($entireDB===true) {

        /* Get the export for this table */

        /* Structure, data, or both? */
        switch($ver_what_original) {

          case 'structure':
            $sqlFile .= "\r\n/* Structure: $tbl */\r\n";
            $sql = self::genExportTable($tbl, true, false, true, false, $tablePrefix, true);
          break;

          case 'data':
            $sqlFile .= "\r\n/* Data: $tbl */\r\n";
            $sql = self::genExportTable($tbl, true, true, false, true, $tablePrefix, true);
          break;

          case 'both':
            $sqlFile .= "\r\n/* Structure and Data: $tbl */\r\n";
            $sql = self::genExportTable($tbl, true, false, false, false, $tablePrefix, true);
          break;

        }

      } else {

        if ($isTracked===true) {

          /* Get the export */

          /* Structure, data, or both? */
          switch($ver_what_new) {

            case 'structure':
              $sqlFile .= "\r\n/* Structure: $tbl */\r\n";
              $sql = self::genExportTable($tbl, true, false, true, false, $tablePrefix, true);
            break;

            case 'data':
              $sqlFile .= "\r\n/* Data: $tbl */\r\n";
              $sql = self::genExportTable($tbl, true, true, false, true, $tablePrefix, true);
            break;

            case 'both':
              $sqlFile .= "\r\n/* Structure and Data: $tbl */\r\n";
              $sql = self::genExportTable($tbl, true, false, false, false, $tablePrefix, true);
            break;

          }

        }

      }


      $sqlFile .= $sql;


    }


    /* Drops for deleted tables this session */
    if ($includeDrops===true) {

      $deletedTables = self::deletedTablesList();

      foreach($deletedTables as $tbl) {
        $sqlFile .= "\r\n/* Drop Table: $tbl */\r\n";
        $sqlFile .= "DROP TABLE IF EXISTS " . $tbl . ";\r\n\r\n";
      }

    }


    return $sqlFile;

  }


  /* Saves .sql version file preferences */
  public function saveVersionFilePrefs() {

    if (!empty($_POST) && check_admin_referer('versionFilePrefs', 'versionFilePrefs_nonce')) {

      $projectVars = self::loadProjectVars();
      $tracking_since = $projectVars['tracking_since'];

      $newProjectVars = Array(
        'tracking'        => true,
        'tracking_since'  => $tracking_since
      );

      $newGenOptions = $this->genOptionsDefaults;


      if ($_POST['entireDB'] && $_POST['entireDB']==='yes') {
        $newGenOptions['entireDB'] = true;
      } else {
        $newGenOptions['entireDB'] = false;
      }

      if ($_POST['ver_what_original'] && strlen($_POST['ver_what_original']) > 0) {
        $newGenOptions['ver_what_original'] = escapeString($_POST['ver_what_original']);
      }

      if ($_POST['ver_what_new'] && strlen($_POST['ver_what_new']) > 0) {
        $newGenOptions['ver_what_new'] = escapeString($_POST['ver_what_new']);
      }

      if ($_POST['includeDrops'] && $_POST['includeDrops']==='yes') {
        $newGenOptions['includeDrops'] = true;
      } else {
        $newGenOptions['includeDrops'] = false;
      }

      if ($_POST['prefixTables'] && $_POST['prefixTables']==='yes') {
        $newGenOptions['prefixTables'] = true;

        if (!$_POST['tablePrefix']) {
          $newGenOptions['prefixTables'] = false;
          $newGenOptions['tablePrefix'] = '';
        }

        if ($_POST['tablePrefix'] && strlen($_POST['tablePrefix']) > 0) {

          $opt_tablePrefix = $_POST['tablePrefix'];

          if (isValidTableName($opt_tablePrefix)) {
            $opt_tablePrefix = escapeString($opt_tablePrefix);
            $newGenOptions['tablePrefix'] = $opt_tablePrefix;
          } else {
            $newGenOptions['prefixTables'] = false;
            $newGenOptions['tablePrefix'] = '';
          }

        } else {
          $newGenOptions['prefixTables'] = false;
        }

      } else {
        $newGenOptions['prefixTables'] = false;
        $newGenOptions['tablePrefix'] = '';
      }


      $newProjectVars['gen_options'] = $newGenOptions;

      self::saveProjectVars($newProjectVars);

      /* redirect back to our screen */
      wp_redirect(admin_url('tools.php?page=wpdbunicorn-admin-options'));

    }

  }


  /* Saves table data-tracking preferences */
  public function saveDataTrackPrefs() {

    /* Check WP form tokens */

    if (!empty($_POST) && check_admin_referer('tableTrackPrefs', 'tableTrackPrefs_nonce')) {

      /* Load the original tables list, loop through each one, and set its tracking preference according to what we've received from the form */

      $tableList = self::loadOriginalTables();
      $this->dbTablesToTrackData = Array();
      $originalChecksums = self::loadOriginalChecksums();

      foreach ($tableList as $tbl) {

        if ($_POST['track_' . $tbl] == 'yes') {

          $this->dbTablesToTrackData[$tbl] = Array(
            'tracking'  => 'yes',
            'since'     => time()
          );

          /* Take a snapshot-in-time of the data for this table */
          self::snapshotTable($tbl);

          /* Generate export SQL for this table in its original state */
          self::genExportTable($tbl, false);

        } else {

          $this->dbTablesToTrackData[$tbl] = Array(
            'tracking'  => 'no'
          );

          $originalChecksums[$tbl] = self::getTableChecksum($tbl)[0]->Checksum;

        }

      }


      $dbTrackSerialized = serialize($this->dbTablesToTrackData);
      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePathTablesTrack, 'w');
      fwrite($fileHandler, $dbTrackSerialized);
      fclose($fileHandler);


      /* redirect back to our screen */
      wp_redirect(admin_url('tools.php?page=wpdbunicorn-admin-options'));

    }

  }


  /* Various admin notices */
  public function notifyChanges() {
    include('inc/notify_main.php');
  }

  public function notifyEnabled() {
    include('inc/notify_enabled.php');
  }

  public function notifyNotEnabled() {
    include('inc/notify_notenabled.php');
  }


  /* Is this the beginning of development? I.e has our install process been run yet? */
  public function checkUnicornInstalled() {

    if (file_exists(dirname(__FILE__) . '/' . $this->projectFilePath)) {
      return true;
    } else {
      return false;
    }

  }


  /* This function runs at the beginning to  */
  public function startupRoutine() {

    if (self::checkUnicornInstalled() === false) {

      echo 'Unicorn not installed';


      /* Unicorn not yet set up, so let's take a fingerprint of the DB as it stands */
      self::ascertainCurrentStructure();


      /* Save table list to file */
      $tableListSerialized = serialize($this->dbCurTables);
      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePathTables, 'w');
      fwrite($fileHandler, $tableListSerialized);
      fclose($fileHandler);


      /* Save table structures to file */
      $tableStructuresSerialized = serialize($this->dbCurTableStructures);
      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePathStrucutres, 'w');
      fwrite($fileHandler, $tableStructuresSerialized);
      fclose($fileHandler);


      /* Take a cheksum of each table as our baseline data snapshot */
      $this->dbTableChecksums = Array();

      foreach ($this->dbCurTables as $tbl) {
        $this->dbTableChecksums[$tbl] = self::getTableChecksum($tbl)[0]->Checksum;
      }


      /* Save table checksums snapshot to file */
      $fileHandler = fopen(dirname(__FILE__) . '/' . $this->projectFilePathChecksums, 'w');
      fwrite($fileHandler, serialize($this->dbTableChecksums));
      fclose($fileHandler);


      /* Save our project file */
      $projectVars = Array(
        'tracking'        => true,
        'tracking_since'  => time()
      );

      $projectVars['gen_options'] = $this->genOptionsDefaults;
      self::saveProjectVars($projectVars);


      /* Notify that plugin has been installed and enabled */
      $this->installed = true;
      add_action('admin_notices', array($this, 'notifyEnabled'));


    } else {

      echo 'Unicorn installed';

      /* Unicorn is already set up, so now we check to see if there are current outstanding changes to the DB */

    }

  }


  /* Returns a MySQL table CHECKSUM against the given table */
  public function getTableChecksum($tableName) {

    global $wpdb;

    if ($tableName) {


      if ($this->debug === 1) {
        echo "getTableChecksum();\r\n";

        $q = 'CHECKSUM TABLE ' . escapeString($tableName);
        print_r($q);
        echo ";\r\n";
      }


      $res = $wpdb->get_results('CHECKSUM TABLE ' . escapeString($tableName));
      return $res;

    }

  }


  /* Table change watcher (new tables, deleted tables) in the GUI */
  public function outputTableChanges() {

    if (self::checkUnicornInstalled() === false) {
      return;
    } else {
      self::deletedTables();
      self::addedTables();
    }

  }


  /* Alert to changes outside of GUI */
  public function changeAlerter() {

    if (self::checkUnicornInstalled() === false) {
      return;
    } else {

      self::checksumChangeCount();

      $structureChangeCount = self::structureChangeCount();
      $deletedTablesCount = self::deletedTablesCount();
      $addedTablesCount = self::addedTablesCount();
      $tableChangeCount = self::checksumChangeCount();

      if ($structureChangeCount > 0) {
        $this->structureChangeCount = $structureChangeCount;
        add_action('admin_notices', array($this, 'notifyChanges'));
      }

      if ($deletedTablesCount > 0) {
        $this->deletedTablesCount = $deletedTablesCount;
        add_action('admin_notices', array($this, 'notifyChanges'));
      }

      if ($addedTablesCount > 0) {
        $this->addedTablesCount = $addedTablesCount;
        add_action('admin_notices', array($this, 'notifyChanges'));
      }

      if ($tableChangeCount > 0) {
        $this->tableChangeCount = $tableChangeCount;
        add_action('admin_notices', array($this, 'notifyChanges'));
      }

    }

  }


  /* Check for deleted tables */
  public function deletedTablesCount() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $deletedCount = 0;

    foreach ($originalTables as $tbl) {

      if (!in_array($tbl, $this->dbCurTables)) {
        $deletedCount++;
      }

    }

    return $deletedCount;

  }

  public function deletedTablesList() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $deletedTables = Array();

    foreach ($originalTables as $tbl) {

      if (!in_array($tbl, $this->dbCurTables)) {
        array_push($deletedTables, $tbl);
      }

    }

    return $deletedTables;

  }

  public function deletedTables() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $deletedCount = 0;
    $output = '<hr /><ul>';

    $output .= '<li><h3>Deleted tables</h3></li>';

    foreach ($originalTables as $tbl) {

      if (!in_array($tbl, $this->dbCurTables)) {
        $output .= '<li><strong>' . $tbl . '</strong> table was deleted</li>';
        $deletedCount++;
      }

    }

    if ($deletedCount === 0) {
      $output .= '<li class=\'red\'>No tables were deleted</li>';
    }

    $output .= '</ul>';
    echo $output;

  }


  /* Get number of data changes thus far */
  public function dataChangeCount() {

    self::ascertainCurrentStructure();

  }


  /* Check for new tables */
  public function addedTablesCount() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $addedCount = 0;

    foreach ($this->dbCurTables as $tbl) {

      if (!in_array($tbl, $originalTables)) {
        $addedCount++;
      }

    }

    return $addedCount;

  }

  public function addedTablesList() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $addedTables = Array();

    foreach ($this->dbCurTables as $tbl) {
      if (!in_array($tbl, $originalTables)) {
        array_push($addedTables, $tbl);
      }
    }

    return $addedTables;

  }

  public function addedTables() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();

    $addedCount = 0;
    $output = '<hr /><ul>';

    $output .= '<li><h3>Added tables</h3></li>';

    foreach ($this->dbCurTables as $tbl) {

      if (!in_array($tbl, $originalTables)) {
        $output .= '<li><strong>' . $tbl . '</strong> table was added</li>';
        $addedCount++;
      }

    }

    if ($addedCount === 0) {
      $output .= '<li class=\'red\'>No new tables were added</li>';
    }

    $output .= '</ul>';
    echo $output;

  }


  /* Tracking how many tables' data? */
  public function dataTrackingTablesCount() {

    self::ascertainCurrentStructure();
    $this->dataTrackingTablesCount = count($this->dbTablesToTrackData);
    return count($this->dbTablesToTrackData);

  }


  /* Data change count */
  public function checksumChangeCount() {

    if (self::dataTrackingTablesCount()===0) {
      return 0;
    } else {

      self::ascertainCurrentStructure();
      $originalChecksums = self::loadOriginalChecksums();

      $output = Array();
      $checksumChanges = 0;

      foreach ($this->dbTablesToTrackData as $tbl => $key) {

        array_push($output, 'checksum orig: ' . $this->dbTableChecksums[$tbl] . ' cur: ' . $originalChecksums[$tbl]);

        if ($originalChecksums[$tbl] <> $this->dbTableChecksums[$tbl]) {
          $checksumChanges++;
        }

      }

      if ($this->debug == 1) {
        print_r($output);
      }

      return $checksumChanges;

    }

  }


  /* Counts structural changes */
  public function structureChangeCount() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();
    $originalTablesStructures = self::loadOriginalTableStructures();

    $differ = new dbStructUpdater();
    $diffCount = 0;

    foreach ($this->dbCurTables as $tbl) {

      $structOrig = $originalTablesStructures[$tbl];
      $structNow = $this->dbCurTableStructures[$tbl];

      $diffs = $differ->getUpdates($structOrig, $structNow);

      foreach($diffs as $diff){
          $diffCount++;
      }

    }

    return $diffCount;

  }


  /* Returns an array of tables which have had changes made to their structure */
  public function tablesWithStructureChanges() {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();
    $originalTablesStructures = self::loadOriginalTableStructures();

    $differ = new dbStructUpdater();

    $tablesChanged = Array();

    foreach ($this->dbCurTables as $tbl) {

      $structOrig = $originalTablesStructures[$tbl];
      $structNow = $this->dbCurTableStructures[$tbl];

      $diffs = $differ->getUpdates($structOrig, $structNow);

      if (count($diffs)>0) {
        array_push($tablesChanged, $tbl);
      }

    }

    return $tablesChanged;

  }


  /* Outputs structural changes to any tables we're tracking */
  public function outputStructureChanges($return) {

    self::ascertainCurrentStructure();
    $originalTables = self::loadOriginalTables();
    $originalTablesStructures = self::loadOriginalTableStructures();

    $differ = new dbStructUpdater();

    $diffCount = 0;

    $sql = '';

    foreach ($this->dbCurTables as $tbl) {

      $structOrig = $originalTablesStructures[$tbl];
      $structNow = $this->dbCurTableStructures[$tbl];

      $diffs = $differ->getUpdates($structOrig, $structNow);

      foreach($diffs as $diff){

        if ($return && $return===true) {
          $sql .= "$diff;\n\n";
        } else {
          echo "$diff;\n\n";
        }

        $diffCount++;

      }

    }

    if ($diffCount==0 && $return !== true) {
      echo 'No modifications yet';
    }

    if ($return && $return === true) {
      return $sql;
    }

  }


  /* Outputs current structure */
  public function outputOriginalStructure() {

    $originalTables = self::loadOriginalTables();
    $originalTablesStructures = self::loadOriginalTableStructures();

    foreach ($originalTables as $tbl) {

      $structOrig = $originalTablesStructures[$tbl];

      echo "$structOrig\n\n";

    }

  }


  /* Ascertains the current database schema */
  public function ascertainCurrentStructure() {


    /* Check if we already have current structure this run, and exit if so */
    if ($this->hasCurrentStructure === true) {
      return;
    }


    global $wpdb;

    /* 1. Get current tables */
    $res = $wpdb->get_results('SHOW TABLES', ARRAY_N);

    $this->dbCurTables = Array();

    foreach ($res as $tbl) {
      array_push($this->dbCurTables, $tbl[0]);
    }

    /* 2. Get current table structure for each table */
    foreach ($this->dbCurTables as $tbl) {

      /* Get MySQL CREATE command for this table*/


      if ($this->debug === 1) {
        echo "ascertainCurrentStructure();\r\n";

        $q = 'SHOW CREATE TABLE ' . escapeString($tbl);
        print_r($q);
        echo ";\r\n";
      }


      $res = $wpdb->get_results('SHOW CREATE TABLE ' . escapeString($tbl), ARRAY_N);

      foreach ($res as $create) {
        $this->dbCurTableStructures[$tbl] = $create[1];
      }

    }

    /* 3. Get current checksum for each table */
    $this->dbTableChecksums = Array();

    foreach ($this->dbCurTables as $tbl) {
      $this->dbTableChecksums[$tbl] = self::getTableChecksum($tbl)[0]->Checksum;
    }

    /* 4. Get current tables to data track */
    $this->dataTrackingTablesCount = 0;
    $this->dbTablesToTrackData = Array();

    $tablesToTrack = self::loadTablesToDataTrack();

    foreach($tablesToTrack as $key => $tbl) {

      if ($tbl['tracking']=='yes') {
        $this->dbTablesToTrackData[$key] = $tbl;
      }

    }

    $this->dataTrackingTablesCount = count($this->dbTablesToTrackData);

    $this->hasCurrentStructure = true;

  }


}


/* Create the singleton object */
new WPDBUnicorn();


?>
