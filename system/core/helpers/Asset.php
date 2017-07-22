<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\helpers;

use gplcart\core\helpers\Request as RequestHelper;

/**
 * Helpers to work with CSS/JS files
 */
class Asset
{

    /**
     * Default weight to add to the the next asset
     */
    const WEIGHT_STEP = 20;

    /**
     * Request class instance
     * @var \gplcart\core\helpers\Request $request
     */
    protected $request;

    /**
     * An array of added assests
     * @var array
     */
    protected $assets = array();

    /**
     * Constructor
     * @param RequestHelper $request
     */
    public function __construct(RequestHelper $request)
    {
        $this->request = $request;
    }

    /**
     * Adds a JS file
     * @param string $script
     * @param array $data
     * @return bool|array
     */
    public function setJs($script, $data = array())
    {
        $data += array(
            'type' => 'js',
            'asset' => $script,
            'position' => 'top'
        );

        if (!isset($data['weight'])) {
            $data['weight'] = $this->getNextWeight('js', $data['position']);
        }

        return $this->set($data);
    }

    /**
     * Adds a CSS file
     * @param string $css
     * @param array $data
     * @return bool|array
     */
    public function setCss($css, $data = array())
    {
        $data += array(
            'asset' => $css,
            'type' => 'css',
        );

        if (!isset($data['weight'])) {
            $data['weight'] = $this->getNextWeight('css', 'top');
        }

        return $this->set($data);
    }

    /**
     * Returns an array of added JS assest
     * @param string $pos Top or Bottom
     * @return array
     */
    public function getJs($pos)
    {
        $js = $this->get('js', $pos);
        gplcart_array_sort($js);
        return $js;
    }

    /**
     * Returns an array of added CSS assets
     * @return array
     */
    public function getCss()
    {
        $css = $this->get('css', 'top');
        gplcart_array_sort($css);
        return $css;
    }

    /**
     * Returns a weight for the next asset
     * @param string $type
     * @param string $pos
     * @return integer
     */
    public function getNextWeight($type, $pos)
    {
        $count = $this->getLastWeight($type, $pos);
        $weight = $count * self::WEIGHT_STEP + self::WEIGHT_STEP;
        return $weight;
    }

    /**
     * Returns a weight of the last added asset
     * @param string $type Either "css" or "js"
     * @param string $pos Either "top" or "bottom"
     * @return integer
     */
    public function getLastWeight($type, $pos)
    {
        return empty($this->assets[$type][$pos]) ? 0 : count($this->assets[$type][$pos]);
    }

    /**
     * Returns an array of asset items
     * @param string $type
     * @param string $position
     * @return array
     */
    protected function get($type, $position)
    {
        if (empty($this->assets[$type][$position])) {
            return array();
        }

        return $this->assets[$type][$position];
    }

    /**
     * Sets an asset
     * @param array $asset
     * @return bool|array
     */
    protected function set(array $asset)
    {
        $build = $this->build($asset);

        if (empty($build['asset'])) {
            return false;
        }

        if (isset($this->assets[$build['type']][$build['position']][$build['key']])) {
            return false;
        }

        $this->assets[$build['type']][$build['position']][$build['key']] = $build;
        return $this->assets[$build['type']];
    }

    /**
     * Builds asset data
     * @param array $data
     * @return array
     */
    public function build(array $data)
    {
        if (strpos($data['asset'], 'http') === 0) {
            $type = 'external';
        } else {
            $type = pathinfo($data['asset'], PATHINFO_EXTENSION);
        }

        $data += array(
            'type' => $type,
            'position' => 'top',
            'condition' => '',
            'version' => 'v',
            'text' => false,
            'aggregate' => $type !== 'external'
        );

        if (!in_array($data['type'], array('css', 'js'))) {
            $data['text'] = true;
        }

        if ($type !== 'external' && $type != $data['type']) {
            $data['text'] = true;
        }

        if ($data['text']) {
            $data['key'] = 'text.' . md5($data['asset']);
            return $data;
        }

        if (strpos($data['asset'], GC_ROOT_DIR) === 0) {
            $data['file'] = $data['asset'];
            $data['asset'] = gplcart_relative_path($data['asset']);
        } else if ($type !== 'external') {
            $data['file'] = gplcart_absolute_path($data['asset']);
        }

        if (isset($data['file']) && !file_exists($data['file'])) {
            return array();
        }

        $data['key'] = $type === 'external' ? $data['asset'] : $this->request->base(true) . $data['asset'];

        if (!empty($data['version']) && isset($data['file'])) {
            $data['key'] .= "?{$data['version']}=" . filemtime($data['file']);
        }

        return $data;
    }

}
