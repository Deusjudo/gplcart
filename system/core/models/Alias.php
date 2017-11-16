<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\models;

use gplcart\core\Hook,
    gplcart\core\Route,
    gplcart\core\Config,
    gplcart\core\Database;
use gplcart\core\models\Language as LanguageModel;

/**
 * Manages basic behaviors and data related to URL aliases
 */
class Alias
{

    /**
     * Database class instance
     * @var \gplcart\core\Database $db
     */
    protected $db;

    /**
     * Hook class instance
     * @var \gplcart\core\Hook $hook
     */
    protected $hook;

    /**
     * Config class instance
     * @var \gplcart\core\Config $config
     */
    protected $config;

    /**
     * Language model instance
     * @var \gplcart\core\models\Language $language
     */
    protected $language;

    /**
     * Route class instance
     * @var \gplcart\core\Route $route
     */
    protected $route;

    /**
     * @param Hook $hook
     * @param Database $db
     * @param Config $config
     * @param Route $route
     * @param LanguageModel $language
     */
    public function __construct(Hook $hook, Database $db, Config $config, Route $route,
            LanguageModel $language)
    {
        $this->db = $db;
        $this->hook = $hook;
        $this->route = $route;
        $this->config = $config;
        $this->language = $language;
    }

    /**
     * Adds an alias
     * @param array $data
     * @return integer
     */
    public function add(array $data)
    {
        $result = null;
        $this->hook->attach('alias.add.before', $data, $result);

        if (isset($result)) {
            return (int) $result;
        }

        $result = $this->db->insert('alias', $data);
        $this->hook->attach('alias.add.after', $data, $result);
        return (int) $result;
    }

    /**
     * Returns an alias
     * @param string $id_key
     * @param null|integer $id_value
     * @return string|array
     */
    public function get($id_key, $id_value = null)
    {
        $result = null;
        $this->hook->attach('alias.get.before', $id_key, $id_value, $result);

        if (isset($result)) {
            return $result;
        }

        if (is_numeric($id_key)) {
            $sql = 'SELECT * FROM alias WHERE alias_id=?';
            $result = $this->db->fetch($sql, array($id_key));
        } else {
            $sql = 'SELECT alias FROM alias WHERE id_key=? AND id_value=?';
            $result = $this->db->fetchColumn($sql, array($id_key, $id_value));
        }

        $this->hook->attach('alias.get.after', $id_key, $id_value, $result);
        return $result;
    }

    /**
     * Deletes an alias
     * @param string $id_key
     * @param null|integer $id_value
     * @return bool
     */
    public function delete($id_key, $id_value = null)
    {
        $result = null;
        $this->hook->attach('alias.delete.before', $id_key, $id_value, $result);

        if (isset($result)) {
            return (bool) $result;
        }

        if (is_numeric($id_key)) {
            $result = $this->db->delete('alias', array('alias_id' => $id_key));
        } else {
            $conditions = array('id_key' => $id_key, 'id_value' => $id_value);
            $result = $this->db->delete('alias', $conditions);
        }

        $this->hook->attach('alias.delete.after', $id_key, $id_value, $result);
        return (bool) $result;
    }

    /**
     * Returns an array of aliases or counts them
     * @param array $data
     * @return array|integer
     */
    public function getList(array $data = array())
    {
        $result = null;
        $this->hook->attach('alias.list.before', $data, $result);

        if (isset($result)) {
            return $result;
        }

        $sql = 'SELECT *';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(alias_id)';
        }

        $sql .= ' FROM alias WHERE alias_id > 0';

        $where = array();

        if (isset($data['alias_id'])) {
            $sql .= ' AND alias_id = ?';
            $where[] = $data['alias_id'];
        }

        if (isset($data['id_key'])) {
            $sql .= ' AND id_key = ?';
            $where[] = $data['id_key'];
        }

        if (isset($data['alias'])) {
            $sql .= ' AND alias LIKE ?';
            $where[] = "%{$data['alias']}%";
        }

