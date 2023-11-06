import $ from 'jquery';
import smartWizard from 'smartwizard';
import { BaseComponent } from "../BaseComponent";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

export class Merlin extends BaseComponent {

	init() {
		this.merlinContainer = document.getElementById( 'merlin' ) || false;
		this.exec();
	}

	canRun() {
		return this.merlinContainer;
	}

	run() {
		this.$merlin = $( this.merlinContainer ).smartWizard( this._base_data.vars.smartwizard_cfg );

		shieldEventsHandler_Main.add_Submit( 'form.merlin-form.ajax-form', ( targetEl ) => this.#runSettingUpdate( targetEl ) );
		shieldEventsHandler_Main.add_Click( '#merlin a.skip-step', () => this.$merlin.smartWizard( 'next' ) );
	}

	#runSettingUpdate( form ) {
		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.action, { form_params: Forms.Serialize( form ) } )
		)
		.then( ( resp ) => {

			if ( resp.success ) {
				this.$merlin.smartWizard( 'next' );
			}
			else {
				alert( resp.data.message );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
		} )
		.finally( () => ShieldOverlay.Hide() );
	};
}