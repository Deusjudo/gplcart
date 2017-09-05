<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\controllers\backend;

use gplcart\core\models\Module as ModuleModel,
    gplcart\core\models\Country as CountryModel,
    gplcart\core\models\Collection as CollectionModel;
use gplcart\core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to multistore functionality
 */
class Store extends BackendController
{

    /**
     * Module model instance
     * @var \gplcart\core\models\Module $module
     */
    protected $module;

    /**
     * Country model instance
     * @var \gplcart\core\models\Country $country
     */
    protected $country;

    /**
     * Collection model instance
     * @var \gplcart\core\models\Collection $collection
     */
    protected $collection;

    /**
     * The current store
     * @var array
     */
    protected $data_store = array();

    /**
     * @param ModuleModel $module
     * @param CollectionModel $collection
     * @param CountryModel $country
     */
    public function __construct(ModuleModel $module,
            CollectionModel $collection, CountryModel $country)
    {
        parent::__construct();

        $this->module = $module;
        $this->country = $country;
        $this->collection = $collection;
    }

    /**
     * Displays the store overview page
     */
    public function listStore()
    {
        $this->actionListStore();

        $this->setTitleListStore();
        $this->setBreadcrumbListStore();

        $this->setFilterListStore();
        $this->setTotalListStore();
        $this->setPagerLimit();

        $this->setData('default_store', $this->store->getDefault());
        $this->setData('stores', $this->getListStore());
        $this->outputListStore();
    }

    /**
     * Set filter on the store overview page
     */
    protected function setFilterListStore()
    {
        $this->setFilter(array('name', 'domain', 'basepath', 'status'));
    }

    /**
     * Sets a total number of stores found for the filter conditions
     */
    protected function setTotalListStore()
    {
        $query = $this->query_filter;
        $query['count'] = true;
        $this->total = (int) $this->store->getList($query);
    }

    /**
     * Applies an action to the selected stores
     */
    protected function actionListStore()
    {
        list($selected, $action, $value) = $this->getPostedAction();

        if (empty($action)) {
            return null;
        }

        $updated = $deleted = 0;
        foreach ($selected as $id) {

            if ($action === 'status' && $this->access('store_edit')) {
                $updated += (int) $this->store->update($id, array('status' => (int) $value));
            }

            if ($action === 'delete' && $this->access('store_delete') && !$this->store->isDefault($id)) {
                $deleted += (int) $this->store->delete($id);
            }
        }

        if ($updated > 0) {
            $message = $this->text('Updated %num items', array('%num' => $updated));
            $this->setMessage($message, 'success', true);
        }

        if ($deleted > 0) {
            $message = $this->text('Deleted %num items', array('%num' => $deleted));
            $this->setMessage($message, 'success', true);
        }
    }

    /**
     * Returns an array of stores
     * @return array
     */
    protected function getListStore()
    {
        $query = $this->query_filter;
        $query['limit'] = $this->limit;

        return $this->store->getList($query);
    }

    /**
     * Sets titles on the store overview page
     */
    protected function setTitleListStore()
    {
        $this->setTitle($this->text('Stores'));
    }

    /**
     * Sets breadcrumbs on the store overview page
     */
    protected function setBreadcrumbListStore()
    {
        $this->setBreadcrumbHome();
    }

    /**
     * Render and output the store overview page
     */
    protected function outputListStore()
    {
        $this->output('settings/store/list');
    }

    /**
     * Displays the store settings form
     * @param integer|null $store_id
     */
    public function editStore($store_id = null)
    {
        $this->setStore($store_id);

        $this->seTitleEditStore();
        $this->setBreadcrumbEditStore();

        $this->setData('store', $this->data_store);
        $this->setData('themes', $this->getListThemeStore());
        $this->setData('is_default', $this->isDefaultStore());
        $this->setData('can_delete', $this->canDeleteStore());
        $this->setData('countries', $this->getCountriesStore());
        $this->setData('collections', $this->getListCollectionStore());

        $this->submitEditStore();
        $this->setDataEditStore();

        $this->setJsEditStore();
        $this->outputEditStore();
    }

    /**
     * Returns an array of country names
     * @return array
     */
    protected function getCountriesStore()
    {
        return $this->country->getIso();
    }

    /**
     * Whether the store can be deleted
     * @return boolean
     */
    protected function canDeleteStore()
    {
        return isset($this->data_store['store_id'])//
                && $this->store->canDelete($this->data_store['store_id'])//
                && $this->access('store_delete')//
                && !$this->isDefaultStore();
    }

