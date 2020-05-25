<?php
use Symfony\Component\Console\Output\OutputInterface;
use Glpi\Console\Application;

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Migration Class
 *
 * @since 0.80
**/
class Migration {

   private   $change    = [];
   private   $fulltexts = [];
   private   $uniques   = [];
   protected $version;
   private   $deb;
   private   $lastMessage;
   private   $log_errors = 0;
   private   $current_message_area_id;
   private   $queries = [
      'pre'    => [],
      'post'   => []
   ];

   /**
    * List (name => value) of configuration options to add, if they're missing
    * @var array
    */
   private $configs = [];

   /**
    * Configuration context
    * @var string
    */
   private $context = 'core';

   const PRE_QUERY = 'pre';
   const POST_QUERY = 'post';

   /**
    * Output handler to use. If not set, output will be directly echoed on a format depending on
    * execution context (Web VS CLI).
    *
    * @var OutputInterface|null
    */
   protected $output_handler;

   /**
    * @param integer $ver Version number
   **/
   function __construct($ver) {

      $this->deb = time();
      $this->version = $ver;

      global $application;
      if ($application instanceof Application) {
         // $application global variable will be available if Migration is called from a CLI console command
         $this->output_handler = $application->getOutput();
      }
   }

   /**
    * Set version
    *
    * @since 0.84
    *
    * @param integer $ver Version number
    *
    * @return void
   **/
   function setVersion($ver) {

      $this->flushLogDisplayMessage();
      $this->version = $ver;
      $this->addNewMessageArea("migration_message_$ver");
   }


   /**
    * Add new message
    *
    * @since 0.84
    *
    * @param string $id Area ID
    *
    * @return void
   **/
   function addNewMessageArea($id) {

      if (!isCommandLine() && $id != $this->current_message_area_id) {
         $this->current_message_area_id = $id;
         echo "<div id='".$this->current_message_area_id."'></div>";
      }

      $this->displayMessage(__('Work in progress...'));
   }


   /**
    * Flush previous displayed message in log file
    *
    * @since 0.84
    *
    * @return void
   **/
   function flushLogDisplayMessage() {

      if (isset($this->lastMessage)) {
         $tps = Html::timestampToString(time() - $this->lastMessage['time']);
         $this->log($tps . ' for "' . $this->lastMessage['msg'] . '"', false);
         unset($this->lastMessage);
      }
   }


   /**
    * Additional message in global message
    *
    * @param string $msg text  to display
    *
    * @return void
   **/
   function displayMessage($msg) {

      $now = time();
      $tps = Html::timestampToString($now-$this->deb);

      $this->outputMessage("{$msg} ({$tps})", null, $this->current_message_area_id);

      $this->flushLogDisplayMessage();
      $this->lastMessage = ['time' => time(),
                            'msg'  => $msg];
   }


   /**
    * Log message for this migration
    *
    * @since 0.84
    *
    * @param string  $message Message to display
    * @param boolean $warning Is a warning
    *
    * @return void
   **/
   function log($message, $warning) {

      if ($warning) {
         $log_file_name = 'warning_during_migration_to_'.$this->version;
      } else {
         $log_file_name = 'migration_to_'.$this->version;
      }

      // Do not log if more than 3 log error
      if ($this->log_errors < 3
         && !Toolbox::logInFile($log_file_name, $message . ' @ ', true)) {
         $this->log_errors++;
      }
   }


   /**
    * Display a title
    *
    * @param string $title Title to display
    *
    * @return void
   **/
   function displayTitle($title) {
      $this->outputMessage($title, 'title');
   }


   /**
    * Display a Warning
    *
    * @param string  $msg Message to display
    * @param boolean $red Displays with red class (false by default)
    *
    * @return void
   **/
   function displayWarning($msg, $red = false) {
      $this->outputMessage($msg, $red ? 'warning' : 'strong');
      $this->log($msg, true);
   }


