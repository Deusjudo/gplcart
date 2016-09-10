<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\models;

use PDO;
use core\Model;
use core\Logger;
use core\classes\Tool;
use core\classes\Session;
use core\models\Mail as ModelsMail;
use core\models\Address as ModelsAddress;
use core\models\UserRole as ModelsUserRole;
use core\models\Language as ModelsLanguage;

use core\exceptions\UserAccessException;

/**
 * Manages basic behaviors and data related to users
 */
class User extends Model
{

    /**
     * Address model instance
     * @var \core\models\Address $address;
     */
    protected $address;

    /**
     * User role model instance
     * @var \core\models\UserRole $role
     */
    protected $role;

    /**
     * Mail model instance
     * @var \core\models\Mail $mail
     */
    protected $mail;

    /**
     * Language model instance
     * @var \core\models\Language $language
     */
    protected $language;

    /**
     * Session class instance
     * @var \core\classes\Session $session
     */
    protected $session;

    /**
     * Logger class instance
     * @var \core\Logger $logger
     */
    protected $logger;

    /**
     * Constructor
     * @param ModelsAddress $address
     * @param ModelsUserRole $role
     * @param ModelsMail $mail
     * @param ModelsLanguage $language
     * @param Session $session
     * @param Logger $logger
     */
    public function __construct(ModelsAddress $address, ModelsUserRole $role,
            ModelsMail $mail, ModelsLanguage $language, Session $session,
            Logger $logger)
    {
        parent::__construct();

        $this->mail = $mail;
        $this->role = $role;
        $this->logger = $logger;
        $this->address = $address;
        $this->session = $session;
        $this->language = $language;
    }

    /**
     * Adds a user
     * @param array $data
     * @return integer
     */
    public function add(array $data)
    {
        $this->hook->fire('add.user.before', $data);

        if (empty($data)) {
            return false;
        }

        $values = array(
            'created' => empty($data['created']) ? GC_TIME : (int) $data['created'],
            'modified' => 0,
            'email' => $data['email'],
            'name' => $data['name'],
            'hash' => Tool::hash($data['password']),
            'data' => empty($data['data']) ? serialize(array()) : serialize((array) $data['data']),
            'status' => !empty($data['status']),
            'role_id' => isset($data['role_id']) ? (int) $data['role_id'] : 0,
            'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : $this->config->get('store', 1),
        );

        $user_id = $this->db->insert('user', $values);

        if (!empty($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $address['user_id'] = $user_id;
                $this->address->add($address);
            }
        }

        $this->hook->fire('add.user.after', $data, $user_id);
        return $user_id;
    }

    /**
     * Updates a user
     * @param integer $user_id
     * @param array $data
     * @return boolean     *
     */
    public function update($user_id, array $data)
    {
        $this->hook->fire('update.user.before', $user_id, $data);

        if (empty($user_id)) {
            return false;
        }

        if (!empty($data['password'])) { // not isset()!
            $data['hash'] = Tool::hash($data['password']);
        }

        $data += array('modified' => GC_TIME);
        $values = $this->db->filterValues('user', $data);

        if (isset($data['addresses'])) {
            foreach ((array) $data['addresses'] as $address) {
                $this->setAddress($user_id, $address);
            }
        }

        $result = false;

        if (!empty($values)) {
            $result = $this->db->update('user', $values, array('user_id' => $user_id));
            $this->hook->fire('update.user.after', $user_id, $values, $result);
        }

        return (bool) $result;
    }

    /**
     * Deletes a user
     * @param integer $user_id
     * @return boolean
     */
    public function delete($user_id)
    {
        $this->hook->fire('delete.user.before', $user_id);

        if (empty($user_id)) {
            return false;
        }

        if (!$this->canDelete($user_id)) {
            return false;
        }

        $this->db->delete('user', array('user_id' => (int) $user_id));
        $this->db->delete('cart', array('user_id' => $user_id));
        $this->db->delete('wishlist', array('user_id' => $user_id));
        $this->db->delete('review', array('user_id' => $user_id));
        $this->db->delete('address', array('user_id' => $user_id));
        $this->db->delete('rating_user', array('user_id' => $user_id));

        $this->hook->fire('delete.user.after', $user_id);
        return true;
    }