    /**
     * Whether the store is default
     * @return boolean
     */
    protected function isDefaultStore()
    {
        return isset($this->data_store['store_id'])//
                && $this->store->isDefault($this->data_store['store_id']);
    }

    /**
     * Sets a store data
     * @param mixed $store_id
     */
    protected function setStore($store_id)
    {
        $this->data_store = array('data' => $this->store->defaultConfig());

        if (is_numeric($store_id)) {
            $this->data_store = $this->store->get($store_id);
            if (empty($this->data_store)) {
                $this->outputHttpStatus(404);
            }
        }
    }

    /**
     * Returns an array of available theme modules excluding backend theme
     * @return array
     */
    protected function getListThemeStore()
    {
        $themes = $this->module->getByType('theme', true);
        unset($themes[$this->theme_backend]);

        return $themes;
    }

    /**
     * Returns an array of enabled collection for the store keyed by an entity name
     * @return array
     */
    protected function getListCollectionStore()
    {
        if (empty($this->data_store['store_id'])) {
            return array();
        }

        $conditions = array(
            'status' => 1,
            'store_id' => $this->data_store['store_id']
        );

        $collections = (array) $this->collection->getList($conditions);

        $list = array();
        foreach ($collections as $collection) {
            $list[$collection['type']][$collection['collection_id']] = $collection;
        }

        return $list;
    }

    /**
     * Handles a submitted store data
     */
    protected function submitEditStore()
    {
        if ($this->isPosted('delete')) {
            $this->deleteStore();
        } else if ($this->isPosted('save') && $this->validateEditStore()) {
            if (isset($this->data_store['store_id'])) {
                $this->updateStore();
            } else {
                $this->addStore();
            }
        }
    }

    /**
     * Validates a submitted store data
     * @return bool
     */
    protected function validateEditStore()
    {
        $this->setSubmitted('store', null, false);
        $this->setSubmittedBool('status');
        $this->setSubmitted('update', $this->data_store);
        $this->setSubmittedBool('data.anonymous_checkout');

        foreach (array('email', 'phone', 'fax', 'map') as $field) {
            $this->setSubmittedArray("data.$field");
        }

        $this->validateComponent('store');

        return !$this->hasErrors();
    }

    /**
     * Deletes a store
     */
    protected function deleteStore()
    {
        $this->controlAccess('store_delete');

        if ($this->store->delete($this->data_store['store_id'])) {
            $this->redirect('admin/settings/store', $this->text('Store has been deleted'), 'success');
        }

        $this->redirect('', $this->text('Unable to delete'), 'danger');
    }

    /**
     * Updates a store
     */
    protected function updateStore()
    {
        $this->controlAccess('store_edit');
        $this->store->update($this->data_store['store_id'], $this->getSubmitted());
        $this->redirect('admin/settings/store', $this->text('Store has been updated'), 'success');
    }

    /**
     * Adds a new store
     */
    protected function addStore()
    {
        $this->controlAccess('store_add');
        $this->store->add($this->getSubmitted());
        $this->redirect('admin/settings/store', $this->text('Store has been added'), 'success');
    }

    /**
     * Prepares a store data
     */
    protected function setDataEditStore()
    {
        foreach (array('logo', 'favicon') as $field) {
            $value = $this->getData("store.data.$field");
            if (!empty($value)) {
                $this->setData("store.{$field}_thumb", $this->image($value));
            }
        }

        foreach (array('email', 'phone', 'fax', 'map') as $field) {
            $value = $this->getData("store.data.$field");
            if (isset($value)) {
                $this->setData("store.data.$field", implode("\n", (array) $value));
            }
        }
    }

    /**
     * Sets JS on the store edit page
     */
    protected function setJsEditStore()
    {
        if (!empty($this->data_store['data']['map'])) {
            $this->setJsSettings('map', $this->data_store['data']['map']);
        }
    }

    /**
     * Sets titles on the store edit page
     */
    protected function seTitleEditStore()
    {
        if (isset($this->data_store['store_id'])) {
            $vars = array('%name' => $this->data_store['name']);
            $title = $this->text('Edit store %name', $vars);
        } else {
            $title = $this->text('Add store');
        }

        $this->setTitle($title);
    }

    /**
     * Sets breadcrumbs on the store edit page
     */
    protected function setBreadcrumbEditStore()
    {
        $this->setBreadcrumbHome();

        $breadcrumb = array(
            'url' => $this->url('admin/settings/store'),
            'text' => $this->text('Stores')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Render and output the store edit page
     */
    protected function outputEditStore()
    {
        $this->output('settings/store/edit');
    }

}
