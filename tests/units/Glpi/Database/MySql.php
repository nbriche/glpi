<?php
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

namespace tests\units\Glpi\Database;

/* Test for src/Glpi/Database/MySql.php */

class MySql extends \GLPITestCase {
   private $db;

   public function beforetestMethod($method) {
      $this->db = \Glpi\DatabaseFactory::create();
   }

   public function testTableExist() {
      $this
         ->boolean($this->db->tableExists('glpi_configs'))->isTrue()
         ->boolean($this->db->tableExists('fakeTable'))->isFalse();
   }

   public function testFieldExists() {
      $this
        ->boolean($this->db->fieldExists('glpi_configs', 'id'))->isTrue()
        ->boolean($this->db->fieldExists('glpi_configs', 'ID'))->isFalse()
        ->boolean($this->db->fieldExists('glpi_configs', 'fakeField'))->isFalse()
        ->boolean($this->db->fieldExists('fakeTable', 'id'))->isFalse()
        ->boolean($this->db->fieldExists('fakeTable', 'fakeField'))->isFalse();
   }

   protected function dataName() {
      return [
         ['field', '`field`'],
         ['`field`', '`field`'],
         ['*', '*'],
         ['table.field', '`table`.`field`'],
         ['table.*', '`table`.*'],
         ['field AS f', '`field` AS `f`'],
         ['field as f', '`field` AS `f`'],
         ['table.field as f', '`table`.`field` AS `f`'],
      ];
   }

   /**
    * @dataProvider dataName
    */
   public function testQuoteName($raw, $quoted) {
      $this->string($this->db->quoteName($raw))->isIdenticalTo($quoted);
   }

   protected function dataValue() {
      return [
         ['foo', "'foo'"],
         ['bar', "'bar'"],
         ['42', "'42'"],
         ['+33', "'+33'"],
         [null, 'NULL'],
         ['null', 'NULL'],
         ['NULL', 'NULL'],
         ['`field`', '`field`'],
         ['`field', "`field"]
      ];
   }

   /**
    * @dataProvider dataValue
    */
   public function testQuoteValue($raw, $expected) {
      $this
         ->string($this->db->quoteValue($raw))->isIdenticalTo($expected);
   }


   protected function dataInsert() {
      return [
         [
            'table', [
               'field'  => 'value',
               'other'  => 'doe'
            ],
            'INSERT INTO `table` (`field`, `other`) VALUES (:field, :other)'
         ], [
            '`table`', [
               '`field`'  => 'value',
               '`other`'  => 'doe'
            ],
            'INSERT INTO `table` (`field`, `other`) VALUES (:field, :other)'
         ], [
            'table', [
               'field'  => new \QueryParam(),
               'other'  => new \QueryParam()
            ],
            'INSERT INTO `table` (`field`, `other`) VALUES (:field, :other)'
         ], [
            'table', [
               'field'  => new \QueryParam('field'),
               'other'  => new \QueryParam('other')
            ],
            'INSERT INTO `table` (`field`, `other`) VALUES (:field, :other)'
         ]
      ];
   }

   /**
    * @dataProvider dataInsert
    */
   public function testBuildInsert($table, $values, $expected) {
      $this->string($this->db->buildInsert($table, $values))->isIdenticalTo($expected);
   }

