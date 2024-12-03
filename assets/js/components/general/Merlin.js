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
		shieldEventsHandler_Main.add_Mouseover( '.security_profiles_section .card', ( targetEl ) => {
			this.merlinContainer.querySelectorAll( '.security_profiles_section .card' ).forEach( ( card ) => {
				if ( !card.classList.contains( 'active_profile' ) ) {
					card.classList.remove( 'border-primary' );
				}
			} );
			targetEl.classList.add( 'border-primary' );
		} );
		shieldEventsHandler_Main.add_Mouseout( '.security_profiles_section .card', ( targetEl ) => {
			this.merlinContainer.querySelectorAll( '.security_profiles_section .card' ).forEach( ( card ) => {
				if ( !card.classList.contains( 'active_profile' ) ) {
					card.classList.remove( 'border-primary' );
				}
			} );
			if ( targetEl.classList.contains( 'active_profile' ) ) {
				targetEl.classList.add( 'border-primary' );
			}
		} );
		shieldEventsHandler_Main.add_Click( '.security_profiles_section .card', ( targetEl ) => {
			const isActive = targetEl.classList.contains( 'active_profile' );
			this.merlinContainer.querySelectorAll( '.security_profiles_section .card' ).forEach( ( card ) => {
				card.classList.remove( 'active_profile' );
				card.classList.remove( 'border-primary' );
			} );
			if ( !isActive ) {
				targetEl.classList.add( 'active_profile' );
				targetEl.classList.add( 'border-primary' );
			}

			const input = document.getElementById( 'SelectedSecurityProfile' ) || false;
			if ( input ) {
				const activeContainer = this.merlinContainer.querySelector( '.security_profiles_section .card.active_profile' );
				input.value = activeContainer ? activeContainer.dataset[ 'profile' ] : '';
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