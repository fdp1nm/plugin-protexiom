<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'protexiom');
?>

<div class="row row-overflow">
    <div class="col-md-2">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter une Protexiom}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
                foreach (eqLogic::byType('protexiom') as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName() . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>
    <div class="col-md-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <form class="form-horizontal">
            <fieldset>
                <legend>{{Général}}</legend>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{Nom de la centrale Somfy Protexiom}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de la centrale Somfy Protexiom}}"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label" >{{Objet parent}}</label>
                    <div class="col-md-3">
                        <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                            <option value="">{{Aucun}}</option>
                            <?php
                            foreach (object::all() as $object) {
                                echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label" >{{Activer}}</label>
                    <div class="col-md-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" size="16" checked/>
                    </div>
                    <label class="col-md-2 control-label" >{{Visible}}</label>
                    <div class="col-md-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{Adresse IP ou Hostname:port}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="SomfyHostPort" placeholder="{{Adresse IP ou Hostname:port}}"/>
									{{Exemple}}: alarme.mondomaine.com:80 {{ou}} 192.168.1.253:80 {{ou}} 192.1681.253
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label" >{{SSL Enabled}}</label>
                    <div class="col-md-3">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="SSLEnabled" placeholder="{{SSL Enabled}}" size="16" checked/>
									{{SSL PAS ENCORE SUPPORTE. Ne pas activer.}}
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">{{User Password}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="UserPwd" placeholder="{{User Password}}"/>
									{{Exemple}}: s3cr3tPassw0rd
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{AuthCard Line 1}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="AuthCardL1" placeholder="{{AuthCard Line 1}}"/>
									{{Exemple}}: 1234|5678|9012|3456|7890|1234
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{AuthCard Line 2}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="AuthCardL2" placeholder="{{AuthCard Line 2}}"/>
									{{Exemple}}: 1234|5678|9012|3456|7890|1234
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{AuthCard Line 3}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="AuthCardL3" placeholder="{{AuthCard Line 3}}"/>
									{{Exemple}}: 1234|5678|9012|3456|7890|1234
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{AuthCard Line 4}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="AuthCardL4" placeholder="{{AuthCard Line 4}}"/>
									{{Exemple}}: 1234|5678|9012|3456|7890|1234
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{AuthCard Line 5}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="AuthCardL5" placeholder="{{AuthCard Line 5}}"/>
									{{Exemple}}: 1234|5678|9012|3456|7890|1234
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{Interval de mise à jour}}</label>
                    <div class="col-md-3">
                        <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="PollInt" placeholder="{{Interval de polling}}"/>
									{{Interval (en secondes) de mise à jour de l'etat.}}<br/>
									{{Si vide ou 0, polling désactivé.<br/>
									{{Valeur minimum: 5 secondes.}}<br/>
									{{Valeur recommandée: 10 secondes}}<br/>
									{{Exemple}}: 10
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">{{Version hardware}}<br></label>
                    <div class="col-md-3">
                    	<input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="HwVersion" placeholder="{{Non détéctée}}" disabled/>
                        	{{Autodétéctée}}
                    </div>
                </div>
           </fieldset> 
        </form>

<?php
/* Command list
 * will be populated by the addCmdToTable() js function in desktop/protexiom.js
*/
?>
        <legend>{{Commandes}}</legend>
        <?php /* The command list is static. Lets not offer the possibility to remove them
        <a class="btn btn-success btn-sm cmdAction" data-action="add"><i class="fa fa-plus-circle"></i> {{Commandes}}</a><br/><br/>
        */ ?>
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 50px;">{{ID}}</th>
                    <th style="width: 200px;">{{Nom}}</th>
                    <th style="width: 180px;">{{Légende widget mobile}}</th>
                    <th style="width: 65px;">{{Type}}</th>
                    <th style="width: 65px;">{{Commande}}</th>
                    <th style="width: 65px;">{{Paramètres}}</th>
                    <th style="width: 65px;"></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php include_file('desktop', 'protexiom', 'js', 'protexiom'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>