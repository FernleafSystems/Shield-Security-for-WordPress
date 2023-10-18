import $ from 'jquery';
import smartWizard from 'smartwizard';
import { BaseService } from "./BaseService";
import { ShieldOverlay } from "./ShieldOverlay";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class Merlin extends BaseService {

	init() {
		this.merlinContainer = document.getElementById( 'merlin' ) || false;
		this.exec();
	}

	canRun() {
		return this.merlinContainer;
	}

	run() {
		this.$merlin = $( this.merlinContainer ).smartWizard( this._base_data.vars.smartwizard_cfg );
		$( 'form.merlin-form.ajax-form', $( this.merlinContainer ) ).on( 'submit', ( evt ) => this.#runSettingUpdate( evt ) );
		$( this.merlinContainer ).on( 'click', 'a.skip-step', () => this.$merlin.smartWizard( 'next' ) );
	}

	#runSettingUpdate( evt ) {
		evt.preventDefault();

		let params = ObjectOps.ObjClone( this._base_data.ajax.action );
		params.form_params = $( evt.currentTarget ).serialize();

		( new AjaxService() )
		.send( params )
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
		.finally( ( resp ) => {
			ShieldOverlay.Hide();
		} );

		return false;
	};
}