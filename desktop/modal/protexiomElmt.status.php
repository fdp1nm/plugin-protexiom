<?php
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
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
if (init('id') == '') {
	throw new Exception('{{EqLogic ID ne peut être vide}}');
}
$eqLogic = eqLogic::byId(init('id'));
if (!is_object($eqLogic)) {
	throw new Exception('{{EqLogic non trouvé}}');
}
?>
<div id="div_protexiomTree">
<h3>Status de l'élément:</h3>
<?php 
echo("<b>LogicalID: </b>".$eqLogic->getLogicalId()."<br>");
echo("<b>ID: </b>".substr($eqLogic->getLogicalId(), strpos($eqLogic->getLogicalId(),"-")+1)."<br>");
echo("<b>EqType: </b>".$eqLogic->getEqType_name()."<br>");
echo("<b>Type: </b>".$eqLogic->getConfiguration('item_type')."<br>");
echo("<b>Label: </b>".$eqLogic->getConfiguration('item_label')."<br>");
echo("<b>Zone: </b>".$eqLogic->getConfiguration('item_zone')."<br>");
?>

</div>