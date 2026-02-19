import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldOverlay } from "../ui/ShieldOverlay";

const VIEW_SIMPLE = 'simple';
const VIEW_ADVANCED = 'advanced';
const TRANSITION_DURATION_MS = 220;
const OVERLAY_MIN_VISIBLE_MS = 250;

export class DashboardViewToggle extends BaseAutoExecComponent {

	canRun() {
		return !!document.querySelector( '.dashboard-view-switch__toggle' )
			&& !!document.querySelector( '.dashboard-overview-panels' );
	}

	run() {
		this.isSwitching = false;
		this.switchRoot = document.querySelector( '.dashboard-view-switch' );
		this.toggleTrigger = document.querySelector( '.dashboard-view-switch__toggle' );
		this.panelsContainer = document.querySelector( '.dashboard-overview-panels' );
		this.overlayContainerID = this.resolveOverlayContainerID();
		this.simpleLabel = this.switchRoot?.querySelector( '.dashboard-view-switch__label--simple' );
		this.advancedLabel = this.switchRoot?.querySelector( '.dashboard-view-switch__label--advanced' );
		this.currentView = this.detectCurrentView();
		this.applySwitchState( this.currentView );

		shieldEventsHandler_Main.add_Click( '.dashboard-view-switch__toggle', ( triggerEl ) => {
			if ( this.isSwitching ) {
				return;
			}

			const targetView = this.currentView === VIEW_ADVANCED ? VIEW_SIMPLE : VIEW_ADVANCED;
			const currentPanel = this.getPanelForView( this.currentView );
			const targetPanel = this.getPanelForView( targetView );
			if ( !currentPanel || !targetPanel ) {
				window.location.assign( triggerEl.href );
				return;
			}

			this.switchToView( targetView, currentPanel, targetPanel );
		} );
	}

	switchToView( targetView, currentPanel, targetPanel ) {
		this.isSwitching = true;
		this.switchRoot?.classList.add( 'is-busy' );
		this.applySwitchState( targetView );
		this.lockPanelsHeight( currentPanel, targetPanel );
		this.panelsContainer?.classList.add( 'is-switching' );

		const overlayStartAt = Date.now();
		ShieldOverlay.Show( this.overlayContainerID );

		const uiTransition = this.swapPanels( currentPanel, targetPanel, targetView );
		const preferenceSave = this.saveViewPreference( targetView );

		Promise.allSettled( [ uiTransition, preferenceSave ] )
		.then( ( results ) => {
			const saveResult = results[ 1 ]?.status === 'fulfilled' ? results[ 1 ].value : { success: false };
			if ( !saveResult.success ) {
				this.showSaveFailureMessage();
			}
		} )
		.finally( () => {
			const elapsed = Date.now() - overlayStartAt;
			window.setTimeout( () => ShieldOverlay.Hide(), Math.max( 0, OVERLAY_MIN_VISIBLE_MS - elapsed ) );
			this.unlockPanelsHeight();
			this.resetTransitionClasses( currentPanel, targetPanel );

			this.currentView = targetView;
			this.isSwitching = false;
			this.switchRoot?.classList.remove( 'is-busy' );
			this.panelsContainer?.classList.remove( 'is-switching' );
		} );
	}

	detectCurrentView() {
		if ( this.switchRoot?.classList.contains( 'is-simple' ) ) {
			return VIEW_SIMPLE;
		}
		return this.switchRoot?.classList.contains( 'is-advanced' ) ? VIEW_ADVANCED : VIEW_SIMPLE;
	}

	applySwitchState( view ) {
		const isAdvanced = view === VIEW_ADVANCED;
		this.switchRoot?.classList.toggle( 'is-advanced', isAdvanced );
		this.switchRoot?.classList.toggle( 'is-simple', !isAdvanced );
		if ( this.switchRoot ) {
			this.switchRoot.dataset.dashboardViewCurrent = isAdvanced ? VIEW_ADVANCED : VIEW_SIMPLE;
		}
		this.simpleLabel?.classList.toggle( 'is-active', !isAdvanced );
		this.advancedLabel?.classList.toggle( 'is-active', isAdvanced );
	}

	getPanelForView( view ) {
		return this.panelsContainer?.querySelector( `[data-dashboard-view="${view}"]` ) || null;
	}

