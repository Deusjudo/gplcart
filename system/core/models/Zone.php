<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\models;

use core\Model;

/**
 * Manages basic behaviors and data related geo zones
 */
class Zone extends Model
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a zone
     * @param integer $zone_id
     * @return array
     */
    public function get($zone_id)
    {
        $this->hook->fire('get.zone.before', $zone_id);

        $sql = 'SELECT * FROM zone WHERE zone_id=?';
        $zone = $this->db->fetch($sql, array($zone_id));

        $this->hook->fire('get.zone.after', $zone);

        return $zone;
    }

    /**
     * Adds a zone
     * @param array $data
     * @return boolean
     */
    public function add(array $data)
    {
        $this->hook->fire('add.zone.before', $data);

        if (empty($data)) {
            return false;
        }

        $data['zone_id'] = $this->db->insert('zone', $data);

        $this->hook->fire('add.zone.after', $data);

        return $data['zone_id'];
    }

    /**
     * Updates a zone
     * @param integer $zone_id
     * @param array $data
     * @return boolean
     */
    public function update($zone_id, array $data)
    {
        $this->hook->fire('update.zone.before', $zone_id, $data);

        if (empty($zone_id) || empty($data)) {
            return false;
        }

        $result = (bool) $this->db->update('zone', $data, array('zone_id' => $zone_id));

        $this->hook->fire('update.zone.after', $zone_id, $data, $result);

        return (bool) $result;
    }

    /**
     * Deletes a zone
     * @param integer $zone_id
     * @return boolean
     */
    public function delete($zone_id)
    {
        $this->hook->fire('delete.zone.before', $zone_id);

        if (empty($zone_id) || !$this->canDelete($zone_id)) {
            return false;
        }

        $result = (bool) $this->db->delete('zone', array('zone_id' => $zone_id));

        $this->hook->fire('delete.zone.after', $zone_id, $result);

        return (bool) $result;
    }

    /**
     * Whether a zone can be deleted
     * @param integer $zone_id
     * @return boolean
     */
    public function canDelete($zone_id)
    {
        $sql = 'SELECT NOT EXISTS (SELECT zone_id FROM country WHERE zone_id=:id)'
                . ' AND NOT EXISTS (SELECT zone_id FROM state WHERE zone_id=:id)'
                . ' AND NOT EXISTS (SELECT zone_id FROM city WHERE zone_id=:id)';

        return (bool) $this->db->fetchColumn($sql, array('id' => $zone_id));
    }

    /**
     * Returns an array of zones
     * @param array $data
     * @return array
     */
    public function getList(array $data)
    {
        $sql = 'SELECT *';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(zone_id)';
        }

        $sql .= ' FROM zone WHERE zone_id > 0';

        $conditions = array();

        if (isset($data['status'])) {
            $sql .= ' AND status=?';
            $conditions[] = (int) $data['status'];
        }

        $allowed_order = array('asc', 'desc');
        $allowed_sort = array('title', 'status');

        if (isset($data['sort']) && in_array($data['sort'], $allowed_sort)
                && isset($data['order']) && in_array($data['order'], $allowed_order)) {
            $sql .= " ORDER BY {$data['sort']} {$data['order']}";
        } else {
            $sql .= " ORDER BY title ASC";
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        if (!empty($data['count'])) {
            return (int) $this->db->fetchColumn($sql, $conditions);
        }

        $list = $this->db->fetchAll($sql, $conditions);
        $this->hook->fire('zone.list', $list);
        return $list;
    }

}