   protected function dataUpdate() {
       global $DB;

      return [
         [
            'table', [
               'field'  => 'value',
               'other'  => 'doe'
            ], [
               'id'  => 1
            ],
            'UPDATE `table` SET `field` = ?, `other` = ? WHERE `id` = ?',
            ['value', 'doe', 1]
         ], [
            'table', [
               'field'  => 'value'
            ], [
               'id'  => [1, 2]
            ],
            'UPDATE `table` SET `field` = ? WHERE `id` IN (?,?)',
            ['value', 1, 2]
         ], [
            'table', [
               'field'  => 'value'
            ], [
               'NOT'  => ['id' => [1, 2]]
            ],
            'UPDATE `table` SET `field` = ? WHERE  NOT (`id` IN (?,?))',
            ['value', 1, 2]
         ], [
            'table', [
               'field'  => new \QueryParam()
            ], [
               'NOT' => ['id' => [new \QueryParam(), new \QueryParam()]]
            ],
            'UPDATE `table` SET `field` = ? WHERE  NOT (`id` IN (?,?))',
            []
         ], [
            'table', [
               'field'  => new \QueryParam('field')
            ], [
               'NOT' => ['id' => [new \QueryParam('idone'), new \QueryParam('idtwo')]]
            ],
            'UPDATE `table` SET `field` = ? WHERE  NOT (`id` IN (?,?))',
            []
         ], [
            'table', [
               'field'  => new \QueryExpression($DB->quoteName('field') . ' + 1')
            ], [
               'id'  => [1, 2]
            ],
            'UPDATE `table` SET `field` = `field` + 1 WHERE `id` IN (?,?)',
            [1, 2]
         ]
      ];
   }

   /**
    * @dataProvider dataUpdate
    */
   public function testBuildUpdate($table, $values, $where, $expected, $parameters) {
       $this
          ->string($this->db->buildUpdate($table, $values, $where))->isIdenticalTo($expected)
          ->array($values)->isIdenticalTo($parameters);
   }

   public function testBuildUpdateWException() {
      $this->exception(
         function() {
            $set = ['a' => 'b'];
            $where = [];

            $this
               ->string($this->db->buildUpdate('table', $set, $where))->isIdenticalTo('');
         }
      )->hasMessage('Cannot run an UPDATE query without WHERE clause!');
   }

   protected function dataDelete() {
      return [
         [
            'table', [
               'id'  => 1
            ],
            'DELETE `table` FROM `table` WHERE `id` = ?',
            [1]
         ], [
            'table', [
               'id'  => [1, 2]
            ],
            'DELETE `table` FROM `table` WHERE `id` IN (?,?)',
            [1, 2]
         ], [
            'table', [
               'NOT'  => ['id' => [1, 2]]
            ],
            'DELETE `table` FROM `table` WHERE  NOT (`id` IN (?,?))',
            [1, 2]
         ], [
            'table', [
               'NOT'  => ['id' => [new \QueryParam(), new \QueryParam()]]
            ],
            'DELETE `table` FROM `table` WHERE  NOT (`id` IN (?,?))',
            []
         ], [
            'table', [
               'NOT'  => ['id' => [new \QueryParam('idone'), new \QueryParam('idtwo')]]
            ],
            'DELETE `table` FROM `table` WHERE  NOT (`id` IN (?,?))',
            []
         ]
      ];
   }

   /**
    * @dataProvider dataDelete
    */
   public function testBuildDelete($table, $where, $expected, $parameters) {
      $params = [];
       $this
          ->string($this->db->buildDelete($table, $params, $where))->isIdenticalTo($expected)
          ->array($params)->isIdenticalTo($parameters);
   }

   public function testBuildDeleteWException() {
      $this->exception(
         function() {
            $set = [];
            $this
                  ->string($this->db->buildDelete('table', $set, []))->isIdenticalTo('');
         }
      )->hasMessage('Cannot run an DELETE query without WHERE clause!');
   }

   public function testListTables() {
      $this
         ->given($tables = $this->db->listTables())
            ->object($tables)
               ->isInstanceOf(\DBMysqlIterator::class)
            ->integer(count($tables))
               ->isGreaterThan(100)
            ->given($tables = $this->db->listTables('glpi_configs'))
            ->object($tables)
               ->isInstanceOf(\DBMysqlIterator::class)
               ->hasSize(1);

   }

   public function testTablesHasItemtype() {
      $dbu = new \DbUtils();
      $list = $this->db->listTables();
      $this->object($list)->isInstanceOf(\DBmysqlIterator::class);
      $this->integer(count($list))->isGreaterThan(200);

      //check if each table has a corresponding itemtype
      while ($line = $list->next()) {
         $this->array($line)
            ->hasSize(1);
         $table = $line['TABLE_NAME'];
         $type = $dbu->getItemTypeForTable($table);

         $this->object($item = $dbu->getItemForItemtype($type))->isInstanceOf('CommonDBTM', $table);
         $this->string(get_class($item))->isIdenticalTo($type);
         $this->string($dbu->getTableForItemType($type))->isIdenticalTo($table);
      }
   }

