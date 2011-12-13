<?php
    echo $this->Html->css('/permitted/js/dynatree/skin/ui.dynatree');
    echo $this->Html->css('/permitted/css/permitted');
    echo $this->Html->css('/permitted/css/jquery.contextMenu');
?>
<div class="permitted_wrapper">
    <a id="permitted_instruction" href="javascript: void(0);">Hide Instructions</a>
    <h1>Permission Management</h1>
    <div id="permitted_instructions">
        Two easy steps
        <ol>
            <li>Pick a group or user on the left</li>
            <li>Set the permissions on the right</li>
        </ol>

        <br />
        <h4>Guest Permissions</h4>
        To grant permissions to the guest user (any unauthenticated user), select the ROOT node on the right, then select the permissions to grant on the left.
    </div>
</div>

<table class="permitted_wrapper">
    <tbody>
        <tr>
            <td>
                <h1>Users/Groups</h1>
                <div id="permitted_aro"></div>
            </td>
            <td>
                <h1>Permissions</h1>
                <div id="permitted_aco"></div>
            </td>
        </tr>
    </tbody>
</table>
<?php
    echo $this->Html->script('/permitted/js/jquery/jquery');
    echo $this->Html->script('/permitted/js/jquery/jquery-ui.custom');
    echo $this->Html->script('/permitted/js/jquery/jquery.cookie');
    echo $this->Html->script('/permitted/js/jquery.contextMenu');
    echo $this->Html->script('/permitted/js/dynatree/jquery.dynatree');
    echo $this->Html->script('/permitted/js/permitted');

?>
<ul id="permittedMenu" class="contextMenu">
    <li class="add">
        <a href="#add">Add</a>
        <a href="#edit">Edit</a>
        <a href="#delete">Delete</a>
    </li>
</ul>
<script type="text/javascript">
var aros = <?php echo $this->Javascript->object($aros); ?>;
var acos = <?php echo $this->Javascript->object($acos); ?>;

function draw() {
    // Clear checkboxes
    jQuery("#permitted_aco").dynatree("getRoot").visit(function(node) {
        node.select(false);
    }, true);

    // Draw allowed
    jQuery("#permitted_aco").dynatree("getRoot").visit(function (node) {
        if (jQuery.inArray(node.data.id, window.permissions.allowed) >= 0) {
            node.select(true);
        }
    }, true);

    // Draw denied
    jQuery("#permitted_aco").dynatree("getRoot").visit(function (node) {
        if (jQuery.inArray(node.data.id, window.permissions.denied) >= 0) {
            node.select(false);
        }
    }, true);
}

function loadPermissions(id) {
    var date = new Date();
    jQuery.ajax({
        "url": "/permitted/permitted/permitted/" + id + '?' + date.getTime(),
        "dataType": 'json',
        "success": function (data) {
            window.permissions = data;
            draw();
        }
    });
}

jQuery(document).ready(function () {

    // Aro tree
    jQuery("#permitted_aro").dynatree({
        persist: false,
        autoCollapse: false,
        children: aros,
        minExpandLevel: 2,
        onLazyRead: function(node) {
            node.appendAjax({
                url: "/permitted/permitted/load/Aro",
                type: 'POST',
                data: {
                    value: node.data.id
                }
            });
        },
        onActivate: function(node) {
            window.aro_id = node.data.id;
            loadPermissions(window.aro_id);

        },
        dnd: {
            onDragStart: function(node) {
                return true;
            },
            autoExpandMS: 1000,
            preventVoidMoves: true, // Prevent dropping nodes 'before self', etc.
            onDragEnter: function(node, sourceNode) {
                return true;
            },
            onDrop: function(node, sourceNode, hitMode, ui, draggable) {
                var date = new Date();
                sourceNode.move(node, hitMode);
                jQuery.ajax({
                    url: '/permitted/permitted/move/Aro/' + sourceNode.data.id + '/' + node.data.id + '/' + hitMode + '?' + date.getTime(),
                    type: 'GET'
                });
            }
        }
    });

    // Aco tree
    jQuery("#permitted_aco").dynatree({
        persist: false,
        minExpandLevel: 2,
        children: acos,
        selectMode: 3,
        checkbox: true,
        onSelect: function (checked, node) {
            var isUserEvent = node.tree.isUserEvent(); // Event was triggered by mouse or keyboard
            if (isUserEvent) {
                if (window.aro_id) {
                    var date = new Date();
                    if (checked) {
                        jQuery.ajax({
                            url: "/permitted/permitted/allow/" + window.aro_id + "/" + node.data.id + '?' + date.getTime()
                        });
                    } else {
                        jQuery.ajax({
                            url: "/permitted/permitted/deny/" + window.aro_id + "/" + node.data.id + '?' + date.getTime()
                        });
                    }
                } else {
                    console.log('First select a user or group');
                }
            }
        }
    });

    jQuery('.dynatree-node').contextMenu({
        menu: 'permittedMenu'
    },
        function(action, el, pos) {
//        alert(
//            ''Action: '' + action + ''\n\n'' +
//            ''Element ID: '' + $(el).attr(''id'') + ''\n\n'' +
//            ''X: '' + pos.x + ''  Y: '' + pos.y + '' (relative to element)\n\n'' +
//            ''X: '' + pos.docX + ''  Y: '' + pos.docY+ '' (relative to document)''
//            );
    });
});

</script>