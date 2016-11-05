<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\handlers\validator;

use core\models\Zone as ModelsZone;
use core\models\Country as ModelsCountry;
use core\handlers\validator\Base as BaseValidator;

/**
 * Provides methods to validate various database related data
 */
class Country extends BaseValidator
{

    /**
     * Country model instance
     * @var \core\models\Country $country
     */
    protected $country;

    /**
     * Zone model instance
     * @var \core\models\Zone $zone
     */
    protected $zone;

    /**
     * Constructor
     * @param ModelsCountry $country
     * @param ModelsZone $zone
     */
    public function __construct(ModelsCountry $country, ModelsZone $zone)
    {
        parent::__construct();

        $this->zone = $zone;
        $this->country = $country;
    }

    /**
     * Performs full validation of submitted country data
     * @param array $submitted
     * @param array $options
     * @return array|bool
     */
    public function country(array &$submitted, array $options = array())
    {
        $this->validateWeight($submitted);
        $this->validateDefault($submitted);
        $this->validateStatus($submitted);
        $this->validateCodeCountry($submitted);
        $this->validateNameCountry($submitted);
        $this->validateNativeNameCountry($submitted);
        $this->validateZoneCountry($submitted);

        if (empty($this->errors) && empty($submitted['default'])) {
            $this->country->unsetDefault($submitted['code']);
        }

        return empty($this->errors) ? true : $this->errors;
    }

    /**
     * Validates a zone ID
     * @param array $submitted
     */
    protected function validateZoneCountry(array $submitted)
    {
        if (empty($submitted['zone_id'])) {
            return true;
        }

        if (!is_numeric($submitted['zone_id'])) {
            $options = array('@field' => $this->language->text('Zone'));
            $this->errors['zone_id'] = $this->language->text('@field must be numeric', $options);
            return false;
        }

        $zone = $this->zone->get($submitted['zone_id']);

        if (empty($zone)) {
            $this->errors['zone_id'] = $this->language->text('Object @name does not exist', array(
                '@name' => $this->language->text('Zone')));
            return false;
        }

        return true;
    }

    /**
     * Validates a country name
     * @param array $submitted
     * @return boolean
     */
    protected function validateNameCountry(array &$submitted)
    {
        if (empty($submitted['name']) || mb_strlen($submitted['name']) > 255) {
            $options = array('@min' => 1, '@max' => 255, '@field' => $this->language->text('Name'));
            $this->errors['name'] = $this->language->text('@field must be @min - @max characters long', $options);
            return false;
        }

        return true;
    }

    /**
     * Validates a native country name
     * @param array $submitted
     * @return boolean
     */
    protected function validateNativeNameCountry(array &$submitted)
    {
        if (empty($submitted['native_name']) || mb_strlen($submitted['native_name']) > 255) {
            $options = array('@min' => 1, '@max' => 255, '@field' => $this->language->text('Native name'));
            $this->errors['native_name'] = $this->language->text('@field must be @min - @max characters long', $options);
            return false;
        }

        return true;
    }

    /**
     * Validates a country code
     * @param array $submitted
     * @return boolean
     */
    protected function validateCodeCountry(array &$submitted)
    {
        if (empty($submitted['code'])) {
            $this->errors['code'] = $this->language->text('@field is required', array(
                '@field' => $this->language->text('Code')
            ));
            return false;
        }

        if (!preg_match('/^[A-Z]{2}$/', $submitted['code'])) {
            $this->errors['code'] = $this->language->text('Invalid country code. It must conform ISO 3166-2 standard');
            return false;
        }

        $submitted['code'] = strtoupper($submitted['code']);

        if (isset($submitted['country']['code']) && ($submitted['country']['code'] === $submitted['code'])) {
            return true;
        }

        $country = $this->country->get($submitted['code']);

        if (empty($country)) {
            return true;
        }

        $this->errors['code'] = $this->language->text('@object already exists', array(
            '@object' => $this->language->text('Code')));
        return false;
    }

    /**
     * Checks country format fields
     * TODO: remove
     * 
     * @param string $value
     * @param array $options
     * @return boolean|array
     */
    public function format($value, array $options = array())
    {
        if (empty($value) && empty($options['required'])) {
            return true;
        }

        $country = empty($value['country']) ? '' : $value['country'];

        $countries = $this->country->getNames(true);
        $format = $this->country->getFormat($country, true);

        $conditions = array('status' => 1, 'country' => $country);
        $states = $this->state->getList($conditions);

        $errors = array();
        foreach ($format as $field => $info) {

            if ($field === 'state_id' && empty($states)) {
                continue;
            }

            if ($field === 'country' && $country === '' && !empty($countries)) {
                $errors['country'] = $this->language->text('Required field');
            }

            if (empty($info['required'])) {
                continue;
            }

            if (empty($value[$field]) || mb_strlen($value[$field]) > 255) {
                $errors[$field] = $this->language->text('Content must be %min - %max characters long', array(
                    '%min' => 1,
                    '%max' => 255
                ));
            }
        }

        return empty($errors) ? true : $errors;
    }

}
