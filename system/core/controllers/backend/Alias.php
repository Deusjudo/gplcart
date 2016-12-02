<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\controllers\backend;

use core\models\Alias as ModelsAlias;
use core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to the URL aliases
 */
class Alias extends BackendController
{

    /**
     * Url model instance
     * @var \core\models\Alias $alias
     */
    protected $alias;

    /**
     * Constructor
     * @param ModelsAlias $alias
     */
    public function __construct(ModelsAlias $alias)
    {
        parent::__construct();

        $this->alias = $alias;
    }

    /**
     * Displays the aliases overview page
     */
    public function listAlias()
    {
        $this->actionAlias();

        $query = $this->getFilterQuery();
        $total = $this->getTotalAlias($query);
        $limit = $this->setPager($total, $query);

        $keys = $this->alias->getIdKeys();
        $aliases = $this->getListAlias($limit, $query);

        $this->setData('id_keys', $keys);
        $this->setData('aliases', $aliases);

        $filters = array('id_value', 'id_key', 'alias');
        $this->setFilter($filters, $query);

        $this->setTitleListAlias();
        $this->setBreadcrumbListAlias();
        $this->outputListAlias();
    }

    /**
     * Applies an action to the selected aliases
     * @return null
     */
    protected function actionAlias()
    {
        $action = (string) $this->request->post('action');

        if (empty($action)) {
            return null;
        }

        $selected = (array) $this->request->post('selected', array());

        $deleted = 0;
        foreach ($selected as $id) {

            $alias = $this->alias->get($id);

            if (empty($alias)) {
                continue;
            }

            $entityname = preg_replace('/_id$/', '', $alias['id_key']);

            if (!$this->access("{$entityname}_edit")) {
                continue;
            }

            if ($action === 'delete') {
                $deleted += (int) $this->alias->delete($id);
            }
        }

        if ($deleted > 0) {
            $message = $this->text('Deleted %num aliases', array('%num' => $deleted));
            $this->setMessage($message, 'success', true);
        }

        return null;
    }

    /**
     * Returns total aliases found depending on some conditions
     * @param array $query
     * @return integer
     */
    protected function getTotalAlias(array $query)
    {
        $query['count'] = true;
        return (int) $this->alias->getList($query);
    }

    /**
     * Returns an array of aliases
     * @param integer $limit
     * @param array $query
     * @return array
     */
    protected function getListAlias($limit, array $query)
    {
        $query['limit'] = $limit;
        return $this->alias->getList($query);
    }

    /**
     * Sets titles on the aliases overview page
     */
    protected function setTitleListAlias()
    {
        $this->setTitle($this->text('Aliases'));
    }

    /**
     * Sets breadcrumbs on the aliases overview page
     */
    protected function setBreadcrumbListAlias()
    {
        $breadcrumb = array(
            'url' => $this->url('admin'),
            'text' => $this->text('Dashboard')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Renders the aliases overview page
     */
    protected function outputListAlias()
    {
        $this->output('content/alias/list');
    }

}