    /**
     * Whether the user can be deleted
     * @param integer $user_id
     * @return boolean
     */
    public function canDelete($user_id)
    {
        if ($this->isSuperadmin($user_id)) {
            return false;
        }

        $sql = 'SELECT * FROM orders WHERE user_id=:user_id';
        $sth = $this->db->prepare($sql);
        $sth->execute(array(':user_id' => (int) $user_id));

        return !$sth->fetchColumn();
    }

    /**
     * Whether the user is superadmin
     * @param integer|null $user_id
     * @return boolean
     */
    public function isSuperadmin($user_id = null)
    {
        if (isset($user_id)) {
            return ($this->superadmin() === (int) $user_id);
        }

        return ($this->superadmin() === $this->id());
    }

    /**
     * Returns superadmin user ID
     * @return integer
     */
    public function superadmin()
    {
        return (int) $this->config->get('user_superadmin', 1);
    }

    /**
     * Returns an ID of the current user
     * @return integer
     */
    public function id()
    {
        return (int) $this->session->get('user', 'user_id');
    }

    /**
     * Whether the user has an access
     * @param string $permission
     * @param mixed $user
     * @return boolean
     */
    public function access($permission, $user = null)
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        $permissions = $this->permissions($user);
        return in_array($permission, $permissions);
    }

    /**
     * Returns user permissions
     * @param mixed $user
     * @return array
     */
    public function permissions($user = null)
    {
        $role_id = $this->roleId($user);

        if (empty($role_id)) {
            return array();
        }

        $role = $this->role->get($role_id);

        if (isset($role['permissions'])) {
            return (array) $role['permissions'];
        }

        return array();
    }

    /**
     * Returns a role ID
     * @param mixed $user
     * @return integer
     */
    public function roleId($user = null)
    {
        if (!isset($user)) {
            return (int) $this->session->get('user', 'role_id');
        }

        if (is_numeric($user)) {
            $user = $this->get($user);
        }

        return isset($user['role_id']) ? (int) $user['role_id'] : 0;
    }

    /**
     * Loads a user
     * @param integer $user_id
     * @param integer|null $store_id
     * @return array
     */
    public function get($user_id, $store_id = null)
    {
        $this->hook->fire('get.user.before', $user_id, $store_id);

        $sql = 'SELECT u.*, r.status AS role_status, r.name AS role_name
                FROM user u
                LEFT JOIN role r ON (u.role_id = r.role_id)
                WHERE u.user_id=:user_id';

        $where = array(':user_id' => (int) $user_id);

        if (isset($store_id)) {
            $sql .= ' AND u.store_id=:store_id';
            $where[':store_id'] = (int) $store_id;
        }

        $sth = $this->db->prepare($sql);
        $sth->execute($where);

        $user = $sth->fetch(PDO::FETCH_ASSOC);

        if (!empty($user)) {
            $user['data'] = unserialize($user['data']);
        }

        $this->hook->fire('get.user.after', $user_id, $user);
        return $user;
    }

    /**
     * Logs in a user
     * @param array $data
     * @throws UserAccessException
     */
    public function login(array $data)
    {
        $this->hook->fire('login.before', $data);

        if (empty($data['email']) || empty($data['password'])) {
            return false;
        }

        $user = $this->getByEmail($data['email']);

        if (empty($user['status'])) {
            return false;
        }

        if (!Tool::hashEquals($user['hash'], Tool::hash($data['password'], $user['hash'], false))) {
            return false;
        }

        if (!$this->session->regenerate(true)) {
            throw new UserAccessException('Failed to regenerate the current session');
        }

        unset($user['hash']);
        $this->session->set('user', null, $user);

        $this->logLogin($user);

        $result = array(
            'user' => $user,
            'message' => '',
            'severity' => 'success',
            'redirect' => $this->getLoginRedirect($user),
        );

        $this->hook->fire('login.after', $data, $result);
        return $result;
    }

    /**
     * Registers a user
     * @param array $data
     * @return array
     */
    public function register(array $data)
    {
        $this->hook->fire('register.user.before', $data);

        $login = $this->config->get('user_registration_login', true);
        $status = $this->config->get('user_registration_status', true);

        $data['status'] = $status;
        $data['user_id'] = $this->add($data);

        $this->logRegistration($data);
        $this->emailRegistration($data);

        $result = array(
            'redirect' => '/',
            'severity' => 'success',
            'message' => $this->language->text('Your account has been created'));

        if ($login && $status) {
            $result = $this->login($data);
        }

        $this->hook->fire('register.user.after', $data, $result);
        return $result;
    }

    /**
     * Sends E-mails to various recepients to inform them about the registration
     * @param array $data
     */
    protected function emailRegistration(array $data)
    {
        // Send an e-mail to the customer
        if ($this->config->get('user_registration_email_customer', true)) {
            $this->mail->set('user_registered_customer', array($data));
        }

        // Send an e-mail to admin
        if ($this->config->get('user_registration_email_admin', true)) {
            $this->mail->set('user_registered_admin', array($data));
        }
    }

    /**
     * Loads a user by an email
     * @param string $email
     * @return array
     */
    public function getByEmail($email)
    {
        $sth = $this->db->prepare('SELECT * FROM user WHERE email=:email');
        $sth->execute(array(':email' => $email));
        $user = $sth->fetch(PDO::FETCH_ASSOC);

        if (empty($user)) {
            return array();
        }

        $user['data'] = unserialize($user['data']);
        return $user;
    }

    /**
     * Loads a user by a name
     * @param string $name
     * @return array
     */
    public function getByName($name)
    {
        $sth = $this->db->prepare('SELECT * FROM user WHERE name=:name');
        $sth->execute(array(':name' => $name));
        $user = $sth->fetch(PDO::FETCH_ASSOC);

        if (empty($user)) {
            return array();
        }

        $user['data'] = unserialize($user['data']);
        return $user;
    }

    /**
     * Returns the current user
     * @return array
     */
    public function current()
    {
        return (array) $this->session->get('user', null, array());
    }

    /**
     * Logs out the current user
     * @return array
     * @throws UserAccessException
     */
    public function logout()
    {
        $user_id = $this->id();
        $this->hook->fire('logout.before', $user_id);

        if (empty($user_id)) {
            return array('message' => '', 'severity' => '', 'redirect' => '/');
        }

        if (!$this->session->delete()) {
            throw new UserAccessException('Failed to delete the session on logout');
        }

        $user = $this->get($user_id);

        $this->logLogout($user);

        $result = array(
            'user' => $user,
            'message' => '',
            'severity' => 'success',
            'redirect' => $this->getLogOutRedirect($user),
        );

        $this->hook->fire('logout.after', $result);
        return $result;
    }

    /**
     * Generates a random password
     * @return string
     */
    public function generatePassword()
    {
        $hash = crypt(Tool::randomString(), Tool::randomString());
        return str_replace(array('+', '/', '='), '', base64_encode($hash));
    }

    /**
     * Performs reset password operation
     * @param array $data
     * @return array
     */
    public function resetPassword(array $data)
    {
        $this->hook->fire('reset.password.before', $data);

        if (empty($data['user']['user_id'])) {
            return array('message' => '', 'severity' => '', 'redirect' => '');
        }

        if (isset($data['password'])) {
            $result = $this->setNewPassword($data['user'], $data['password']);
        } else {
            $result = $this->setResetPassword($data['user']);
        }

        $this->hook->fire('reset.password.after', $data, $result);
        return $result;
    }

    /**
     * Sets reset token and sends reset link
     * @param array $user
     * @return array
     */
    protected function setResetPassword(array $user)
    {
        $lifetime = (int) $this->config->get('user_reset_password_lifespan', 86400);

        $user['data']['reset_password'] = array(
            'token' => Tool::randomString(),
            'expires' => GC_TIME + $lifetime,
        );

        $this->update($user['user_id'], array('data' => $user['data']));
        $this->mail->set('user_reset_password', array($user));

        return array(
            'redirect' => 'forgot',
            'severity' => 'success',
            'message' => $this->language->text('Password reset link has been sent to your E-mail')
        );
    }

    /**
     * Sets a new password
     * @param array $user
     * @param string $password
     * @return array
     */
    protected function setNewPassword(array $user, $password)
    {
        $user['password'] = $password;

        unset($user['data']['reset_password']);
        $this->update($user['user_id'], $user);
        $this->mail->set('user_changed_password', array($user));

        return array(
            'redirect' => 'login',
            'severity' => 'success',
            'message' => $this->language->text('Your password has been successfully changed')
        );
    }

    /**
     * Returns allowed min and max password length
     * @return array
     */
    public function getPasswordLength()
    {
        return array(
            'min' => $this->config->get('user_password_min_length', 8),
            'max' => $this->config->get('user_password_max_length', 255)
        );
    }

    /**
     * Returns an array of users or counts them
     * @param array $data
     * @return array|integer
     */
    public function getList(array $data = array())
    {
        $sql = 'SELECT *';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(user_id)';
        }

        $sql .= ' FROM user WHERE user_id > 0';

        $where = array();

        if (isset($data['name'])) {
            $sql .= ' AND name LIKE ?';
            $where[] = "%{$data['name']}%";
        }

        if (isset($data['email'])) {
            $sql .= ' AND email LIKE ?';
            $where[] = "%{$data['email']}%";
        }

        if (isset($data['role_id'])) {
            $sql .= ' AND role_id = ?';
            $where[] = (int) $data['role_id'];
        }

        if (isset($data['store_id'])) {
            $sql .= ' AND store_id = ?';
            $where[] = (int) $data['store_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND status = ?';
            $where[] = (int) $data['status'];
        }

        if (isset($data['sort']) && (isset($data['order']) && in_array($data['order'], array('asc', 'desc'), true))) {
            $order = $data['order'];

            switch ($data['sort']) {
                case 'name':
                    $sql .= " ORDER BY name $order";
                    break;
                case 'email':
                    $sql .= " ORDER BY email $order";
                    break;
                case 'role_id':
                    $sql .= " ORDER BY role_id $order";
                    break;
                case 'store_id':
                    $sql .= " ORDER BY store_id $order";
                    break;
                case 'status':
                    $sql .= " ORDER BY status $order";
                    break;
                case 'created':
                    $sql .= " ORDER BY created $order";
                    break;
            }
        } else {
            $sql .= " ORDER BY created DESC";
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        $sth = $this->db->prepare($sql);
        $sth->execute($where);

        if (!empty($data['count'])) {
            return (int) $sth->fetchColumn();
        }

        $list = array();
        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $list[$user['user_id']] = $user;
        }

        $this->hook->fire('users', $list);
        return $list;
    }

    /**
     * Adds/updates an address for a given user
     * @param integer $user_id
     * @param array $address
     * @return bool
     */
    protected function setAddress($user_id, array $address)
    {
        if (empty($address['address_id'])) {
            $address['user_id'] = $user_id;
            return (bool) $this->address->add($address);
        }

        return (bool) $this->address->update($address['address_id'], $address);
    }

    /**
     * Logs a login event
     * @param array $user
     */
    protected function logLogin(array $user)
    {
        $data = array(
            'message' => 'User %s has logged in',
            'variables' => array('%s' => $user['email'])
        );

        $this->logger->log('login', $data);
    }

    /**
     * Logs a logout event
     * @param array $user
     */
    protected function logLogout(array $user)
    {
        $data = array(
            'message' => 'User %email has logged out',
            'variables' => array('%email' => $user['email'])
        );

        $this->logger->log('logout', $data);
    }

    /**
     * Logs a registration event
     * @param array $user
     */
    protected function logRegistration(array $user)
    {
        $data = array(
            'message' => 'User %email has been registered',
            'variables' => array('%email' => $user['email'])
        );

        $this->logger->log('register', $data);
    }

    /**
     * Retuns a redirect path for logged in user
     * @param array $user
     * @return string
     */
    protected function getLoginRedirect(array $user)
    {
        if ($this->isSuperadmin($user['user_id'])) {
            return $this->config->get('user_login_redirect_superadmin', 'admin');
        }

        return $this->config->get("user_login_redirect_{$user['role_id']}", "account/{$user['user_id']}");
    }

    /**
     * Returns a redirect path for logged out users
     * @param array $user
     * @return string
     */
    protected function getLogOutRedirect(array $user)
    {
        if ($this->isSuperadmin($user['user_id'])) {
            return $this->config->get('user_logout_redirect_superadmin', 'login');
        }

        return $this->config->get("user_logout_redirect_{$user['role_id']}", 'login');
    }

}
