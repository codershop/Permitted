<?php
App::import('Behavior', 'Tree');
class PermittedTreeBehavior extends TreeBehavior {

	public function move(&$Model, $nodeId, $parentId, $siblingId, $type)
    {
        $Model->id = $nodeId;

        // Move to parent Aro
        $success = true;
        if ($parentId != $Model->field('parent_id')) {
            if (!$Model->save(array('parent_id' => $parentId))) {
                $success = false;
            }
        }

        // Determine move direction and number of moves and move to correct order in parent
        $children = $Model->find('all', array(
            'fields' => array(
                'id'
            ),
            'conditions' => array(
                $Model->alias .'.parent_id' => $parentId
            ),
            'order' => $Model->alias .'.lft ASC'
        ));

        // find position of sibling node and child node
        if (!empty($siblingId) && !empty($children)) {
            $childPos = false;
            $siblingPos = false;
            for ($i = 0; $i < count($children) && ($childPos === false || $siblingPos === false); $i++) {
                $node =& $children[$i][$Model->alias];
                if (!$childPos && $nodeId == $node['id']) {
                    $childPos = $i;
                } else if (!$siblingPos && $siblingId == $node['id']) {
                    $siblingPos = $i;
                }
            }

            // Move the child node
            $moveNum = 0;
            if ($childPos !== false && $siblingPos !== false) {
                if ($siblingPos > $childPos) {
                    $moveDirection = 'down';
                    $moveNum = $siblingPos - $childPos;
                    $moveNum = ($type == 'before')? $moveNum -1: $moveNum;
                } else {
                    $moveDirection = 'up';
                    $moveNum = $childPos - $siblingPos;
                    $moveNum = ($type == 'before')? $moveNum: $moveNum -1;
                }
            }
        } else {
            $moveNum = (count($children) - 1);
            $moveDirection = 'up';
        }

        if ($moveNum > 0) {
            if ($moveDirection == 'up') {
                if (!$Model->moveUp($nodeId, $moveNum)) {
                    $success = false;
                }
            } else {
                if (!$Model->moveDown($nodeId, $moveNum)) {
                    $success = false;
                }
            }
        }

        return $success;
	}
}
?>