   /**
    * Define field's format
    *
    * @param string  $type          can be bool, char, string, integer, date, datetime, text, longtext or autoincrement
    * @param string  $default_value new field's default value,
    *                               if a specific default value needs to be used
    * @param boolean $nodefault     No default value (false by default)
    *
    * @return string
   **/
   private function fieldFormat($type, $default_value, $nodefault = false) {

      $format = '';
      switch ($type) {
         case 'bool' :
         case 'boolean' :
            $format = "TINYINT(1) NOT NULL";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format .= " DEFAULT '0'";
               } else if (in_array($default_value, ['0', '1'])) {
                  $format .= " DEFAULT '$default_value'";
               } else {
                  trigger_error(__('default_value must be 0 or 1'), E_USER_ERROR);
               }
            }
            break;

         case 'char' :
         case 'character' :
            $format = "CHAR(1)";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format .= " DEFAULT NULL";
               } else {
                  $format .= " NOT NULL DEFAULT '$default_value'";
               }
            }
            break;

         case 'str' :
         case 'string' :
            $format = "VARCHAR(255) COLLATE utf8_unicode_ci";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format .= " DEFAULT NULL";
               } else {
                  $format .= " NOT NULL DEFAULT '$default_value'";
               }
            }
            break;

         case 'int' :
         case 'integer' :
            $format = "INT(11) NOT NULL";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format .= " DEFAULT '0'";
               } else if (is_numeric($default_value)) {
                  $format .= " DEFAULT '$default_value'";
               } else {
                  trigger_error(__('default_value must be numeric'), E_USER_ERROR);
               }
            }
            break;

         case 'date' :
            $format = "DATE";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format.= " DEFAULT NULL";
               } else {
                  $format.= " DEFAULT '$default_value'";
               }
            }
            break;

         case 'datetime' :
            $format = "DATETIME";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format.= " DEFAULT NULL";
               } else {
                  $format.= " DEFAULT '$default_value'";
               }
            }
            break;

         case 'text' :
            $format = "TEXT COLLATE utf8_unicode_ci";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format.= " DEFAULT NULL";
               } else {
                  $format.= " NOT NULL DEFAULT '$default_value'";
               }
            }
            break;

         case 'longtext' :
            $format = "LONGTEXT COLLATE utf8_unicode_ci";
            if (!$nodefault) {
               if (is_null($default_value)) {
                  $format .= " DEFAULT NULL";
               } else {
                  $format .= " NOT NULL DEFAULT '$default_value'";
               }
            }
            break;

         // for plugins
         case 'autoincrement' :
            $format = "INT(11) NOT NULL AUTO_INCREMENT";
            break;

         default :
            // for compatibility with old 0.80 migrations
            $format = $type;
            break;
      }
      return $format;
   }


   /**
    * Add a new GLPI normalized field
    *
    * @param string $table   Table name
    * @param string $field   Field name
    * @param string $type    Field type, @see Migration::fieldFormat()
    * @param array  $options Options:
    *                         - update    : if not empty = value of $field (must be protected)
    *                         - condition : array of where conditions, if needed
    *                         - value     : default_value new field's default value, if a specific default value needs to be used
    *                         - nodefault : do not define default value (default false)
    *                         - comment   : comment to be added during field creation
    *                         - first     : add the new field at first column
    *                         - after     : where adding the new field
    *                         - null      : value could be NULL (default false)
    *
    * @return boolean
   **/
   function addField($table, $field, $type, $options = []) {
      global $DB;

      $params['update']    = [];
      $params['condition'] = [true];
      $params['value']     = null;
      $params['nodefault'] = false;
      $params['comment']   = '';
      $params['after']     = '';
      $params['first']     = '';
      $params['null']      = false;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      if (!is_array($params['condition'])) {
         Toolbox::deprecated('Use arrays to pass conditions');
         $params['condition'] = [new \QueryExpression($params['condition'])];
      }

      $format = $this->fieldFormat($type, $params['value'], $params['nodefault']);

      if (!empty($params['comment'])) {
         $params['comment'] = " COMMENT '".addslashes($params['comment'])."'";
      }

      if (!empty($params['after'])) {
         $params['after'] = " AFTER `".$params['after']."`";
      } else if (!empty($params['first'])) {
         $params['first'] = " FIRST ";
      }

      if ($params['null']) {
         $params['null'] = 'NULL ';
      }

      if ($format) {
         if (!$DB->fieldExists($table, $field, false)) {
            $this->change[$table][] = "ADD `$field` $format ".$params['comment'] ." ".
                                      $params['null'].$params['first'].$params['after'];

            if (!empty($params['update'])) {
               $this->migrationOneTable($table);
               $DB->updateOrDie(
                  $table, [
                     $field   => $params['update']
                  ], $params['condition']
               );
            }
            return true;
         }
         return false;
      }
   }


   /**
    * Modify field for migration
    *
    * @param string $table    Table name
    * @param string $oldfield Old name of the field
    * @param string $newfield New name of the field
    * @param string $type     Field type, @see Migration::fieldFormat()
    * @param array  $options  Options:
    *                         - default_value new field's default value, if a specific default value needs to be used
    *                         - first     : add the new field at first column
    *                         - after     : where adding the new field
    *                         - null      : value could be NULL (default false)
    *                         - comment comment to be added during field creation
    *                         - nodefault : do not define default value (default false)
    *
    * @return boolean
   **/
   function changeField($table, $oldfield, $newfield, $type, $options = []) {
      global $DB;

      $params['value']     = null;
      $params['nodefault'] = false;
      $params['comment']   = '';
      $params['after']     = '';
      $params['first']     = '';
      $params['null']      = false;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      $format = $this->fieldFormat($type, $params['value'], $params['nodefault']);

      if ($params['comment']) {
         $params['comment'] = " COMMENT '".addslashes($params['comment'])."'";
      }

      if (!empty($params['after'])) {
         $params['after'] = " AFTER `".$params['after']."`";
      } else if (!empty($params['first'])) {
         $params['first'] = " FIRST ";
      }

      if ($params['null']) {
         $params['null'] = 'NULL ';
      }

      if ($DB->fieldExists($table, $oldfield, false)) {
         // in order the function to be replayed
         // Drop new field if name changed
         if (($oldfield != $newfield)
             && $DB->fieldExists($table, $newfield)) {
            $this->change[$table][] = "DROP `$newfield` ";
         }

         if ($format) {
            $this->change[$table][] = "CHANGE `$oldfield` `$newfield` $format ".$params['comment']." ".
                                      $params['null'].$params['first'].$params['after'];
         }
         return true;
      }

      return false;
   }


   /**
    * Drop field for migration
    *
    * @param string $table Table name
    * @param string $field Field name
    *
    * @return void
   **/
   function dropField($table, $field) {
      global $DB;

      if ($DB->fieldExists($table, $field, false)) {
         $this->change[$table][] = "DROP `$field`";
      }
   }


   /**
    * Drop immediatly a table if it exists
    *
    * @param string $table Table name
    *
    * @return void
   **/
   function dropTable($table) {
      global $DB;

      if ($DB->tableExists($table)) {
         $DB->rawQuery("DROP TABLE `$table`");
      }
   }


   /**
    * Add index for migration
    *
    * @param string       $table     Table name
    * @param string|array $fields    Field(s) name(s)
    * @param string       $indexname Index name, $fields if empty, defaults to empty
    * @param string       $type      Index type (index or unique - default 'INDEX')
    * @param integer      $len       Field length (default 0)
    *
    * @return void
   **/
   function addKey($table, $fields, $indexname = '', $type = 'INDEX', $len = 0) {
      global $DB;

      //when no index name provided, compute from field(s) name(s)
      if (!$indexname) {
         if (is_array($fields)) {
            $indexname = implode($fields, "_");
         } else {
            $indexname = $fields;
         }
      }

      if (!$DB->indexExists($table, $fields, $indexname)) {
         if (is_array($fields)) {
            if ($len) {
               $fields = "`".implode($fields, "`($len), `")."`($len)";
            } else {
               $fields = "`".implode($fields, "`, `")."`";
            }
         } else if ($len) {
            $fields = "`$fields`($len)";
         } else {
            $fields = "`$fields`";
         }

         if ($type == 'FULLTEXT') {
            $this->fulltexts[$table][] = "ADD $type `$indexname` ($fields)";
         } else if ($type == 'UNIQUE') {
            $this->uniques[$table][] = "ADD $type `$indexname` ($fields)";
         } else {
            $this->change[$table][] = "ADD $type `$indexname` ($fields)";
         }
      }
   }


   /**
    * Drop index for migration
    *
    * @param string $table     Table name
    * @param string $indexname Index name
    *
    * @return void
   **/
   function dropKey($table, $indexname) {
      global $DB;

      if ($DB->indexExists($table, [], $indexname)) {
         $this->change[$table][] = "DROP INDEX `$indexname`";
      }
   }


   /**
    * Rename table for migration
    *
    * @param string $oldtable Old table name
    * @param string $newtable new table name
    *
    * @return void
   **/
   function renameTable($oldtable, $newtable) {
      global $DB;

      if (!$DB->tableExists("$newtable") && $DB->tableExists("$oldtable")) {
         $query = "RENAME TABLE `$oldtable` TO `$newtable`";
         $DB->rawQueryOrDie($query, $this->version." rename $oldtable");

         // Update target of "buffered" schema updates
         if (isset($this->change[$oldtable])) {
            $this->change[$newtable] = $this->change[$oldtable];
            unset($this->change[$oldtable]);
         }
         if (isset($this->fulltexts[$oldtable])) {
            $this->fulltexts[$newtable] = $this->fulltexts[$oldtable];
            unset($this->fulltexts[$oldtable]);
         }
         if (isset($this->uniques[$oldtable])) {
            $this->uniques[$newtable] = $this->uniques[$oldtable];
            unset($this->uniques[$oldtable]);
         }
      } else {
         if (Toolbox::startsWith($oldtable, 'glpi_plugin_')
            || Toolbox::startsWith($newtable, 'glpi_plugin_')
         ) {
            return;
         }
         $message = sprintf(
            __('Unable to rename table %1$s (%2$s) to %3$s (%4$s)!'),
            $oldtable,
            ($DB->tableExists($oldtable) ? __('ok') : __('nok')),
            $newtable,
            ($DB->tableExists($newtable) ? __('nok') : __('ok'))
         );
         Toolbox::logError($message);
         die(1);
      }
   }


   /**
    * Copy table for migration
    *
    * @since 0.84
    *
    * @param string $oldtable The name of the table already inside the database
    * @param string $newtable The copy of the old table
    *
    * @return void
   **/
   function copyTable($oldtable, $newtable) {
      global $DB;

      if (!$DB->tableExists($newtable)
          && $DB->tableExists($oldtable)) {

         // Try to do a flush tables if RELOAD privileges available
         // $query = "FLUSH TABLES `$oldtable`, `$newtable`";
         // $DB->rawQuery($query);

         $query = "CREATE TABLE " . $DB->quoteName($newtable) . " LIKE " . $DB->quoteName($oldtable);
         $DB->rawQueryOrDie($query, $this->version." create $newtable");

         //nedds DB::insert to support subqeries to get migrated
         $query = "INSERT INTO " . $DB->quoteName($newtable) . "
                          (SELECT *
                           FROM " . $DB->quoteName($oldtable) . ")";
         $DB->rawQueryOrDie($query, $this->version." copy from $oldtable to $newtable");
      }
   }


   /**
    * Insert an entry inside a table
    *
    * @since 0.84
    *
    * @param string $table The table to alter
    * @param array  $input The elements to add inside the table
    *
    * @return integer id of the last item inserted by mysql
   **/
   function insertInTable($table, array $input) {
      global $DB;

      if ($DB->tableExists("$table")
          && is_array($input) && (count($input) > 0)) {

         $values = [];
         foreach ($input as $field => $value) {
            if ($DB->fieldExists($table, $field)) {
               $values[$field] = $value;
            }
         }

         $DB->insertOrDie($table, $values, $this->version." insert in $table");

         return $DB->insertId();
      }
   }


   /**
    * Execute migration for only one table
    *
    * @param string $table Table name
    *
    * @return void
   **/
   function migrationOneTable($table) {
      global $DB;

      if (isset($this->change[$table])) {
         $query = "ALTER TABLE " . $DB->quoteName($table) . " ".implode($this->change[$table], " ,\n")." ";
         $this->displayMessage( sprintf(__('Change of the database layout - %s'), $table));
         $DB->rawQueryOrDie($query, $this->version." multiple alter in $table");
         unset($this->change[$table]);
      }

      if (isset($this->fulltexts[$table])) {
         $this->displayMessage( sprintf(__('Adding fulltext indices - %s'), $table));
         foreach ($this->fulltexts[$table] as $idx) {
            $query = "ALTER TABLE " . $DB->quoteName($table) . " ".$idx;
            $DB->rawQueryOrDie($query, $this->version." $idx");
         }
         unset($this->fulltexts[$table]);
      }
      if (isset($this->uniques[$table])) {
         $this->displayMessage( sprintf(__('Adding unicity indices - %s'), $table));
         foreach ($this->uniques[$table] as $idx) {
            $query = "ALTER TABLE `$table` ".$idx;
            $DB->rawQueryOrDie($query, $this->version." $idx");
         }
         unset($this->uniques[$table]);
      }
   }


   /**
    * Execute global migration
    *
    * @return void
   **/
   function executeMigration() {
      global $DB;

      foreach ($this->queries[self::PRE_QUERY] as $query) {
         $DB->rawQueryOrDie($query['query'], $query['message'], $query['params']);
      }
      $this->queries[self::PRE_QUERY] = [];

      $tables = array_merge(
         array_keys($this->change),
         array_keys($this->fulltexts),
         array_keys($this->uniques)
      );
      foreach ($tables as $table) {
         $this->migrationOneTable($table);
      }

      foreach ($this->queries[self::POST_QUERY] as $query) {
         $DB->rawQueryOrDie($query['query'], $query['message'], $query['params']);
      }
      $this->queries[self::POST_QUERY] = [];

      $this->storeConfig();

      // end of global message
      $this->displayMessage(__('Task completed.'));
   }


   /**
    * Register a new rule
    *
    * @since 0.84
    *
    * @param array $rule     Array of fields of glpi_rules
    * @param array $criteria Array of Array of fields of glpi_rulecriterias
    * @param array $actions  Array of Array of fields of glpi_ruleactions
    *
    * @return integer new rule id
   **/
   function createRule(Array $rule, Array $criteria, Array $actions) {
      global $DB;

      // Avoid duplicate - Need to be improved using a rule uuid of other
      if (countElementsInTable('glpi_rules', ['name' => $rule['name']])) {
         return 0;
      }
      $rule['comment']     = sprintf(__('Automatically generated by GLPI %s'), $this->version);
      $rule['description'] = '';

      // Compute ranking
      $iterator = $DB->request([
         'SELECT' => ['MAX' => 'ranking AS rank'],
         'FROM'   => 'glpi_rules',
         'WHERE'  => ['sub_type' => $rule['sub_type']]
      ]);

      $ranking = 1;
      if (count($iterator)) {
         $data = $iterator->next();
         $ranking = $data["rank"] + 1;
      }

      // The rule itself
      $values = ['ranking' => $ranking];
      foreach ($rule as $field => $value) {
         $values[$field] = $value;
      }
      $DB->insertOrDie('glpi_rules', $values);
      $rid = $DB->insertId();

      // The rule criteria
      foreach ($criteria as $criterion) {
         $values = ['rules_id' => $rid];
         foreach ($criterion as $field => $value) {
            $values[$field] = $value;
         }
         $DB->insertOrDie('glpi_rulecriterias', $values);
      }

      // The rule criteria actions
      foreach ($actions as $action) {
         $values = ['rules_id' => $rid];
         foreach ($action as $field => $value) {
            $values[$field] = $value;
         }
         $DB->insertOrDie('glpi_ruleactions', $values);
      }
   }


   /**
    * Update display preferences
    *
    * @since 0.85
    *
    * @param array $toadd items to add : itemtype => array of values
    * @param array $todel items to del : itemtype => array of values
    *
    * @return void
   **/
   function updateDisplayPrefs($toadd = [], $todel = []) {
      global $DB;

      //TRANS: %s is the table or item to migrate
      $this->displayMessage(sprintf(__('Data migration - %s'), 'glpi_displaypreferences'));
      if (count($toadd)) {
         foreach ($toadd as $type => $tab) {
            $iterator = $DB->request([
               'SELECT'          => 'users_id',
               'DISTINCT'        => true,
               'FROM'            => 'glpi_displaypreferences',
               'WHERE'           => ['itemtype' => $type]
            ]);

            if (count($iterator) > 0) {
               while ($data = $iterator->next()) {
                  $result = $DB->request([
                     'SELECT' => ['MAX' => 'rank as maxrank'],
                     'FROM'   => 'glpi_displaypreferences',
                     'WHERE'  => [
                        'users_id'  => $data['users_id'],
                        'itemtype'  => $type
                     ]
                  ])->next();

                  $rank = $result['maxrank'];
                  ++$rank;

                  foreach ($tab as $newval) {
                     $check_iterator = $DB->request([
                        'FROM'   => 'glpi_displaypreferences',
                        'WHERE'  => [
                           'users_id'  => $data['users_id'],
                           'num'       => $newval,
                           'itemtype'  => $type
                        ]
                     ]);
                     if (count($check_iterator) == 0) {
                           $DB->insert(
                              'glpi_displaypreferences', [
                                 'itemtype'  => $type,
                                 'num'       => $newval,
                                 'rank'      => $rank++,
                                 'users_id'  => $data['users_id']
                              ]
                           );
                     }
                  }
               }

            } else { // Add for default user
               $rank = 1;
               foreach ($tab as $newval) {
                     $DB->insert(
                        'glpi_displaypreferences', [
                           'itemtype'  => $type,
                           'num'       => $newval,
                           'rank'      => $rank++,
                           'users_id'  => 0
                        ]
                     );
               }
            }
         }
      }

      if (count($todel)) {
         // delete display preferences
         foreach ($todel as $type => $tab) {
            if (count($tab)) {
               $DB->delete(
                  'glpi_displaypreferences', [
                     'itemtype'  => $type,
                     'num'       => $tab
                  ]
               );
            }
         }
      }
   }

   /**
    * Add a migration SQL query
    *
    * @param string $type    Either self::PRE_QUERY or self::POST_QUERY
    * @param string $query   Query to execute
    * @param array  $params  Query parameters
    * @param string $message Mesage to display on error, defaults to null
    *
    * @return Migration
    *
    * @since 10.0.0 Added $params parameter
    */
   private function addQuery($type, $query, $params, $message = null) {
      $this->queries[$type][] =  [
         'query'     => $query,
         'message'   => $message,
         'params'    => $params
      ];
      return $this;
   }

   /**
    * Add a pre migration SQL query
    *
    * @param string $query   Query to execute
    * @param array  $params  Query parameters
    * @param string $message Mesage to display on error, defaults to null
    *
    * @return Migration
    *
    * @since 10.0.0 Added $params parameter
    */
   public function addPreQuery($query, $params = [], $message = null) {
      return $this->addQuery(self::PRE_QUERY, $query, $params, $message);
   }

   /**
    * Add a post migration SQL query
    *
    * @param string $query   Query to execute
    * @param array  $params  Query parameters
    * @param string $message Mesage to display on error, defaults to null
    *
    * @return Migration
    *
    * @since 10.0.0 Added $params parameter
    */
   public function addPostQuery($query, $params = [], $message = null) {
      return $this->addQuery(self::POST_QUERY, $query, $params, $message);
   }

   /**
    * Backup existing tables
    *
    * @param array $tables Existing tables to backup
    *
    * @return boolean
    */
   public function backupTables($tables) {
      global $DB;

      $backup_tables = false;
      foreach ($tables as $table) {
         // rename new tables if exists ?
         if ($DB->tableExists($table)) {
            $this->dropTable("backup_$table");
            $this->displayWarning(sprintf(__('%1$s table already exists. A backup have been done to %2$s'),
                                          $table, "backup_$table"));
            $backup_tables = true;
            $this->renameTable("$table", "backup_$table");
         }
      }
      if ($backup_tables) {
         $this->displayWarning("You can delete backup tables if you have no need of them.", true);
      }
      return $backup_tables;
   }

   /**
    * Add configuration value(s) to current context; @see Migration::setContext()
    *
    * @since 9.2
    *
    * @param string|array $values Value(s) to add
    *
    * @return Migration
    */
   public function addConfig($values) {
      $this->configs += (array)$values;
      return $this;
   }

   /**
    * Store configuration values that does not exists
    *
    * @since 9.2
    *
    * @return boolean
    */
   private function storeConfig() {
      global $DB;

      if (count($this->configs)) {
         $existing = $DB->request(
            "glpi_configs", [
               'context'   => $this->context,
               'name'      => array_keys($this->configs)
            ]
         );
         foreach ($existing as $conf) {
            unset($this->configs[$conf['name']]);
         }
         if (count($this->configs)) {
            Config::setConfigurationValues($this->context, $this->configs);
            $this->displayMessage(sprintf(
               __('Configuration values added for %1$s.'),
               implode(', ', array_keys($this->configs))
            ));
         }
      }
   }

   /**
    * Set configuration context
    *
    * @since 9.2
    *
    * @param string $context Configuration context
    *
    * @return Migration
    */
   public function setContext($context) {
      $this->context = $context;
      return $this;
   }

   /**
    * Add new right to profiles that match rights requirements
    *    Default is to give rights to profiles with READ and UPDATE rights on config
    *
    * @param string  $name   Right name
    * @param integer $rights Right to set (defaults to ALLSTANDARDRIGHT)
    * @param array   $requiredrights Array of right name => value
    *                   A profile must have these rights in order to get the new right.
    *                   This array can be empty to add the right to every profile.
    *                   Default is ['config' => READ | UPDATE].
    *
    * @return void
    */
   public function addRight($name, $rights = ALLSTANDARDRIGHT, $requiredrights = ['config' => READ | UPDATE]) {
      global $DB;

      // Get all profiles where new rights has not been added yet
      $prof_iterator = $DB->request(
         [
            'SELECT'    => 'glpi_profiles.id',
            'FROM'      => 'glpi_profiles',
            'LEFT JOIN' => [
               'glpi_profilerights' => [
                  'ON' => [
                     'glpi_profilerights' => 'profiles_id',
                     'glpi_profiles'      => 'id',
                     [
                        'AND' => ['glpi_profilerights.name' => $name]
                     ]
                  ]
               ],
            ],
            'WHERE'     => [
               'glpi_profilerights.id' => null,
            ]
         ]
      );

      if ($prof_iterator->count() === 0) {
         return;
      }

      $where = [];
      foreach ($requiredrights as $reqright => $reqvalue) {
         $where['OR'][] = [
            'name'   => $reqright,
            new QueryExpression("{$DB->quoteName('rights')} & $reqvalue = $reqvalue")
         ];
      }

      while ($profile = $prof_iterator->next()) {
         if (empty($requiredrights)) {
            $reqmet = true;
         } else {
            $iterator = $DB->request([
               'SELECT' => [
                  'name',
                  'rights'
               ],
               'FROM'   => 'glpi_profilerights',
               'WHERE'  => $where + ['profiles_id' => $profile['id']]
            ]);

            $reqmet = (count($iterator) == count($requiredrights));
         }

         $DB->insertOrDie(
            'glpi_profilerights', [
               'id'           => null,
               'profiles_id'  => $profile['id'],
               'name'         => $name,
               'rights'       => $reqmet ? $rights : 0
            ],
            sprintf('%1$s add right for %2$s', $this->version, $name)
         );
      }

      $this->displayWarning(
         sprintf(
            'New rights has been added for %1$s, you should review ACLs after update',
            $name
         ),
         true
      );
   }

   public function setOutputHandler($output_handler) {

      $this->output_handler = $output_handler;
   }

   /**
    * Output a message.
    *
    * @param string $msg      Message to output.
    * @param string $style    Style to use, value can be 'title', 'warning', 'strong' or null.
    * @param string $area_id  Display area to use.
    *
    * @return void
    */
   protected function outputMessage($msg, $style = null, $area_id = null) {
      if (isCommandLine()) {
         $this->outputMessageToCli($msg, $style);
      } else {
         $this->outputMessageToHtml($msg, $style, $area_id);
      }
   }

   /**
    * Output a message in console output.
    *
    * @param string $msg    Message to output.
    * @param string $style  Style to use, see self::outputMessage() for possible values.
    *
    * @return void
    */
   private function outputMessageToCli($msg, $style = null) {

      $format = null;
      $verbosity = OutputInterface::VERBOSITY_NORMAL;
      switch ($style) {
         case 'title':
            $msg       = str_pad(" $msg ", 100, '=', STR_PAD_BOTH);
            $format    = 'info';
            $verbosity = OutputInterface::VERBOSITY_NORMAL;
            break;
         case 'warning':
            $msg       = str_pad("** {$msg}", 100);
            $format    = 'comment';
            $verbosity = OutputInterface::VERBOSITY_VERBOSE;
            break;
         case 'strong':
            $msg       = str_pad($msg, 100);
            $format    = 'comment';
            $verbosity = OutputInterface::VERBOSITY_VERBOSE;
            break;
         default:
            $msg       = str_pad($msg, 100);
            $format    = 'comment';
            $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
            break;
      }

      if ($this->output_handler instanceof OutputInterface) {
         if (null !== $format) {
            $msg = sprintf('<%1$s>%2$s</%1$s>', $format, $msg);
         }
         $this->output_handler->writeln($msg, $verbosity);
      } else {
         echo $msg . PHP_EOL;
      }
   }

   /**
    * Output a message in html page.
    *
    * @param string $msg      Message to output.
    * @param string $style    Style to use, see self::outputMessage() for possible values.
    * @param string $area_id  Display area to use.
    *
    * @return void
    */
   private function outputMessageToHtml($msg, $style = null, $area_id = null) {

      $msg = Html::entities_deep($msg);

      switch ($style) {
         case 'title':
            $msg = '<h3>' . $msg . '</h3>';
            break;
         case 'warning':
            $msg = '<div class="migred"><p>' . $msg . '</p></div>';
            break;
         case 'strong':
            $msg = '<p><span class="b">' . $msg . '</span></p>';
            break;
         default:
            $msg = '<p class="center">' . $msg . '</p>';
            break;
      }

      if (null !== $area_id) {
         echo "<script type='text/javascript'>
                  document.getElementById('{$area_id}').innerHTML = '{$msg}';
               </script>\n";
         Html::glpi_flush();
      } else {
         echo $msg;
      }
   }
}
