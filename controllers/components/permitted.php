<?php
/**
 * This class is adapted from
 * http://bakery.cakephp.org/articles/macduy/2010/01/05/acl-caching-using-session
 */


/**
 * ACL Caching.
 *
 * Yet another take at Caching ACL queries, now using Session.
 * Adapted from http://www.nabble.com/ACL-Auth-Speed-Issues-td21386047.html
 * and bits and pieces taken from cached_acl.php
 *
 * It also extends ACL with some nifty functions for easier and simpler code.
 *
 * Cake's ACL doesn't cache anything. For better performance, we
 * put results of check into session. Only ::check() is wrapped,
 * other functions are simply piped to the parent Acl object,
 * though it can be handy to wrap these too in future.
 *
 * @author macduy
 */
App::import('Component', 'Acl');
App::import('Component', 'Session');
App::import('Model', 'Aco');
App::import('Model', 'Aro');
class PermittedComponent extends AclComponent
{
    private static $user;

    private $settings = array(
        'active'       => true,
        'acoRoot'      => 'ROOT',
        'aroRoot'      => 'ROOT',
        'loginAction'  => array('plugin' => null, 'admin' => false, 'controller' => 'users', 'action' => 'login'),
        'logoutAction' => array('plugin' => null, 'admin' => false, 'controller' => 'users', 'action' => 'logout')
    );

    public function action()
    {
        $plugin_path = '';
        if (!empty($this->controller->params['plugin'])) {
            $plugin_path = Inflector::camelize($this->controller->params['plugin']) . '/';
        }

        $action = $this->settings['acoRoot'] . '/' . $plugin_path . Inflector::pluralize($this->controller->name) .'/'. $this->controller->action;
        if (!$this->Aco->node($action)) {
            $action = $this->settings['acoRoot'] . '/' . $plugin_path . $this->controller->name .'/'. $this->controller->action;
        }

        // Does the method exist?
        $classMethods = array_map('strtolower', get_class_methods($this->controller));
        if (!in_array(strtolower($this->controller->params['action']), $classMethods)) {
			return false;
		}

        // Is the method protected?
        $protectedMethods = array_map('strtolower', get_class_methods('controller'));
		if (in_array(strtolower($this->controller->params['action']), $protectedMethods) || strpos($this->controller->params['action'], '_', 0) === 0) {
			return false;
		}

        // Is it in the session cache?
        $path = $this->cachePath(false, $action, "*", true);
        if (!$this->Session->check($path)) {

            // Check the aco table
            $parts = explode('/', $action);
            $parent = null;
            foreach ($parts as $part) {
                $aco = $this->Aco->find(
                    'first',
                    array(
                        'fields' => array('Aco.id'),
                        'conditions' => array('Aco.parent_id' => $parent, 'Aco.alias' => $part)
                    )
                );

                if (!empty($aco)) {
                    $parent = $aco['Aco']['id'];
                } else {
                    $this->Aco->create();
                    $result = $this->Aco->save(array('parent_id' => $parent, 'alias' => $part));
                    if (!empty($result)) {
                        $parent = $this->Aco->id;
                    } else {
                        return false;
                    }
                }
            }
        }

        return $action;
    }

