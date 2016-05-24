
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


$("#table_cmd_protexiom_ctrl").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

//Hide or show subDevice liste
$('.eqLogicAction[data-action=hide]').on('click', function () {
    var eqLogic_id = $(this).attr('data-eqLogic_id');
    $('.sub-nav-list').each(function () {
		if ( $(this).attr('data-eqLogic_id') == eqLogic_id ) {
			$(this).toggle();
		}
    });
    return false;
});

/*
 * Show modals
 */
//Show protexiom tree modal
$('#bt_displayElmtTree').on('click', function () {
	$('#md_modal').dialog({title: "{{Arbre des composants protexiom}}"});
	$('#md_modal').load('index.php?v=d&plugin=protexiom&modal=protexiom.tree&id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
});
//Show protexiom tree modal
$('#bt_displayElmtStatus').on('click', function () {
	$('#md_modal').dialog({title: "{{Status de l'équipement}}"});
	$('#md_modal').load('index.php?v=d&plugin=protexiom&modal=protexiomElmt.status&id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
});

function printEqLogic(_eqLogic){
	if($('.li_eqLogic.active').attr('data-eqlogic_id') != ''){
		$('.img_device').attr("src", $('.eqLogicDisplayCard[data-eqLogic_id='+$('.li_eqLogic.active').attr('data-eqlogic_id')+'] img').attr('src'));
	}else{
		$('.img_device').attr("src",'plugins/protexiom/desktop/images/typedefault.png');
	}
}

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    if (init(_cmd.eqType) == 'protexiom') {
    	addCmdToTableProtexiom(_cmd);
    }else{
    	addCmdToTableSubDevice(_cmd)
    }
    
}

function addCmdToTableProtexiom(_cmd) {
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
    }
    
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    
}

function addCmdToTableSubDevice(_cmd) {
    if (init(_cmd.type) == 'info') {
	    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	    tr += '<td>';
	    tr += '<span class="cmdAttr" data-l1key="id" ></span>';
	    tr += '</td>';
	    tr += '<td>';
	    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"><br>';
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
    }
    
	table_cmd = '#table_cmd';
	if ( $(table_cmd+'_'+_cmd.eqType ).length ) {
		table_cmd+= '_'+_cmd.eqType;
	}
    $(table_cmd+' tbody').append(tr);
    $(table_cmd+' tbody tr:last').setValues(_cmd, '.cmdAttr');
    
}

