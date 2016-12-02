<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\controllers\backend;

use core\helpers\Tool;
use core\controllers\backend\Controller as BackendController;

class Settings extends BackendController
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Displays edit settings form
     */
    public function editSettings()
    {
        $this->controlAccessSuperAdmin();

        $settings = $this->getSettings();
        $this->setData('settings', $settings);

        $this->submitSettings();
        $this->setDataEditSettings();

        $this->setTitleEditSettings();
        $this->setBreadcrumbEditSettings();
        $this->outputEditSettings();
    }

    /**
     * Returns an array of settings with their default values
     * @return array
     */
    protected function getDefaultSettings()
    {
        return array(
            'cron_key' => '',
            'error_level' => 2,
            'gapi_email' => '',
            'gapi_browser_key' => '',
            'gapi_certificate' => '',
            'email_method' => 'mail',
            'smtp_auth' => 1,
            'smtp_secure' => 'tls',
            'smtp_host' => array('smtp.gmail.com'),
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_port' => 587
        );
    }

    /**
     * Returns an array of settings
     * @return array
     */
    protected function getSettings()
    {
        $default = $this->getDefaultSettings();
        $saved = $this->config();

        return Tool::merge($default, $saved);
    }

    /**
     * Saves submitted settings
     */
    protected function submitSettings()
    {
        if (!$this->isPosted('save')) {
            return;
        }

        $this->setSubmitted('settings');
        $this->validateSettings();

        if ($this->hasErrors('settings')) {
            return;
        }

        $this->updateSettings();
    }

    /**
     * Validates submitted settings
     */
    protected function validateSettings()
    {
        $this->setSubmittedBool('smtp_auth');
        $this->validate('settings');
    }

    /**
     * Updates common setting with submitted values
     */
    protected function updateSettings()
    {
        $this->controlAccess('settings_edit');

        if ($this->isPosted('delete_gapi_certificate')) {
            unlink(GC_FILE_DIR . '/' . $this->config('gapi_certificate'));
            $this->config->reset('gapi_certificate');
        }

        $submitted = $this->getSubmitted();

        foreach ($submitted as $key => $value) {
            $this->config->set($key, $value);
        }

        $message = $this->text('Settings have been updated');
        $this->redirect('', $message, 'success');
    }

    /**
     * Prepares settings values before passing them to template
     */
    protected function setDataEditSettings()
    {
        $smtp_host = $this->getData('settings.smtp_host');
        $this->setData('settings.smtp_host', implode("\n", (array) $smtp_host));
    }

    /**
     * Sets titles on the settings form page
     */
    protected function setTitleEditSettings()
    {
        $this->setTitle($this->text('Settings'));
    }

    /**
     * Sets breadcrumbs on the settings form page
     */
    protected function setBreadcrumbEditSettings()
    {
        $breadcrumb = array(
            'url' => $this->url('admin'),
            'text' => $this->text('Dashboard')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Renders settings page
     */
    protected function outputEditSettings()
    {
        $this->output('settings/settings');
    }

}