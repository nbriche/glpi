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

/**
 * Update from 0.85.3 to 0.85.5
 *
 * @return bool for success (will die for most error)
**/
function update0853to0855() {
   global $migration;

   $updateresult = true;

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '0.85.5'));
   $migration->setVersion('0.85.5');

   $migration->addField("glpi_entities", 'inquest_duration', "integer", ['value' => 0]);

   $migration->addKey('glpi_users', 'begin_date', 'begin_date');
   $migration->addKey('glpi_users', 'end_date', 'end_date');

   $migration->addKey('glpi_knowbaseitems', 'begin_date', 'begin_date');
   $migration->addKey('glpi_knowbaseitems', 'end_date', 'end_date');

   // must always be at the end
   $migration->executeMigration();

   return $updateresult;
}

