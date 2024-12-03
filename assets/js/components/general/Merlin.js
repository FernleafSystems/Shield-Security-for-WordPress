import $ from 'jquery';
import smartWizard from 'smartwizard';
import { BaseComponent } from "../BaseComponent";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

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
		this.$merlin.on( 'showStep', () => BootstrapTooltips.RegisterNewTooltipsWithin( this.merlinContainer ) );

		this.slideSecurityProfiles();
	}

	slideSecurityProfiles() {

		shieldEventsHandler_Main.add_Mouseover( '#TableSecurityProfiles .level_cell', ( targetEl ) => {
			const level = targetEl.dataset.level;
			this.merlinContainer.querySelectorAll( '#TableSecurityProfiles .level_cell' ).forEach( ( cell ) => {
				cell.classList.remove( 'hover_column' );
			} );
			this.merlinContainer.querySelectorAll( '#TableSecurityProfiles .level_cell_' + level ).forEach( ( cell ) => {
				cell.classList.add( 'hover_column' );
			} );
		} );
		shieldEventsHandler_Main.add_Mouseout( '#TableSecurityProfiles .level_cell', ( targetEl ) => {
			this.merlinContainer.querySelectorAll( '#TableSecurityProfiles .level_cell' ).forEach( ( cell ) => {
				cell.classList.remove( 'hover_column' );
			} );
		} );

		shieldEventsHandler_Main.add_Click( '#TableSecurityProfiles .level_cell', ( cell ) => {
			const level = cell.dataset.level;
			const wasActive = cell.classList.contains( 'active_column' );
			this.merlinContainer.querySelectorAll( '#TableSecurityProfiles .level_cell' ).forEach( ( levelCell ) => {
				levelCell.classList.remove( 'active_column' );
			} );
			if ( !wasActive ) {
				this.merlinContainer.querySelectorAll( '#TableSecurityProfiles .level_cell_' + level ).forEach( ( levelCell ) => {
					levelCell.classList.add( 'active_column' );
				} );
			}

			const input = document.getElementById( 'SelectedSecurityProfile' ) || false;
			if ( input ) {
				input.value = wasActive ? '' : level;
			}
		} );
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