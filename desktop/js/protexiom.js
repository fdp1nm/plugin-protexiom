
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
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    if (init(_cmd.type) == 'info') {
        var disabled = (init(_cmd.configuration.virtualAction) == '1') ? 'disabled' : '';
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '" virtualAction="' + init(_cmd.configuration.virtualAction) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"></td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />';
        tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '</td>';
        tr += '<td><textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height : 33px;" ' + disabled + ' placeholder="{{Calcul}}"></textarea>';
        tr += '<a class="btn btn-default cursor listEquipementInfo btn-sm" data-input="calcul"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateValue" placeholder="{{Valeur retour d\'état}}" style="margin : 5px;width : 30%;display : inline-block;">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime" placeholder="{{Durée avant retour d\'état (min)}}" style="margin : 5px;width : 30%;display : inline-block;">';
        tr += '</td>';
        tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="unite" style="width : 90px;" placeholder="{{Unité}}"></td>';
        tr += '<td>';
        tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isHistorized" data-label-text="{{Historiser}}" /></span> ';
        tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isVisible" data-label-text="{{Afficher}}" checked/></span> ';
        tr += '<span class="expertModeVisible"><input type="checkbox" data-size="mini" class="cmdAttr bootstrapSwitch" data-l1key="display" data-label-text="{{Inverser}}" data-l2key="invertBinary" /></span> ';
        tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="eventOnly"' + disabled + ' data-label-text="{{Evènement}}" /></span> ';
        tr += '<input style="width : 81%;margin-bottom : 2px;" class="tooltips cmdAttr form-control input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="{{Lifetime cache}}" title="{{Lifetime cache}}">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';
        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        if (isset(_cmd.type)) {
            $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
        }
        jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
        initCheckBox();
    }

    if (init(_cmd.type) == 'action') {
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<div class="row">';
        tr += '<div class="col-sm-6">';
        tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>';
        tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
        tr += '</div>';
        tr += '<div class="col-sm-6">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
        tr += '</div>';
        tr += '</div>';
        tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;margin-right : 10px;" title="{{La valeur de la commande vaut par défaut la commande}}">';
        tr += '<option value="">Aucune</option>';
        tr += '</select>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
        tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '<input class="cmdAttr" data-l1key="configuration" data-l2key="virtualAction" value="1" style="display:none;" >';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="infoName" placeholder="{{Nom information}}" style="margin-bottom : 5px;width : 70%; display : inline-block;">';
        tr += '<a class="btn btn-default btn-sm cursor listEquipementAction" data-input="infoName" style="margin-left : 5px;"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="value" placeholder="{{Valeur}}" style="margin-bottom : 5px;width : 50%; display : inline-block;">';
        tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo" data-input="value" style="margin-left : 5px;"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
        tr += '</td>';
        tr += '<td></td>';
        tr += '<td>';
        tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-l1key="isVisible" data-size="mini" data-label-text="{{Afficher}}" checked/></span> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';

        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd tbody tr:last');
        jeedom.eqLogic.builSelectCmd({
            id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
            filter: {type: 'info'},
            error: function (error) {
                $('#div_alert').showAlert({message: error.message, level: 'danger'});
            },
            success: function (result) {
                tr.find('.cmdAttr[data-l1key=value]').append(result);
                tr.setValues(_cmd, '.cmdAttr');
                jeedom.cmd.changeType(tr, init(_cmd.subType));
                initCheckBox();
            }
        });
    }	
}

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    if (init(_cmd.type) == 'info') {
	    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="id" ></span>';
	    tr += '</td>';
	    tr += '<td>';
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"><br>';
	    tr += '</td>';
	    tr += '<td>'
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="mobileLabel" style="width : 140px;" placeholder="{{Légende widget mobile}}">';
	    tr += '</td>'; 
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="type" ></span><br>';
	    tr += '<span class="cmdAttr" data-l1key="subType" value="other"></span>';
	    tr += '</td>'; 
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="logicalId" ></span>';
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="somfyCmd" style="display : none;">';
	    tr += '</td>'; 
	    tr += '<td>';
	    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isHistorized" data-label-text="{{Historiser}}" /></span> <br>';
	    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isVisible" data-label-text="{{Afficher}}" checked/></span> ';
	    tr += '</td>';
	    tr += '<td>';
	    if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a>';
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
	    }
	    /* The command list is static. Lets not offer the possibility to remove them
	    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';*/
	    tr += '</td>';
	    tr += '</tr>';
	    $('#table_cmd tbody').append(tr);
	    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    }

    if (init(_cmd.type) == 'action') {
	    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="id" ></span>';
	    tr += '</td>';
	    tr += '<td>';
	    tr += '<div class="row">';
	    tr += '<div class="col-sm-4">';
	    tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>';
	    tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
	    tr += '</div>';
	    tr += '<div class="col-sm-8">';
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}">';
	    tr += '</div>';
	    tr += '</div>';
	    tr += '</td>';
	    tr += '<td>'
	    tr +='<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="mobileLabel" style="width : 140px;" placeholder="{{Légende widget mobile}}">';
	    tr += '</td>';
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="type" ></span><br>';
	    tr += '<span class="cmdAttr" data-l1key="subType" value="other"></span>';
	    tr += '</td>'; 
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="logicalId" ></span>';
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="somfyCmd" style="display : none;">';
	    tr += '</td>'; 
	    tr += '<td>';
	    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isHistorized" data-label-text="{{Historiser}}" /></span> <br>';
	    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-l1key="isVisible" data-label-text="{{Afficher}}" checked/></span> ';
	    tr += '</td>';
	    tr += '<td>';
	    if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a>';
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
	    }
	    /* The command list is static. Lets not offer the possibility to remove them
	    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';*/
	    tr += '</td>';
	    tr += '</tr>';
	    $('#table_cmd tbody').append(tr);
	    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    }
    
}

