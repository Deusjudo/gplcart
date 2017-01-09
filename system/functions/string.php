<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

/**
 * Returns a hashed string
 * @param string $string
 * @param string $salt
 * @param integer $iterations
 * @return string
 */
function gplcart_string_hash($string, $salt = '', $iterations = 10)
{
    if ($salt === '') {
        $salt = gplcart_string_random();
    }

    if (!empty($iterations)) {
        $salt = sprintf("$2a$%02d$", $iterations) . $salt;
    }

    return crypt($string, $salt);
}

/**
 * Generates a random string
 * @param integer $size
 * @return string
 */
function gplcart_string_random($size = 32)
{
    return bin2hex(openssl_random_pseudo_bytes($size));
}

/**
 * Compares two hashed strings
 * @param string $str1
 * @param string $str2
 * @return boolean
 */
function gplcart_string_equals($str1, $str2)
{
    settype($str1, 'string');
    settype($str2, 'string');

    if (function_exists('hash_equals')) {
        return hash_equals($str1, $str2);
    }

    if (strlen($str1) != strlen($str2)) {
        return false;
    }

    $res = $str1 ^ $str2;
    $ret = 0;

    for ($i = strlen($res) - 1; $i >= 0; $i--) {
        $ret |= ord($res[$i]);
    }

    return !$ret;
}

/**
 * Replaces placeholders in the string
 * @param string $pattern
 * @param array $placeholders
 * @param array $data
 * @return string
 */
function gplcart_string_replace($pattern, array $placeholders, array $data)
{
    foreach ($placeholders as $placeholder => $data_key) {

        if (!isset($data[$data_key]) || !is_string($data[$data_key])) {
            unset($placeholders[$placeholder]);
            continue;
        }

        $placeholders[$placeholder] = $data[$data_key];
    }

    return $placeholders ? strtr($pattern, $placeholders) : '';
}

/**
 * Splits a text by new lines
 * @param string $string
 * @param null|integer $limit
 * @return array
 */
function gplcart_string_array($string, $limit = null)
{
    if (isset($limit)) {
        $array = explode("\n", str_replace("\r", "", $string), $limit);
    } else {
        $array = explode("\n", str_replace("\r", "", $string));
    }

    return array_filter(array_map('trim', $array));
}

/**
 * Formats a string by replacing variable placeholders
 * @param string $string A string containing placeholders
 * @param array $arguments An associative array of replacements
 * @return string
 */
function gplcart_string_format($string, array $arguments = array())
{
    foreach ($arguments as $key => $value) {
        switch ($key[0]) {
            case '@':
                $arguments[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                break;
            case '!':
                // Html
                break;
            case '%':
            default:
                $arguments[$key] = '<i class="placeholder">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</i>';
        }
    }

    return strtr($string, $arguments);
}

/**
 * Converts string to boolean type
 * @param string $value
 * @return boolean
 */
function gplcart_string_bool($value)
{
    if (!is_string($value)) {
        return (bool) $value;
    }

    $v = strtolower($value);

    $map = array(
        'y' => true, 'n' => false, 'yes' => true, 'no' => false,
        'true' => true, 'false' => false, '1' => true, '0' => false,
        'on' => true, 'off' => false
    );

    return isset($map[$v]) ? $map[$v] : false;
}
