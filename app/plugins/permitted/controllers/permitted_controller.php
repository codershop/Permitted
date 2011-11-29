<?php
class PermittedController extends AppController
{
    public $name = 'Permitted';
    public $uses = array('Aco', 'Aro');
    public $helpers = array('Javascript');

    public function allow($aro_id, $aco_id)
    {
        $this->layout = 'ajax';
        $this->Aco->Behaviors->attach('Tree');
        $this->Aro->Behaviors->attach('Tree');
        $aco = implode('/', Set::extract('/Aco/alias', $this->Aco->getPath($aco_id)));
        $aro = implode('/', Set::extract('/Aro/alias', $this->Aro->getPath($aro_id)));

        $this->Permitted->allow($aro, $aco);

        // Remove permissions of sub nodes
        $this->removeSubPermissions($aro_id, $aco_id);

        $this->render('/elements/json_success');
    }

    private function buildNodes($className, &$items, &$children, $parent_id = null)
    {
        while ($item = array_shift($items)) {
            if ($item[$className]['parent_id'] != $parent_id) {
                array_unshift($items, $item);
                return;
            }
            $node = new Object();
            $node->title = $item[$className]['id'] . ': ' . $item[$className]['alias'];
            $node->id = $item[$className]['id'];
            $node->icon = false;
            if ($item[$className]['rght'] - $item[$className]['lft'] > 1) {
                $node->isFolder = true;
                $node->isLazy = true;
                $node->children = array();
                $this->buildNodes($className, $items, $node->children, $item[$className]['id']);
            }
            if (empty($node->children)) {
                unset($node->children);
            } else {
                unset($node->icon);
            }
            $children[] = $node;
        }
    }

    public function deny($aro_id, $aco_id)
    {
        $this->layout = 'ajax';
        $this->Aco->Behaviors->attach('Tree');
        $this->Aro->Behaviors->attach('Tree');
        $aco = implode('/', Set::extract('/Aco/alias', $this->Aco->getPath($aco_id)));
        $aro = implode('/', Set::extract('/Aro/alias', $this->Aro->getPath($aro_id)));

        $this->Permitted->deny($aro, $aco);

        // Remove permissions of sub nodes
        $this->removeSubPermissions($aro_id, $aco_id);

        $this->render('/elements/json_success');
    }

    public function index()
    {
        $aro = $this->Aro->find('first', array('conditions' => array('Aro.parent_id IS NULL')));
        $aros_nodes = array();
        if (!empty($aro)) {
            $aros = array();
            if ($aro['Aro']['rght'] - $aro['Aro']['lft'] > 1) {
                $aros = $this->Aro->find('all', array('conditions' => array('Aro.parent_id' => $aro['Aro']['id']), 'order' => 'Aro.lft ASC'));
            }
            array_unshift($aros, $aro);
            $this->buildNodes('Aro', $aros, $aros_nodes);
        }
        $this->set('aros', $aros_nodes);

        $acos = $this->Aco->find('all', array('order' => 'Aco.lft ASC'));
        $acos_nodes = array();
        $this->buildNodes('Aco', $acos, $acos_nodes);
        $this->set('acos', $acos_nodes);
    }

    public function load($type)
    {
        $this->layout = 'ajax';
        if (empty($this->params['form']['value'])) {
            $this->render('/elements/json_error');
            return true;
        }
        $parent_id = $this->params['form']['value'];
        $children = $this->{$type}->find('all', array('conditions' => array($type . '.parent_id' => $parent_id), 'order' => $type . '.lft ASC'));
        $nodes = array();
        $this->buildNodes($type, $children, $nodes, $parent_id);
        $this->set('parent_id', $parent_id);
        $this->set('type', $type);
        $this->set('nodes', $nodes);
    }

    public function move($class, $node, $otherNode, $mode)
    {
        $this->layout = 'ajax';

        $this->{$class}->Behaviors->attach('Permitted.PermittedTree');
        if ($mode != 'over') {
            $parent = $this->{$class}->find(
                'first',
                array(
                    'joins' => array(
                        array(
                            'table' => Inflector::tableize($class),
                            'alias' => 'Child',
                            'type' => 'INNER',
                            'conditions' => array(
                                'Child.parent_id = ' . $class . '.id'
                            )
                        )
                    ),
                    'conditions' => array(
                        'Child.id' => $otherNode
                    )
                )
            );
            $parent_id = $parent[$class]['id'];
        } else {
            $parent_id = $otherNode;
        }

        $this->{$class}->move($node, $parent_id, $otherNode, $mode);

        $this->render('/elements/json_success');
    }

    public function permitted($id)
    {
        $this->layout = 'ajax';
        $acos = $this->Aco->find(
            'all',
            array(
                'fields' => array(
                    'Aco.id'
                ),
                'joins' => array(
                    array(
                        'table' => 'aros_acos',
                        'alias' => 'ArosAco',
                        'type' => 'INNER',
                        'conditions' => array(
                            'ArosAco.aco_id = Aco.id'
                        )
                    ),
                    array(
                        'table' => 'aros',
                        'alias' => 'Aro',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'Aro.id = ArosAco.aro_id'
                        )
                    ),
                    array(
                        'table' => 'aros',
                        'alias' => 'Child',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'Child.lft >= Aro.lft',
                            'Child.rght <= Aro.rght',
                        )
                    )
                ),
                'conditions' => array(
                    'Child.id' => $id
                ),
                'group' => 'Aco.id'
            )
        );

        $allowed = array();
        $denied = array();
        $aro_path = implode('/', Set::extract('/Aro/alias', $this->Aro->getPath($id)));
        $this->Permitted->flushCache();

        foreach ($acos as $aco) {
            $aco_path = implode('/', Set::extract('/Aco/alias', $this->Aco->getPath($aco['Aco']['id'])));

            if ($this->Permitted->check($aro_path, $aco_path)) {
                $allowed[] = $aco['Aco']['id'];
            } else {
                $denied[] = $aco['Aco']['id'];
            }
        }

        $this->set('allowed', $allowed);
        $this->set('denied', $denied);
    }

    private function removeSubPermissions($aro_id, $aco_id)
    {
        $acos = $this->Aco->find(
            'all',
            array(
                'fields' => array('Aco.id'),
                'joins' => array(
                    array(
                        'table' => 'acos',
                        'alias' => 'AcoParent',
                        'type' => 'INNER',
                        'conditions' => array(
                            'AcoParent.lft < Aco.lft',
                            'AcoParent.rght > Aco.rght'
                        )
                    )
                ),
                'conditions' => array('AcoParent.id' => $aco_id)
            )
        );
        $acos = Set::extract('/Aco/id', $acos);

        $this->Aro->Permission->deleteAll(array(
            'Permission.aro_id' => $aro_id,
            'Permission.aco_id' => $acos
        ), false, false);
    }
}
?>
