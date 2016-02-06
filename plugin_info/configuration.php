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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
 }
 ?>
 <form class="form-horizontal">
     <fieldset>
         <div class="form-group">
             <label class="col-sm-5 control-label">{{Interval de mise à jour}}</label>
             <div class="col-sm-5">
                 <input class="configKey form-control" data-l1key="pollInt" />
                 {{Interval (en secondes) de mise à jour de l'etat.}}<br/>
                 {{Valeur minimum: 5 secondes.}}<br/>
                 {{Valeur recommandée: 10 secondes}}<br/>
                 {{Exemple}}: 10
             </div>
         </div>
         
         <script>
            function protexiom_postSaveConfiguration(){
                $.ajax({// fonction permettant de faire de l'ajax
                    type: "POST", // methode de transmission des données au fichier php
                    url: "plugins/protexiom/core/ajax/protexiom.ajax.php", // url du fichier php
                    data: {
                        action: "postSave",
                        //Let's send previous values as parameters, to be able to set them back in case of bad values
                        pollInt: "<?php echo config::byKey('pollInt', 'protexiom'); ?>",
                    },
                    dataType: 'json',
                    error: function (request, status, error) {
                        handleAjaxError(request, status, error);
                    },
                    success: function (data) { // si l'appel a bien fonctionné
                        if (data.state != 'ok') {
                            $('#div_alert').showAlert({message: data.result, level: 'danger'});
                            return;
                        }
                        $('#ul_plugin .li_plugin[data-plugin_id=protexiom]').click();
                    }
                });

            }
        </script>
        
     </fieldset>
 </form>
