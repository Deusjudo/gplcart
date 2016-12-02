<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\controllers\backend;

use core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to languages
 */
class Language extends BackendController
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Displays the language edit form
     * @param string|null $code
     */
    public function editLanguage($code = null)
    {
        $language = $this->getLanguage($code);
        $default = $this->language->getDefault();
        $can_delete = $this->canDeleteLanguage($language);

        $this->setData('language', $language);
        $this->setData('can_delete', $can_delete);
        $this->setData('default_language', $default);

        $this->submitLanguage($language);

        $this->setTitleEditLanguage($language);
        $this->setBreadcrumbEditLanguage();
        $this->outputEditLanguage();
    }

    /**
     * Whether the language can be deleted
     * @return type
     */
    protected function canDeleteLanguage(array $language)
    {
        return (isset($language['code']) && $this->access('language_delete'));
    }

    /**
     * Returns a language
     * @param string $code
     * @return array
     */
    protected function getLanguage($code)
    {
        if (empty($code)) {
            return array();
        }

        $language = $this->language->get($code);

        if (empty($language)) {
            $this->outputError(404);
        }

        return $language;
    }

    /**
     * Saves a submitted language
     * @param array $language
     * @return null|void
     */
    protected function submitLanguage(array $language)
    {
        if ($this->isPosted('delete')) {
            return $this->deleteLanguage($language);
        }

        if (!$this->isPosted('save')) {
            return null;
        }

        $this->setSubmitted('language');
        $this->validateLanguage($language);

        if ($this->hasErrors('language')) {
            return null;
        }

        if (isset($language['code'])) {
            return $this->updateLanguage($language);
        }

        return $this->addLanguage();
    }

    /**
     * Deletes a language
     * @param array $language
     * @return null
     */
    protected function deleteLanguage(array $language)
    {
        $this->controlAccess('language_delete');

        $deleted = $this->language->delete($language['code']);

        if ($deleted) {
            $message = $this->text('Language has been deleted');
            $this->redirect('admin/settings/language', $message, 'success');
        }

        $message = $this->text('Unable to delete this language');
        $this->redirect('', $message, 'danger');
    }

    /**
     * Validates a language
     * @param array $language
     */
    protected function validateLanguage(array $language)
    {
        $this->setSubmittedBool('status');
        $this->setSubmittedBool('default');
        $this->setSubmitted('update', $language);
        $this->validate('language');
    }

    /**
     * Updates a language
     * @param array $language
     */
    protected function updateLanguage(array $language)
    {
        $this->controlAccess('language_edit');

        $submitted = $this->getSubmitted();
        $this->language->update($language['code'], $submitted);

        $message = $this->text('Language has been updated');
        $this->redirect('admin/settings/language', $message, 'success');
    }

    /**
     * Adds a new language
     */
    protected function addLanguage()
    {
        $this->controlAccess('language_add');

        $values = $this->getSubmitted();
        $this->language->add($values);

        $message = $this->text('Language has been added');
        $this->redirect('admin/settings/language', $message, 'success');
    }

    /**
     * Sets titles on the edit language page
     * @param array $language
     */
    protected function setTitleEditLanguage(array $language)
    {
        $title = $this->text('Add language');
        
        if (isset($language['code'])) {
            $vars = array('%name' => $language['native_name']);
            $title = $this->text('Edit language %name', $vars);
        }

        $this->setTitle($title);
    }

    /**
     * Sets breadcrumbs on the edit language page
     */
    protected function setBreadcrumbEditLanguage()
    {
        $breadcrumbs = array();

        $breadcrumbs[] = array(
            'url' => $this->url('admin'),
            'text' => $this->text('Dashboard')
        );

        $breadcrumbs[] = array(
            'url' => $this->url('admin/settings/language'),
            'text' => $this->text('Languages')
        );

        $this->setBreadcrumbs($breadcrumbs);
    }

    /**
     * Renders the edit language page templates
     */
    protected function outputEditLanguage()
    {
        $this->output('settings/language/edit');
    }

    /**
     * Displays the language overview page
     */
    public function listLanguage()
    {
        $this->refreshLanguage();

        $languages = $this->language->getList();
        $this->setData('languages', $languages);

        $this->setTitleListLanguage();
        $this->setBreadcrumbListLanguage();
        $this->outputListLanguage();
    }

    /**
     * Removes cached translations for the given language
     * @return null|void
     */
    protected function refreshLanguage()
    {
        $code = (string) $this->request->get('refresh');

        if (empty($code)) {
            return null;
        }

        $this->language->refresh($code);

        $message = $this->text('Cache for language @code has been deleted', array(
            '@code' => $code
        ));

        $this->redirect('', $message, 'success');
    }

    /**
     * Sets titles on the language overview page
     */
    protected function setTitleListLanguage()
    {
        $this->setTitle($this->text('Languages'));
    }

    /**
     * Sets breadcrumbs on the language overview page
     */
    protected function setBreadcrumbListLanguage()
    {
        $breadcrumb = array(
            'url' => $this->url('admin'),
            'text' => $this->text('Dashboard')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Renders the language overview page templates
     */
    protected function outputListLanguage()
    {
        $this->output('settings/language/list');
    }

}