   public function testQuote() {
      $this
         ->string($this->db->quote('nothing to do'))->isIdenticalTo("'nothing to do'")
         ->string($this->db->quote("shoul'be escaped"))->isIdenticalTo("'shoul\\'be escaped'")
         ->string($this->db->quote("First\nSecond"))->isIdenticalTo("'First\\nSecond'")
         ->string($this->db->quote("First\rSecond"))->isIdenticalTo("'First\\rSecond'")
         ->string($this->db->quote('Hi, "you"'))->isIdenticalTo("'Hi, \\\"you\\\"'");
   }

   public function testIndexExists() {
      $this
         ->boolean($this->db->indexExists('glpi_configs', 'fakeField'))->isFalse()
         ->boolean($this->db->indexExists('glpi_configs', 'id'))->isTrue()
         ->boolean($this->db->indexExists('glpi_configs', 'context'))->isFalse()
         ->boolean($this->db->indexExists('glpi_configs', 'name'))->isFalse()
         ->boolean($this->db->indexExists('glpi_configs', ['name', 'context']))->isTrue()
         ->boolean($this->db->indexExists('glpi_groups_tickets', ['tickets_id', 'type'], 'unicity'))->isTrue()
         ->boolean($this->db->indexExists('glpi_groups_tickets', ['tickets_id', 'type', 'groups_id']))->isTrue()
         ->boolean($this->db->indexExists('glpi_configs', 'value'))->isFalse()
         ->boolean($this->db->indexExists('glpi_users', 'locations_id'))->isTrue()
         ->boolean($this->db->indexExists('glpi_users', [], 'unicityloginauth'))->isTrue()
         ->when(
            function () {
               $this->boolean($this->db->indexExists('fakeTable', 'id'))->isFalse();
            }
         )->error
            ->withType(E_USER_WARNING)
            ->exists();
   }

   public function testListIndexes() {
       $this
         ->array($this->db->listIndexes('glpi_groups_tickets'))
         ->isEqualTo([
            'PRIMARY' => [
               0 => 'id',
            ],
            'unicity' => [
               0 => 'tickets_id',
               1 => 'type',
               2 => 'groups_id',
            ],
            'group' => [
               0 => 'groups_id',
               1 => 'type',
            ]
         ]);

       $this
          ->array($this->db->listIndexes('glpi_users'))
         ->isEqualTo([
            'PRIMARY' => [
               0 => 'id'
            ],
            'unicityloginauth' => [
               0 => 'name',
               1 => 'authtype',
               2 => 'auths_id'
            ],
            'firstname' => [
               0 => 'firstname'
            ],
            'realname' => [
               0 => 'realname'
            ],
            'entities_id' => [
               0 => 'entities_id'
            ],
            'profiles_id' => [
               0 => 'profiles_id'
            ],
            'locations_id' => [
               0 => 'locations_id'
            ],
            'usertitles_id' => [
               0 => 'usertitles_id'
            ],
            'usercategories_id' => [
               0 => 'usercategories_id'
            ],
            'is_deleted' => [
               0 => 'is_deleted'
            ],
            'is_active' => [
               0 => 'is_active'
            ],
            'date_mod' => [
               0 => 'date_mod'
            ],
            'authitem' => [
               0 => 'authtype',
               1 => 'auths_id'
            ],
            'is_deleted_ldap' => [
               0 => 'is_deleted_ldap'
            ],
            'date_creation' => [
               0 => 'date_creation'
            ],
            'begin_date' => [
               0 => 'begin_date'
            ],
            'end_date' => [
               0 => 'end_date'
            ],
            'sync_field' => [
               0 => 'sync_field'
            ],
            'groups_id' => [
               0 => 'groups_id'
            ],
            'users_id_supervisor' => [
               0 => 'users_id_supervisor'
            ]
         ]);
   }
}