	swapPanels( fromPanel, toPanel, targetView ) {
		this.resetTransitionClasses( fromPanel, toPanel );

		if ( this.isReducedMotion() ) {
			fromPanel.classList.remove( 'is-active' );
			toPanel.classList.add( 'is-active' );
			this.dispatchViewChanged( targetView );
			return Promise.resolve();
		}

		toPanel.classList.add( 'is-active' );
		fromPanel.classList.add( 'is-active', 'is-current' );

		return new Promise( ( resolve ) => {
			window.requestAnimationFrame( () => {
				fromPanel.classList.remove( 'is-current' );
				fromPanel.classList.add( 'is-leaving' );
				toPanel.classList.add( 'is-entering' );

				this.waitForTransition( toPanel )
				.then( () => {
					fromPanel.classList.remove( 'is-active', 'is-leaving', 'is-current' );
					toPanel.classList.add( 'is-active' );
					toPanel.classList.remove( 'is-entering', 'is-leaving', 'is-current' );
					this.dispatchViewChanged( targetView );
				} )
				.finally( () => resolve() );
			} );
		} );
	}

	waitForTransition( panel ) {
		return new Promise( ( resolve ) => {
			let didResolve = false;
			let timeoutID = null;

			const finish = () => {
				if ( didResolve ) {
					return;
				}
				didResolve = true;
				if ( timeoutID ) {
					window.clearTimeout( timeoutID );
				}
				panel.removeEventListener( 'transitionend', onTransitionEnd );
				resolve();
			};

			const onTransitionEnd = ( evt ) => {
				if ( evt.target === panel ) {
					finish();
				}
			};

			panel.addEventListener( 'transitionend', onTransitionEnd );
			timeoutID = window.setTimeout( finish, TRANSITION_DURATION_MS + 120 );
		} );
	}

	resetTransitionClasses( ...panels ) {
		panels.filter( ( panel ) => !!panel ).forEach( ( panel ) => {
			panel.classList.remove( 'is-current', 'is-entering', 'is-leaving' );
		} );
	}

	lockPanelsHeight( currentPanel, targetPanel ) {
		if ( !this.panelsContainer ) {
			return;
		}

		const currentHeight = Math.max( 1, Math.round( currentPanel.getBoundingClientRect().height ) );
		const targetHeight = Math.max( 1, Math.round( this.measurePanelHeight( targetPanel ) ) );

		this.panelsContainer.style.height = `${currentHeight}px`;
		this.panelsContainer.style.transition = `height ${TRANSITION_DURATION_MS}ms ease`;
		this.panelsContainer.style.overflow = 'hidden';

		window.requestAnimationFrame( () => {
			this.panelsContainer.style.height = `${targetHeight}px`;
		} );
	}

	unlockPanelsHeight() {
		if ( !this.panelsContainer ) {
			return;
		}
		this.panelsContainer.style.height = '';
		this.panelsContainer.style.transition = '';
		this.panelsContainer.style.overflow = '';
	}

	measurePanelHeight( panel ) {
		if ( !panel ) {
			return 0;
		}

		const wasActive = panel.classList.contains( 'is-active' );
		if ( !wasActive ) {
			panel.classList.add( 'is-active' );
		}
		const height = panel.getBoundingClientRect().height;
		if ( !wasActive ) {
			panel.classList.remove( 'is-active' );
		}

		return height;
	}

	dispatchViewChanged( targetView ) {
		document.dispatchEvent( new CustomEvent( 'shield:dashboard-view-changed', {
			detail: {
				view: targetView,
			}
		} ) );
	}

	resolveOverlayContainerID() {
		const preferred = [
			'PageMainBody_Inner-Apto',
			'PageContainer-Apto',
		];
		const found = preferred.find( ( id ) => !!document.getElementById( id ) );
		return found || null;
	}

	saveViewPreference( targetView ) {
		if ( !( 'dashboard_view_toggle' in ( this._base_data?.ajax || {} ) ) ) {
			return Promise.resolve( { success: false } );
		}

		return ( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.dashboard_view_toggle, {
				view: targetView
			} ),
			false,
			true
		)
		.then( ( resp ) => ( {
			success: !!resp?.success,
		} ) );
	}

	showSaveFailureMessage() {
		const msg = this._base_data?.strings?.save_failed
			|| 'Could not save dashboard view preference. It may reset when you reload this page.';
		if ( typeof shieldServices !== 'undefined' && typeof shieldServices.notification === 'function' ) {
			shieldServices.notification().showMessage( msg, false );
		}
	}

	isReducedMotion() {
		return !!window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}
}
