import { Tab, Tooltip } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";

export class RailSidebarController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'[data-shield-rail-target][data-bs-toggle="tab"]',
			( item ) => this.handleBootstrapTabShown( item ),
			false
		);

		shieldEventsHandler_Main.add_Click(
			'[data-shield-rail-switch]',
			( el, evt ) => {
				evt.preventDefault();
				this.switchRailTarget( el );
			},
			false
		);

		shieldEventsHandler_Main.add_Keyup(
			'[data-shield-rail-switch]',
			( el, evt ) => {
				if ( evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar' ) {
					evt.preventDefault();
					this.switchRailTarget( el );
				}
			},
			false
		);

		this.activateCurrentRailPanes();
	}

	switchRailTarget( el ) {
		const targetKey = ( el.dataset.shieldRailSwitch || '' ).trim();
		if ( targetKey.length < 1 ) {
			return;
		}
		const scope = el.closest( '[data-shield-rail-scope="1"]' );
		if ( !scope ) {
			return;
		}
		const railItem = scope.querySelector( `[data-shield-rail-target="${targetKey}"]` );
		if ( railItem ) {
			Tab.getOrCreateInstance( railItem ).show();
		}
	}

	handleBootstrapTabShown( item ) {
		const scope = item.closest( '[data-shield-rail-scope="1"]' );
		if ( scope === null ) {
			return;
		}

		const sidebar = scope.querySelector( '.shield-rail-sidebar' );
		if ( sidebar !== null ) {
			sidebar.querySelectorAll( '[data-shield-rail-target]' ).forEach( ( candidate ) => {
				candidate.classList.toggle( 'is-active', candidate === item );
			} );
		}

		const targetPane = this.findBootstrapTargetPane( item, scope );
		if ( targetPane === null ) {
			return;
		}

		this.activatePaneEnhancements( scope, targetPane );
	}

	findBootstrapTargetPane( item, scope ) {
		const targetSelector = ( item.dataset.bsTarget || item.getAttribute( 'href' ) || '' ).trim();
		if ( targetSelector.length < 2 || !targetSelector.startsWith( '#' ) ) {
			return null;
		}

		return scope.querySelector( targetSelector );
	}
	disposeTooltipsWithin( container ) {
		container.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( el ) => {
			const tip = Tooltip.getInstance( el );
			if ( tip ) {
				tip.dispose();
			}
		} );
	}

	activatePaneEnhancements( scope, targetPane ) {
		const contentArea = scope.querySelector( '.shield-rail-layout__content' );
		if ( contentArea !== null ) {
			this.disposeTooltipsWithin( contentArea );
		}
		UiContentActivator.activateWithin( targetPane );
	}

	activateCurrentRailPanes() {
		document.querySelectorAll( '[data-shield-rail-scope="1"]' ).forEach( ( scope ) => {
			const activeItem = scope.querySelector(
				'[data-shield-rail-target][data-bs-toggle="tab"].active, '
				+ '[data-shield-rail-target][data-bs-toggle="tab"][aria-selected="true"]'
			);
			if ( activeItem === null ) {
				return;
			}

			const targetPane = this.findBootstrapTargetPane( activeItem, scope );
			if ( targetPane !== null ) {
				this.activatePaneEnhancements( scope, targetPane );
			}
		} );
	}
}
