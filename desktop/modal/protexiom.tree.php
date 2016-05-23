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
<h3>Liste des elements</h3>
<pre>
<?php 
print_r($eqLogic->getElementsFromCache());
?>
</pre>
</div>