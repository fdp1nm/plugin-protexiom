<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'protexiom');
$eqLogics = eqLogic::byType('protexiom')
?>

<div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter une Protexiom}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>   
              <?php
                foreach ($eqLogics as $eqLogic) {
                       echo '<li>';
						echo '<i class="fa jeedom-alerte cursor eqLogicAction" data-action="hide" data-eqLogic_id="' . $eqLogic->getId() . '"></i>';
						echo '<a class="cursor li_eqLogic" style="display: inline;" data-eqLogic_id="' . $eqLogic->getId() . '" data-eqLogic_type="protexiom">' . $eqLogic->getName() . '</a>';
						echo '<ul id="ul_eqLogic" class="nav nav-list bs-sidenav sub-nav-list" data-eqLogic_id="' . $eqLogic->getId() . '" style="display: none;">';
							echo '<li>';
								echo '<i class="fa jeedom2-bulb19 cursor eqLogicAction" data-action="hide" data-eqLogic_id="ctrl_' . $eqLogic->getId() . '"></i>';
								echo '<a class="cursor eqLogicAction" data-action="hide" style="display: inline;" data-eqLogic_id="ctrl_' . $eqLogic->getId() . '" data-eqLogic_type="protexiom">{{Centralisations}}</a>';
								echo '<ul id="ul_eqLogic" class="nav nav-list bs-sidenav sub-nav-list" data-eqLogic_id="ctrl_' . $eqLogic->getId() . '" style="display: none;">';
									foreach (eqLogic::byType('protexiom_ctrl') as $SubeqLogic) {
										if ( substr ($SubeqLogic->getLogicalId(), 0, strpos($SubeqLogic->getLogicalId(),"_")) == $eqLogic->getId() ) {
											echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $SubeqLogic->getId() . '" data-eqLogic_type="protexiom_ctrl"><a>' . $SubeqLogic->getName() . '</a></li>';
										}
									}
								echo '</ul>';
							echo '</li>';
							/*echo '<li>';
								echo '<i class="fa jeedom-mouvement cursor eqLogicAction" data-action="hide" data-eqLogic_id="sensor_' . $eqLogic->getId() . '"></i>';
								echo '<a class="cursor eqLogicAction" data-action="hide" style="display: inline;" data-eqLogic_id="sensor_' . $eqLogic->getId() . '" data-eqLogic_type="protexiom">{{Détécteur}}</a>';
								echo '<ul id="ul_eqLogic" class="nav nav-list bs-sidenav sub-nav-list" data-eqLogic_id="sensor_' . $eqLogic->getId() . '" style="display: none;">';
									foreach (eqLogic::byType('protexiom_sensor') as $SubeqLogic) {
										if ( substr ($SubeqLogic->getLogicalId(), 0, strpos($SubeqLogic->getLogicalId(),"_")) == $eqLogic->getId() ) {
											echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $SubeqLogic->getId() . '" data-eqLogic_type="protexiom_sensor"><a>' . $SubeqLogic->getName() . '</a></li>';
										}
									}
								echo '</ul>';
							echo '</li>';*/
						echo '</ul>';
					echo '</li>';
				}
                ?>
            </ul>
        </div>
    </div>
    
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
        <legend>{{Mes alarmes somfy}}
        </legend>
        <?php
        if (count($eqLogics) == 0) {
            echo "<br/><br/><br/><center><span style='color:#767676;font-size:1.2em;font-weight: bold;'>{{Vous n'avez pas encore d'alarme Somfy, cliquez sur Ajouter une protexiom pour commencer}}</span></center>";
        } else {
            ?>
            <div class="eqLogicThumbnailContainer">
            	<div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
             		<center>
                		<i class="fa fa-plus-circle" style="font-size : 7em;color:#ffb401;"></i>
            		</center>
            		<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#ffb401"><center>Ajouter</center></span>
        		</div>
                <?php
                foreach ($eqLogics as $eqLogic) {
                    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
                    echo "<center>";
                    echo '<img src="plugins/protexiom/doc/images/protexiom_icon.png" height="105" width="92" />';
                    echo "</center>";
                    echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
                    echo '</div>';
                }
                ?>
            </div>
        <?php } ?>
    </div>
    
    <div class="col-lg-10 col-md-9 col-sm-8 protexiom eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <form class="form-horizontal">
            <fieldset>
                <legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}<i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i></legend>
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
                    <label class="col-lg-2 control-label">{{Catégorie}}</label>
                    <div class="col-lg-8">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">';
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>';
                        }
                        ?>

                    </div>
                </div>
                <div class="form-group">
			<label class="col-md-2 control-label">{{Activer}}</label>
			<div class="col-md-3">
				<input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
				<input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="Visible" data-l1key="isVisible" checked/>
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
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Commentaire}}</label>
                    <div class="col-sm-3">
                    	<textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire"></textarea>
                    </div>
                </div>
           </fieldset> 
        </form>
	
        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
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
                    <th style="width: 230px;">{{Nom}}</th>
                    <th>{{Légende widget mobile}}</th>
                    <th style="width: 110px;">{{Type}}</th>
                    <th style="width: 100px;">{{Commande}}</th>
                    <th style="width: 200px;">{{Paramètres}}</th>
                    <th style="width: 100px;"></th>
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
    <?php include_file('desktop', 'protexiom_ctrl', 'php', 'protexiom'); ?>
</div>

<?php include_file('desktop', 'protexiom', 'js', 'protexiom'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>

<script type="text/javascript">
if (getUrlVars('saveSuccessFull') == 1) {
    $('#div_alert').showAlert({message: '{{Sauvegarde effectuée avec succès}}<br>{{Utilisez l\icône suivante pour voir le détail de l\'élément <i class="fa jeedom-alerte"></i>}}', level: 'success'});
}
</script>