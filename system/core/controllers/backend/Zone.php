<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\controllers\backend;

use gplcart\core\models\Zone as ZoneModel;
use gplcart\core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to geo zones
 */
class Zone extends BackendController
{

    /**
     * Zone model instance
     * @var \gplcart\core\models\Zone $zone
     */
    protected $zone;

    /**
     * An array of zone data
     * @var array
     */
    protected $data_zone = array();

    /**
     * @param ZoneModel $zone
     */
    public function __construct(ZoneModel $zone)
    {
        parent::__construct();

        $this->zone = $zone;
    }

    /**
     * Displays the zone overview page
     */
    public function listZone()
    {
        $this->actionListZone();

        $this->setTitleListZone();
        $this->setBreadcrumbListZone();

        $this->setFilterListZone();
        $this->setTotalListZone();
        $this->setPagerLimit();

        $this->setData('zones', $this->getListZone());
        $this->outputListZone();
    }

    /**
     * Sets filter on the zone overview page
     */
    protected function setFilterListZone()
    {
        $this->setFilter(array('title', 'status'));
    }

    /**
     * Sets a total number of zones found for the filter conditions
     */
    public function setTotalListZone()
    {
        $query = $this->query_filter;
        $query['count'] = true;
        $this->total = (int) $this->zone->getList($query);
    }

    /**
     * Applies an action to the selected zones
     */
    protected function actionListZone()
    {
        $value = $this->getPosted('value', '', true, 'string');
        $action = $this->getPosted('action', '', true, 'string');
        $selected = $this->getPosted('selected', array(), true, 'array');

        if (empty($action)) {
            return null;
        }

        $updated = $deleted = 0;
        foreach ($selected as $id) {

            if ($action === 'status' && $this->access('zone_edit')) {
                $updated += (int) $this->zone->update($id, array('status' => $value));
            }

            if ($action === 'delete' && $this->access('zone_delete')) {
                $deleted += (int) $this->zone->delete($id);
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
     * Returns an array of zones
     * @return array
     */
    protected function getListZone()
    {
        $query = $this->query_filter;
        $query['limit'] = $this->limit;

        return $this->zone->getList($query);
    }

    /**
     * Sets title on the zones overview page
     */
    protected function setTitleListZone()
    {
        $this->setTitle($this->text('Zones'));
    }

    /**
     * Sets breadcrumbs on the zone overview page
     */
    protected function setBreadcrumbListZone()
    {
        $this->setBreadcrumbBackend();
    }

    /**
     * Render and output the zone overview page
     */
    protected function outputListZone()
    {
        $this->output('settings/zone/list');
    }

    /**
     * Displays the zone edit page
     * @param null|integer $zone_id
     */
    public function editZone($zone_id = null)
    {
        $this->setZone($zone_id);

        $this->setTitleEditZone();
        $this->setBreadcrumbEditZone();

        $this->setData('zone', $this->data_zone);
        $this->setData('can_delete', $this->canDeleteZone());

        $this->submitEditZone();
        $this->outputEditZone();
    }

    /**
     * Whether the zone can be deleted
     * @return bool
     */
    protected function canDeleteZone()
    {
        return isset($this->data_zone['zone_id'])//
                && $this->zone->canDelete($this->data_zone['zone_id'])//
                && $this->access('zone_delete');
    }

    /**
     * Sets a zone data
     * @param integer $zone_id
     */
    protected function setZone($zone_id)
    {
        if (is_numeric($zone_id)) {
            $this->data_zone = $this->zone->get($zone_id);
            if (empty($this->data_zone)) {
                $this->outputHttpStatus(404);
            }
        }
    }

    /**
     * Handles a submitted zone data
     */
    protected function submitEditZone()
    {
        if ($this->isPosted('delete')) {
            $this->deleteZone();
            return null;
        }

        if (!$this->isPosted('save') || !$this->validateEditZone()) {
            return null;
        }

        if (isset($this->data_zone['zone_id'])) {
            $this->updateZone();
        } else {
            $this->addZone();
        }
    }

    /**
     * Validates a submitted zone
     * @return bool
     */
    protected function validateEditZone()
    {
        $this->setSubmitted('zone');
        $this->setSubmittedBool('status');
        $this->setSubmitted('update', $this->data_zone);

        $this->validateComponent('zone');

        return !$this->hasErrors();
    }

    /**
     * Deletes a zone
     */
    protected function deleteZone()
    {
        $this->controlAccess('zone_delete');

        $deleted = $this->zone->delete($this->data_zone['zone_id']);

        if ($deleted) {
            $message = $this->text('Zone has been deleted');
            $this->redirect('admin/settings/zone', $message, 'success');
        }

        $message = $this->text('Unable to delete');
        $this->redirect('', $message, 'danger');
    }

    /**
     * Updates a zone
     */
    protected function updateZone()
    {
        $this->controlAccess('zone_edit');

        $values = $this->getSubmitted();
        $this->zone->update($this->data_zone['zone_id'], $values);

        $message = $this->text('Zone has been updated');
        $this->redirect('admin/settings/zone', $message, 'success');
    }

    /**
     * Adds a new zone
     */
    protected function addZone()
    {
        $this->controlAccess('zone_add');
        $this->zone->add($this->getSubmitted());

        $message = $this->text('Zone has been added');
        $this->redirect('admin/settings/zone', $message, 'success');
    }

    /**
     * Sets titles on the edit zone page
     */
    protected function setTitleEditZone()
    {
        $title = $this->text('Add zone');

        if (isset($this->data_zone['zone_id'])) {
            $vars = array('%name' => $this->data_zone['title']);
            $title = $this->text('Edit zone %name', $vars);
        }

        $this->setTitle($title);
    }

    /**
     * Sets breadcrumbs on the edit zone page
     */
    protected function setBreadcrumbEditZone()
    {
        $this->setBreadcrumbBackend();

        $breadcrumb = array(
            'text' => $this->text('Zones'),
            'url' => $this->url('admin/settings/zone')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Render and output the edit zone page
     */
    protected function outputEditZone()
    {
        $this->output('settings/zone/edit');
    }

}
