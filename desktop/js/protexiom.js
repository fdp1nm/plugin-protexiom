
/* Copyright   2014 fdp1
 * 
 * This work is free. You can redistribute it and/or modify it under the
 * terms of the Do What The Fuck You Want To Public License, Version 2,
 * as published by Sam Hocevar. See the COPYING file for more details.
 * 
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://www.wtfpl.net/ for more details.
 */

function temPaddCmdToTable() {
}

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" ></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" >';
    tr += '</td>'; 
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="type" ></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" value="other" style="display : none;">';
    tr += '</td>'; 
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="logicalId" ></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="somfyCmd" style="display : none;">';
    tr += '</td>'; 
    tr += '<td>';
    tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> {{Visible}}<br/></span>';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    /* The command list is static. Lets not offer the possibility to remove them
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';*/
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
}

