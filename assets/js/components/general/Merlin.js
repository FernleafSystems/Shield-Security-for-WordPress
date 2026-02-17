import { BaseComponent } from "../BaseComponent";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

export class Merlin extends BaseComponent {

	init() {
		this.merlinContainer = document.getElementById( 'merlin' ) || false;
		this.currentStep = 0;
		this.exec();
	}

	canRun() {
		return this.merlinContainer;
	}

	run() {
		this.stepPanes = this.merlinContainer.querySelectorAll( '.wizard-step-pane' );
		this.stepperSteps = this.merlinContainer.querySelectorAll( '.stepper-step' );
		this.totalSteps = this.stepPanes.length;

		this.merlinContainer.querySelector( '.merlin-next' )
			?.addEventListener( 'click', () => this.next() );
		this.merlinContainer.querySelector( '.merlin-prev' )
			?.addEventListener( 'click', () => this.prev() );

		shieldEventsHandler_Main.add_Submit(
			'form.merlin-form.ajax-form',
			( targetEl ) => this.#runSettingUpdate( targetEl )
		);
		shieldEventsHandler_Main.add_Click(
			'#merlin a.skip-step, #merlin .skip-link',
			() => this.next()
		);

		this.slideSecurityProfiles();
		this.goToStep( 0 );
	}

	goToStep( index ) {
		if ( index < 0 || index >= this.totalSteps ) return;
		this.currentStep = index;

		this.stepPanes.forEach( ( pane, i ) =>
			pane.classList.toggle( 'd-none', i !== index )
		);

		this.stepperSteps.forEach( ( step, i ) => {
			step.classList.remove( 'completed', 'current', 'future' );
			const num = step.querySelector( '.step-number' );
			const check = step.querySelector( '.step-check' );
			if ( i < index ) {
				step.classList.add( 'completed' );
				num?.classList.add( 'd-none' );
				check?.classList.remove( 'd-none' );
			}
			else if ( i === index ) {
				step.classList.add( 'current' );
				num?.classList.remove( 'd-none' );
				check?.classList.add( 'd-none' );
			}
			else {
				step.classList.add( 'future' );
				num?.classList.remove( 'd-none' );
				check?.classList.add( 'd-none' );
			}
		} );

		const isFirst = index === 0;
		const isLast = index === this.totalSteps - 1;

		this.merlinContainer.querySelector( '.merlin-prev' ).disabled = isFirst;
		this.merlinContainer.querySelector( '.merlin-next' ).disabled = isLast;

		BootstrapTooltips.RegisterNewTooltipsWithin( this.merlinContainer );
	}

	next() { this.goToStep( this.currentStep + 1 ); }
	prev() { this.goToStep( this.currentStep - 1 ); }

	slideSecurityProfiles() {

		shieldEventsHandler_Main.add_Click( '#ProfileCards .profile-card:not(.profile-card-current)', ( card ) => {
			const level = card.dataset.level;
			const wasSelected = card.classList.contains( 'selected' );

			this.merlinContainer.querySelectorAll( '#ProfileCards .profile-card' ).forEach( ( c ) => {
				c.classList.remove( 'selected' );
			} );

			if ( !wasSelected ) {
				card.classList.add( 'selected' );
			}

			const input = document.getElementById( 'SelectedSecurityProfile' ) || false;
			if ( input ) {
				input.value = wasSelected ? '' : level;
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
				this.next();
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
