<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\models;

use PDO;
use core\Model;

/**
 * Manages basic behaviors and data related to the review system
 */
class Review extends Model
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Adds a review
     * @param array $data
     * @return boolean|integer
     */
    public function add(array $data)
    {
        $this->hook->fire('add.review.before', $data);

        if (empty($data)) {
            return false;
        }

        $data += array('created' => GC_TIME);
        $values = $this->db->prepareInsert('review', $data);

        $data['review_id'] = $this->db->insert('review', $values);

        $this->hook->fire('add.review.after', $data);
        return $data['review_id'];
    }

    /**
     * Loads a review
     * @param integer $review_id
     * @return array
     */
    public function get($review_id)
    {
        $this->hook->fire('get.review.before', $review_id);

        $sth = $this->db->prepare('SELECT * FROM review WHERE review_id=?');
        $sth->execute(array($review_id));

        $review = $sth->fetch(PDO::FETCH_ASSOC);

        if (!empty($review)) {
            $review['data'] = unserialize($review['data']);
        }

        $this->hook->fire('get.review.after', $review_id, $review);
        return $review;
    }

    /**
     * Updates a review
     * @param integer $review_id
     * @param array $data
     * @return boolean
     */
    public function update($review_id, array $data)
    {
        $this->hook->fire('update.review.before', $review_id, $data);

        if (empty($review_id)) {
            return false;
        }

        $data['modified'] = GC_TIME;

        $values = $this->db->filterValues('review', $data);

        $result = false;

        if (!empty($values)) {
            $conditions = array('review_id' => $review_id);
            $result = $this->db->update('review', $values, $conditions);
        }

        $this->hook->fire('update.review.after', $review_id, $data, $result);
        return (bool) $result;
    }

    /**
     * Deletes a review
     * @param integer $review_id
     * @return boolean
     */
    public function delete($review_id)
    {
        $this->hook->fire('delete.review.before', $review_id);

        if (empty($review_id)) {
            return false;
        }

        $sth = $this->db->prepare("DELETE FROM review WHERE FIND_IN_SET(review_id, :review_id)");
        $sth->execute(array(':review_id' => implode(',', (array) $review_id)));

        $this->hook->fire('delete.review.after', $review_id);
        return true;
    }

    /**
     * Returns an array of reviews or their total number
     * @param array $data
     * @return array|integer
     */
    public function getList(array $data = array())
    {
        $sql = 'SELECT r.*, u.name, u.email';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(r.review_id)';
        }

        $sql .= ' FROM review r'
                . ' LEFT JOIN user u ON(r.user_id = u.user_id)'
                . ' WHERE r.review_id > 0';

        $where = array();

        if (isset($data['text'])) {
            $sql .= ' AND r.text LIKE ?';
            $where[] = "%{$data['text']}%";
        }

        if (isset($data['user_id'])) {
            $sql .= ' AND r.user_id = ?';
            $where[] = (int) $data['user_id'];
        }

        if (isset($data['product_id'])) {
            $sql .= ' AND r.product_id = ?';
            $where[] = (int) $data['product_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND r.status = ?';
            $where[] = (bool) $data['status'];
        }

        if (isset($data['created'])) {
            $sql .= ' AND r.created = ?';
            $where[] = (int) $data['created'];
        }

        if (isset($data['modified'])) {
            $sql .= ' AND r.modified = ?';
            $where[] = (int) $data['modified'];
        }

        if (isset($data['user_status'])) {
            $sql .= ' AND u.status = ?';
            $where[] = (int) $data['user_status'];
        }

        $allowed_order = array('asc', 'desc');
        $allowed_sort = array('product_id', 'user_id', 'status', 'created', 'text');

        if (isset($data['sort']) && in_array($data['sort'], $allowed_sort) && isset($data['order']) && in_array($data['order'], $allowed_order)) {
            $sql .= " ORDER BY r.{$data['sort']} {$data['order']}";
        } else {
            $sql .= " ORDER BY r.created DESC";
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        $sth = $this->db->prepare($sql);
        $sth->execute($where);

        if (!empty($data['count'])) {
            return (int) $sth->fetchColumn();
        }

        $list = array();
        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $review) {
            $list[$review['review_id']] = $review;
        }

        $this->hook->fire('reviews', $list);
        return $list;
    }

}
