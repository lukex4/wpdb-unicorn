
<div class="notice notice-error is-dismissible">

    <p>Unicorn has tracked the following changes since activation:</p>

    <p>
      Deleted tables: <?php echo $this->deletedTablesCount; ?><br />
      New tables: <?php echo $this->addedTablesCount; ?><br />
      Table structure changes: <?php echo $this->structureChangeCount; ?><br />
      Tracked data changes: <?php echo $this->tableChangeCount; ?>
    </p>

    <p>
      <strong>version.sql</strong> contains an up-to-date SQL snapshot of your database changes.
    </p>

    <p><a href="<?php echo admin_url('tools.php?page=wpdbunicorn-admin-options'); ?>">Click here</a> for more details</p>

</div>