    /**
     * Checks that ALL of given pairs of aco-action are satisfied
     */
    public function all($aro, $pairs)
    {
        foreach ($pairs as $aco => $action)
        {
            if (!$this->check($aro,$aco,$action))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Allow
     */
    public function allow($aro, $aco, $action = "*")
    {
        parent::allow($aro, $aco, $action);
        $this->delete($aro, $aco, $action);
    }

    /**
     * Returns a unique, dot separated path to use as the cache key. Copied from CachedAcl.
     *
     * @param string $aro ARO
     * @param string $aco ACO
     * @param boolean $acoPath Boolean to return only the path to the ACO or the full path to the permission.
     * @access private
     */
    private function cachePath($aro, $aco, $action, $acoPath = false)
    {
        if ($action != "*")
        {
            $aco .= '/' . $action;
        }
        $path = Inflector::slug($aco);

        if (!$acoPath)
        {
            if (!is_array($aro))
            {
                $_aro = explode(':', $aro);
            } elseif (Set::countDim($aro) > 1)
            {
                $_aro = array(key($aro), current(current($aro)));
            } else
            {
                $_aro = array_values($aro);
            }
            $path .= '.' . Inflector::slug(implode('.', $_aro));
        }

        return "Acl.".$path;
    }

    /**
     * Returns an array of booleans for each $aco-$aro pair
     */
    public function can($aro, $pairs)
    {
        $can = array();
        $i = 0;
        foreach ($pairs as $aco => $action) {
            $can[$i] = $this->check($aro,$aco,$action);
            $i++;
        }
        return $can;
    }

    /**
     * Check to see if the aro has access to the aco.
     *
     * @param mixed $aro The user to check for permission
     * @param mixed $aco The permission to check for
     * @param string $action
     * @return boolean True if the aro has aco permission
     */
    public function check($aro, $aco, $action = "*")
    {
        $path = $this->cachePath($aro, $aco, $action);
        if ($this->Session->check($path))
        {
            return $this->Session->read($path);
        } else
        {
            $check = parent::check($aro, $aco, $action);
            $this->Session->write($path, $check);
            return $check;
        }
    }

    /**
     * Deletes the cache reference in Session, if found
     */
    private function delete($aro, $aco, $action)
    {
        $key = $this->cachePath($aro, $aco, $action, true);
        if ($this->Session->check($key))
        {
            $this->Session->delete($key);
        }
    }

    /**
     * Deny method.
     */
    public function deny($aro, $aco, $action = "*")
    {
        parent::deny($aro, $aco, $action);
        $this->delete($aro, $aco, $action);
    }

    /**
     * Deletes the whole cache from the Session variable
     */
    public function flushCache()
    {
        $this->Session->delete('Acl');
    }

    /**
     * Grant method.
     *
     * This method overrides and uses the original
     * method. It only adds cache to it.
     *
     * @param string $aro ARO
     * @param string $aco ACO
     * @param string $action Action (defaults to *)
     * @access public
     */
    public function grant($aro, $aco, $action = "*")
    {
        parent::grant($aro, $aco, $action);
        $this->delete($aro, $aco, $action);
    }

    /**
     * Inherit method.
     *
     * This method overrides and uses the original
     * method. It only adds cache to it.
     *
     * @param string $aro ARO
     * @param string $aco ACO
     * @param string $action Action (defaults to *)
     * @access public
     */
    public function inherit($aro, $aco, $action = "*")
    {
        parent::inherit($aro, $aco, $action);
        $this->delete($aro, $aco, $action);
    }

    public function initialize(&$controller, $settings = array())
    {
        $this->settings = am($this->settings, $settings);
        $this->controller = &$controller;
        Permitted::instance($this);
        $this->master =& $controller;
        $controller->Acl =& $this;
        $this->Session = new SessionComponent();
        $this->Aco = new Aco();
        $this->Aro = new Aro();

        $action = $this->action();
        if (empty($action)) {
            return false;
        }

        if ($this->settings['active']) {
            $user = $this->user();
            if (!$this->check($user, $action)) {
                $controller->redirect($this->settings['loginAction']);
            }
        }
    }


    /**
     * Checks that AT LEAST ONE of given pairs of aco-action is satisfied
     */
    public function one($aro, $pairs)
    {
        foreach ($pairs as $aco => $action)
        {
            if ($this->check($aro,$aco,$action))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Revoke method.
     *
     * This method overrides and uses the original
     * method. It only adds cache to it.
     *
     * @param string $aro ARO
     * @param string $aco ACO
     * @param string $action Action (defaults to *)
     * @access public
     */
    public function revoke($aro, $aco, $action = "*")
    {
        parent::revoke($aro, $aco, $action);
        $this->delete($aro, $aco, $action);
    }

    public function user($user = null)
    {
        if (!empty($user)) {
            self::$user = $user;
        } else if (empty(self::$user)) {
            $user = null;
            if (class_exists('Authsome')) {
                $user = Authsome::get('User.id');
            }
            if (!empty($user)) {
                self::$user = $user;
            } else {
                return $this->settings['aroRoot'];
            }
        }

        return self::$user;
    }
}

/**
 * Static acl class
 * this concept was inspired by Debuggable's Authsome
 * https://github.com/felixge/cakephp-authsome
 *
 */
class Permitted
{
    static public function allow($aro, $aco = null, $action = "*")
    {
        return self::instance()->allow($aro, $aco, $action);
    }

    static public function check($aro, $aco = null, $action = "*")
    {
        if ($aco == null) {
            $aco = $aro;

            $user = self::instance()->user();
            if (!empty($user)) {
                $aro = array('model' => 'User', 'foreign_key' => self::instance()->user());
            } else {
                $aro = 'ROOT';
            }
        }

        return self::instance()->check($aro, $aco, $action);
    }

    static public function deny($aro, $aco = null, $action = "*")
    {
        return self::instance()->deny($aro, $aco, $action);
    }

    static public function grant($aro, $aco = null, $action = "*")
    {
        return self::instance()->grant($aro, $aco, $action);
    }

    static public function inherit($aro, $aco = null, $action = "*")
    {
        return self::instance()->inherit($aro, $aco, $action);
    }

    static public function instance($setInstance = null)
    {
        static $instance;

        if ($setInstance) {
            $instance = $setInstance;
        }

        if (!$instance) {
            throw new Exception('SessionAclComponent not initialized properly!');
        }

        return $instance;
    }

    static public function revoke($aro, $aco = null, $action = "*")
    {
        return self::instance()->revoke($aro, $aco, $action);
    }

    static public function user($user_id)
    {
        self::instance()->user($user_id);
    }
}
?>