
<div class='wrap'>

  <?php screen_icon(); ?>

  <h1>UNICORN-DB: WordPress Database Version Control</h1>


  <hr />


  <div class='collapsibleInfo' style='height:auto;'>

    <h1>UNICORN-DB is installed &amp; active</h1>

    <h3><span class="dashicons dashicons-yes"></span>&nbsp;Your database changes are being tracked, so you can relax and get on with your development</h3>

    <h3><span class="dashicons dashicons-yes"></span>&nbsp;version.sql always contains the latest snapshot of your database changes</h3>

    <h3><span class="dashicons dashicons-yes"></span>&nbsp;You can change what UNICORN tracks via the options below</h3>

    <h3><span class="dashicons dashicons-yes"></span>&nbsp;By default, only structure alterations and deleted and created tables are tracked</h3>

    <h3><span class="dashicons dashicons-yes"></span>&nbsp;At the end of your development cycle, you'll have a version.sql file containing structure, data, or both, of the whole DB, just the tables you choose, or new tables only</h3>

    <hr />

    <h3><span class="dashicons dashicons-warning"></span>&nbsp;To reset tracking, click the button below</h3>

    <p style="color:#FF0000;"><strong>WARNING: Resetting data tracking will erase all records of tracked database changes!<br />This action cannot be undone.</strong></p>

    <?php

    echo '<form method="POST" action="' . admin_url('admin-post.php') . '">';

    wp_nonce_field('resetButton', 'resetButton_nonce');

    ?>

      <p>
        <input type="hidden" name="action" value="resetButton" />
        <input type="submit" name="submit" id="submit" class="button button-primary" value="Reset Now &raquo;" />
      </p>

    </form>

  </div>


  <hr />


  <h1>version.sql</h1>
  <div class='collapsibleInfo'>

    <?php

    echo '<form method="POST" action="' . admin_url('admin-post.php') . '">';

      $projectVars = self::loadProjectVars();

      $opt_entireDB = $projectVars['gen_options']['entireDB'];
      $opt_ver_what_original = $projectVars['gen_options']['ver_what_original'];
      $opt_ver_what_new = $projectVars['gen_options']['ver_what_new'];
      $opt_includeDrops = $projectVars['gen_options']['includeDrops'];
      $opt_prefixTables  = $projectVars['gen_options']['prefixTables'];
      $opt_tablePrefix = $projectVars['gen_options']['tablePrefix'];

    ?>

    <div class='rowWrapper'>

      <div class='panel thirty-five'>
        <h3>Options</h3>


        <!-- group of options -->
        <hr />

          <h4>General</h4>

          <p><em>By default, Unicorn versions all new tables and all selected tables (as above). You can override this, and tell Unicorn to version the entire database:</em></p>

          <ul>

            <li>
              <div class="eighty floatleft">
                <span class="dashicons dashicons-arrow-right"></span>&nbsp;Version entire database
              </div>
              <div class="twenty floatleft center">
                <input type="checkbox" name="entireDB" id="entireDB" value="yes" <?php if ($opt_entireDB === true) { echo 'checked'; } ?>/>
              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

            <li>
              <div class="hundred floatleft">
                <span class="dashicons dashicons-arrow-right"></span>&nbsp;<strong>Just structure, just data, or both</strong>
              </div>
              <div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

            <li id="originalTablesOption">
              <div class="sixty floatleft">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-- Original tables:
              </div>
              <div class="forty floatleft center">

                <label for="structure">Structure</label>
                <input type="radio" name="ver_what_original" id="structure" value="structure" <?php if ($opt_ver_what_original=='structure') {echo ' checked';} ?>/>

                <label for="data">Data</label>
                <input type="radio" name="ver_what_original" id="data" value="data" <?php if ($opt_ver_what_original=='data') {echo ' checked';} ?>/>

                <label for="both">Both</label>
                <input type="radio" name="ver_what_original" id="both" value="both" <?php if ($opt_ver_what_original=='both') {echo ' checked';} ?>/>

              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

            <li>
              <div class="sixty floatleft">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-- New tables and selected original tables:
              </div>
              <div class="forty floatleft center">
                <label for="structure">Structure</label>
                <input type="radio" name="ver_what_new" id="structure" value="structure" <?php if ($opt_ver_what_new=='structure') {echo ' checked';} ?>/>

                <label for="data">Data</label>
                <input type="radio" name="ver_what_new" id="data" value="data" <?php if ($opt_ver_what_new=='data') {echo ' checked';} ?>/>

                <label for="both">Both</label>
                <input type="radio" name="ver_what_new" id="both" value="both" <?php if ($opt_ver_what_new=='both') {echo ' checked';} ?>/>
              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

          </ul>

        <!-- / -->


        <!-- group of options -->
        <hr />

          <h4>Deleted Tables</h4>

          <ul>

            <li>
              <div class="eighty floatleft">
                <span class="dashicons dashicons-arrow-right"></span>&nbsp;Include DROP queries for tables deleted this session
              </div>
              <div class="twenty floatleft center">
                <input type="checkbox" name="includeDrops" id="includeDrops" value="yes" <?php if ($opt_includeDrops === true) { echo 'checked'; } ?>/>
              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

          </ul>

        <!-- / -->


        <!-- group of options -->
        <hr />

          <h4>Table Prefixes</h4>

          <ul>

            <li>
              <div class="eighty floatleft">
                <span class="dashicons dashicons-arrow-right"></span>&nbsp;Prefix table names in the version.sql file
              </div>
              <div class="twenty floatleft center">
                <input type="checkbox" name="prefixTables" id="prefixTables" value="yes" <?php if ($opt_prefixTables === true) { echo 'checked'; } ?>/>
              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

            <li>
              <div class="hundred floatleft center">
                  <input type="text" name="tablePrefix" id="tablePrefix" placeholder="Enter a prefix" value="<?php echo $opt_tablePrefix; ?>" />
              </div><div style="clear:both;font-size:4px;height:8px;">&nbsp;</div>
            </li>

          </ul>

          <hr />

        <!-- / -->

        <?php

        wp_nonce_field('versionFilePrefs', 'versionFilePrefs_nonce');

        ?>

        <p>&nbsp;</p>

        <p>
          <input type="hidden" name="action" value="versionFilePrefs" />
          <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Options &amp; Re-generate Version File &raquo;" />
        </p>

      </div>

      <div class='panel five'>
        &nbsp;
      </div>

      <div class='panel sixty'>
        <h3>Preview</h3>

        <textarea style="width:100%;height:95%;min-height:500px;display:block;"><?php echo self::genVersionSQL(); ?></textarea>

      </div>

    </div>

    </form>

  </div>


  <hr />


  <h1>Original Structure</h1>
  <div class='collapsibleInfo'>

    <div class='rowWrapper'>

      <div class='panel thirty-five'>
        <h3>Tables</h3>

        <p style="text-align:justify;"><em>The structure of these tables are tracked by default, but not the data. If you wish to enable data tracking for a specific table, tick the relevant box and click 'Save'. Data change tracking will only begin when you enable it for a given table.</em></p>

        <?php self::outputTableList(); ?>

      </div>

      <div class='five'>
        &nbsp;
      </div>

      <div class='panel sixty'>
        <h3>Table Structure</h3>

        <textarea style="width:100%;height:95%;display:block;"><?php self::outputOriginalStructure(); ?></textarea>

      </div>

    </div>

  </div>


  <hr />


  <h1>Detected Changes: Structure</h1>
  <div class='collapsibleInfo'>

    <div class='rowWrapper'>

      <div class='panel thirty-five'>
        <h3>Tables</h3>

        <?php

        $projectVars = self::loadProjectVars();

        $tracking_since = $projectVars['tracking_since'];
        $tracking_since = date('d-m-Y H:m', $tracking_since);

        ?>

        <p style="text-align:justify;"><em>UNICORN-DB has been tracking changes to your database's table structure since <?php        echo $tracking_since; ?>.</em></p>

        <?php self::outputTableChanges(); ?>

      </div>

      <div class='panel five'>
        &nbsp;
      </div>

      <div class='panel sixty'>

        <h3>Table Structure Modifications <a href=""><span class="dashicons dashicons-editor-help" style="text-decoration:none;color:#444;"></span></a></h3>

        <textarea style="width:100%;height:400px;"><?php self::outputStructureChanges(); ?></textarea>

      </div>

    </div>

  </div>


  <hr />


  <h1>Detected Changes: Data</h1>
  <div class='collapsibleInfo'>

    <?php

    $tablesTrackCount = self::dataTrackingTablesCount();

    ?>

    <h3>Currently tracking data changes in <?php echo $tablesTrackCount; ?> tables.</h3>

    <hr />

    <?php

    if ($tablesTrackCount > 0) {

      $originalChecksums = self::loadOriginalChecksums();

      foreach ($this->dbTablesToTrackData as $key => $tbl) {

        if ($tbl['tracking'] == 'yes') {

          $dataOrig = self::loadTableSnapshot($key);
          $dataCur = self::returnTableData($key);

          /* Do checksums match? */
          $checksumMatch = false;

          $checksumOrig = $originalChecksums[$key];
          $checksumCur = self::getTableChecksum($key);

          $cur = $checksumCur[0]->Checksum;

          if ($cur === $checksumOrig) {
            $checksumMatch = true;
          }

          ?>

          <h3 style='width:100%;'<?php if ($checksumMatch === false) { echo ' class="redRow"'; } ?>><span class="dashicons dashicons-editor-table"></span>&nbsp;<?php if ($checksumMatch === false) { echo '<span class="dashicons dashicons-warning"></span>&nbsp;'; } ?><?php echo $key; ?></h3>

          <div class='rowWrapper'>

            <div class='panel fifty'>
              <h3 class='hlight'>Data - Original</h3>

              <?php

              /* Outputs a HTML table with the original data */
              self::printDataTable($dataOrig, $dataCur);

              ?>

            </div>

            <div class='panel fifty'>
              <h3 class='hlight'>Data - Current</h3>

              <?php

              /* Outputs a HTML table with the current data */
              self::printDataTable($dataCur, $dataOrig);

              ?>

            </div>

          </div>

          <hr />

          <?php

        }

      }


    }

    ?>

  </div>




</div>
