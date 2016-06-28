# UNICORN-DB for WordPress

UNICORN provides live database change tracking for WordPress developers. Once installed, UNICORN tracks changes to the database structure, as well as selected changes in the content.

## Use cases

When multiple developers are working on the same WordPress project, database changes have to be managed carefully - if one developer makes a database structure change, all other developers must be made aware of that change. This is where UNICORN is useful.

Equally, when a major WordPress version is released which has its own database changes, it may be desirable to test those changes in a local environment before rolling out to production. UNICORN would track any database changes made by WordPress - or a plugin - and would provide a version.sql file with SQL commands which, when executed on the production database, would ensure production has the same structure as the latest version in development.

##### Don't use on production WordPress

UNICORN is not appropriate on a production WordPress site, as it is likely to track a lot of data (if configured that way), and as the UNICORN generator is run every time WordPress runs, is likely to become cumbersome and a performance issue.

## Activating UNICORN

All you have to do is install the plugin. The default settings will already be enabled, and tracking will begin immediately.

*For more information on configurable settings, see 'Default and configurable settings' below.*

##### File permissions

UNICORN needs to be able to write to and in its own directory and various sub-directories. It should never try to write above, or access files outside of, its working directory. UNICORN uses hidden files in the /data sub-directory, to manage various aspects of its internal state as well as the state of your database.

## version.sql

version.sql is automatically generated, and is subject to the options available in the UNICORN panel under 'version.sql Options'. version.sql will always contain the latest snapshot of changes made to the database.

Depending on the options you choose, version.sql would typically contain the following types of SQL query:

- ALTER TABLE ...
- CREATE TABLE ...
- DROP TABLE ...
- INSERT INTO ...

At the end of a development cycle where database changes have been made, version.sql can be run against the WordPress database at production, staging or another developer's install. When run this way, the 'target' database will be brought up-to-date with the state of your database in development.

version.sql lives in the root directory of the UNICORN plugin, i.e:

> wp-content/plugins/wpdb-unicorn/version.sql

## Default and configurable settings

You can view and change UNICORN settings in its WordPress panel. The panel is available under Tools > UNICORN-DB.

By default, UNICORN tracks the following:

- Newly created tables
- Deleted tables
- Structure alterations of existing tables.

There are other settings available, which tell UNICORN to:

- Track both data and structure in entire database, not just new tables
- Track both data and structure in new tables and selected original tables
- Track just structure in entire database
- Track just structure in new tables and selected original tables
- Track just data in entire database
- Track just data in new tables and selected original tables
- Custom prefix any table names in version.sql

##### Table prefixes

You can tell UNICORN to prefix any table names in version.sql. This might be useful when you want to test out the changes on another database without affecting production or other important databases.

In the UNICORN-DB panel inside WordPress, look for the option:

> Prefix table names in the version.sql file

The tickbox must be checked as well as a prefix entered in the text box in order to enable table prefixes.

## Reset tracking

UNICORN can reset its state tracking completely, however this action can't be undone.

If you wish to reset database state tracking, expunging all record of changes, click the 'Reset Now' button in the UNICORN-DB panel.
