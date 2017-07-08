    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic protexiom_ctrl" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
    	<div class="row">
    		<div class="col-sm-7">
        		<form class="form-horizontal">
            		<fieldset>
                		<legend>
                    		<i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}
				   		<i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i>
                		</legend>
                		<div class="form-group">
                    		<label class="col-lg-2 control-label">{{Nom de la télécommande}}</label>
                    		<div class="col-lg-3">
                        		<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        		<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de la télécommande}}"/>
                    		</div>
                		</div>
                		<div class="form-group">
                    		<label class="col-lg-2 control-label" >{{Objet parent}}</label>
                    		<div class="col-lg-3">
                        		<select class="form-control eqLogicAttr" data-l1key="object_id">
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
		                  <label class="col-sm-2 control-label" >{{Activer}}</label>
							<div class="col-sm-10">
							<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>{{Activer}}</label>
							<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>{{Visible}}</label>
							</div>
		                </div>
		            </fieldset> 
		        </form>
        	</div>
		<div class="col-sm-5">
			<form class="form-horizontal">
    			<fieldset>
      				<legend>{{Informations}}
       					<i id="bt_displayStatus" title="{{Afficher le status}}" class="fa fa-search expertModeVisible pull-right tooltips cursor"></i>
     				</legend>
        			<center>
    					<img src="core/img/no_image.gif" data-original=".jpg" id="img_device" class="img-responsive img_device" style="max-height : 250px;"/>
  					</center>
  					<div class="form-group">
                    	<label class="col-sm-2 control-label">{{Commentaire}}</label>
                    	<div class="col-sm-10">
                    		<textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire"></textarea>
                    	</div>
                	</div>
  				</fieldset>
  			</form>
		</div>
	</div>

<?php
/* Command list
 * will be populated by the addCmdToTable() js function in desktop/protexiom.js
*/
?>
        <legend>{{Commandes}}</legend>
        <?php /* The command list is static. Lets not offer the possibility to remove them
        <a class="btn btn-success btn-sm cmdAction" data-action="add"><i class="fa fa-plus-circle"></i> {{Commandes}}</a><br/><br/>
        */ ?>
        <table id="table_cmd_protexiom_ctrl" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 50px;">{{ID}}</th>
                    <th style="width: 230px;">{{Nom}}</th>
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
