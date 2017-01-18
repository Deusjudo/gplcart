<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\handlers\job\export;

use gplcart\core\Container;

/**
 * Base export handler class
 */
class Base
{

    /**
     * File model instance
     * @var \gplcart\core\models\File $file
     */
    protected $file;

    /**
     * Store model instance
     * @var \gplcart\core\models\Store $store
     */
    protected $store;

    /**
     * Database offset
     * @var integer
     */
    protected $offset;

    /**
     * Max number of items to select from the database
     * @var integer
     */
    protected $limit;

    /**
     * Full path to working CSV file
     * @var string
     */
    protected $path;

    /**
     * An array of header mapping
     * @var array
     */
    protected $header = array();

    /**
     * An array of the current job data
     * @var type 
     */
    protected $job = array();

    /**
     * An array of selected items
     * @var array 
     */
    protected $items = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->file = Container::get('gplcart\\core\\models\\File');
        $this->store = Container::get('gplcart\\core\\models\\Store');
    }

    /**
     * Starts a new iteration
     * @param array $job
     * @return object Base
     */
    protected function start(array &$job)
    {
        $this->job = &$job;

        $this->limit = $this->job['data']['limit'];
        $this->path = $this->job['data']['operation']['file'];
        $this->header = $this->job['data']['operation']['csv']['header'];
        $this->offset = isset($this->job['context']['offset']) ? $this->job['context']['offset'] : 0;

        return $this;
    }

    /**
     * Finishes the current iteration
     * @return object Base
     */
    protected function finish()
    {
        if (empty($this->items)) {
            $this->job['status'] = false;
            $this->job['done'] = $this->job['total'];
            return $this;
        }

        $this->job['done'] = count($this->items);
        $this->job['context']['offset'] += $this->job['done'];
        return $this;
    }

    /**
     * Returns an array of CSV data based on the header info
     * @param array $item
     * @return array
     */
    protected function getData(array $item)
    {
        $data = array();
        foreach (array_keys($this->header) as $key) {
            $data[$key] = isset($item[$key]) ? $item[$key] : '';
        }
        return $data;
    }

    /**
     * Prepares images
     * @param array $data
     * @param array $item
     */
    protected function prepareImages(array &$data, array $item)
    {
        if (empty($data['images'])) {
            return null;
        }

        $store = $this->store->get($item['store_id']);

        $paths = array();
        foreach ((array) $data['images'] as $image) {
            if (isset($store['domain'])) {
                $path = $this->store->url($store);
                $paths[] = "$path/files/{$image['path']}";
                continue;
            }

            $paths[] = $image['path'];
        }

        $data['images'] = $this->join($paths);
        return null;
    }

    /**
     * Writes an array of row data to the CSV file
     * @param array $data
     */
    protected function write(array $data)
    {
        gplcart_file_csv($this->path, $data, $this->job['data']['delimiter']);
    }

    /**
     * Converts an array of values into a string
     * @param array $data
     * @return string
     */
    protected function join(array $data)
    {
        return implode($this->job['data']['multiple_delimiter'], (array) $data);
    }

}
