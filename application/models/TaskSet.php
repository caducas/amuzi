<?php

/**
 * TaskSet
 *
 * @package Amuzi
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2012  Diogo Oliveira de Melo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class TaskSet extends DZend_Model
{
    public function findByTaskTypeId($taskTypeId)
    {
        return $this->_taskSetDb->findByTaskTypeId($taskTypeId);
    }

    public function findMostRecentTask($name, $args)
    {
        $taskTypeRow = $this->_taskTypeModel->findRowByName($name);
        $rowSet = $this->findByTaskTypeId($taskTypeRow->id);
        $ids = array();

        foreach ($rowSet as $row) {
            $ids[] = $row->id;
        }

        if (0 !== count($rowSet)) {
            $id = $this->_taskParameterModel->findMostRecentTaskSetId($ids, $args);
            if (false === $id) {
                return false;
            } else {
                return $this->_taskSetDb->findById($id);
            }
        }

        return false;
    }

    public function createTask($name, $args)
    {
        $args = array_slice(func_get_args(), 1);
        $taskTypeRow = $this->_taskTypeModel->findByName($name);

        $data = array(
            'task_type_id' => $taskTypeRow->id,
        );

        $taskSetId = $this->_taskSetDb->insert($data);

        $i = 0;
        foreach ($args as $arg) {
            $this->_taskParameterModel->createParameter($taskSetId, $i, $arg);
            $i++;
        }

        return $taskSetId;
    }
}