        if (!empty($data['id_value'])) {
            settype($data['id_value'], 'array');
            $placeholders = rtrim(str_repeat('?,', count($data['id_value'])), ',');
            $sql .= " AND id_value IN($placeholders)";
            $where = array_merge($where, $data['id_value']);
        }

        $allowed_order = array('asc', 'desc');
        $allowed_sort = array('id_value', 'id_key', 'alias', 'alias_id');

        if (isset($data['sort']) && in_array($data['sort'], $allowed_sort)//
                && isset($data['order'])//
                && in_array($data['order'], $allowed_order)
        ) {
            $sql .= " ORDER BY {$data['sort']} {$data['order']}";
        } else {
            $sql .= " ORDER BY alias DESC";
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        if (!empty($data['count'])) {
            return (int) $this->db->fetchColumn($sql, $where);
        }

        $result = $this->db->fetchAll($sql, $where, array('index' => 'alias_id'));
        $this->hook->attach('alias.list.after', $data, $result);
        return $result;
    }

    /**
     * Returns a array of id keys (entity types)
     * @return array
     */
    public function getIdKeys()
    {
        return $this->db->fetchColumnAll('SELECT id_key FROM alias GROUP BY id_key');
    }

    /**
     * Creates an alias using an array of data
     * @param string $pattern
     * @param array $options
     * @return string
     */
    public function generate($pattern, array $options = array())
    {
        $options += array('translit' => true, 'language' => null, 'placeholders' => array());

        $result = null;
        $this->hook->attach('alias.generate.before', $pattern, $options, $result);

        if (isset($result)) {
            return (string) $result;
        }

        $alias = $pattern;
        if (!empty($options['placeholders'])) {
            $alias = gplcart_string_replace($pattern, $options['placeholders'], $options);
        }

        if (!empty($options['translit'])) {
            $alias = gplcart_string_slug($this->language->translit($alias, $options['language']));
        }

        $trimmed = mb_strimwidth(str_replace(' ', '-', trim($alias)), 0, 100, '');
        $result = $this->getUnique($trimmed);
        $this->hook->attach('alias.generate.after', $pattern, $options, $result);
        return $result;
    }

    /**
     * Generates an alias for an entity
     * @param string $entity_name
     * @param array $data
     * @return string
     */
    public function generateEntity($entity_name, array $data)
    {
        $data += array('placeholders' => $this->getEntityPatternPlaceholders($entity_name));
        return $this->generate($this->getEntityPattern($entity_name), $data);
    }

    /**
     * Returns default entity alias pattern
     * @param string $entity_name
     * @return string
     */
    protected function getEntityPattern($entity_name)
    {
        return $this->config->get("{$entity_name}_alias_pattern", '%t.html');
    }

    /**
     * Returns default entity alias placeholders
     * @param string $entity_name
     * @return array
     */
    protected function getEntityPatternPlaceholders($entity_name)
    {
        return $this->config->get("{$entity_name}_alias_placeholder", array('%t' => 'title'));
    }

    /**
     * Returns a unique alias using a base string
     * @param string $alias
     * @return string
     */
    public function getUnique($alias)
    {
        if (!$this->exists($alias)) {
            return $alias;
        }

        $info = pathinfo($alias);
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

        $counter = 0;

        do {
            $counter++;
            $modified = $info['filename'] . '-' . $counter . $ext;
        } while ($this->exists($modified));

        return $modified;
    }

    /**
     * Whether the alias path already exists
     * @param string $path
     * @return boolean
     */
    public function exists($path)
    {
        foreach ($this->route->getList() as $route) {
            if (isset($route['pattern']) && $route['pattern'] === $path) {
                return true;
            }
        }

        return (bool) $this->getByPath($path);
    }

    /**
     * Loads an alias
     * @param string $alias
     * @return array
     */
    public function getByPath($alias)
    {
        return $this->db->fetch('SELECT * FROM alias WHERE alias=?', array($alias));
    }

}
