import { Tab } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ConfigureLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		this.initializeCurrentRoot();
		this.isRefreshing = false;
		document.addEventListener( 'shield:expansion-form-saved', ( evt ) => {
			if ( this.getConfigureRoot()?.contains( evt.target ) ) {
				this.refreshConfigureSection();
			}
		} );
	}

	initializeCurrentRoot() {
		this.rootEl = this.getConfigureRoot();
	}

	refreshConfigureSection() {
		if ( this.isRefreshing ) {
			return;
		}

		const configureRoot = this.getConfigureRoot();
		if ( configureRoot === null ) {
			return;
		}

		const requestData = this.parseRenderActionData( configureRoot.dataset.configureRenderAction || '' );
		if ( requestData === null ) {
			return;
		}

		const activeZoneKey = this.getActiveRailItemKey( configureRoot );
		this.isRefreshing = true;

		( new AjaxService() )
		.send( requestData, false, true )
		.then( ( resp ) => {
			const renderOutput = ( resp && resp.success && typeof resp?.data?.render_output === 'string' )
				? resp.data.render_output
				: '';
			if ( this.replaceConfigureRoot( renderOutput ) ) {
				this.reinitializeConfigureComponents();
				this.restoreActiveRailItem( activeZoneKey );
				UiContentActivator.activateCurrentWithinRoot( this.getConfigureRoot() );
				return;
			}
			window.location.reload();
		} )
		.catch( () => {
			window.location.reload();
		} )
		.finally( () => {
			this.isRefreshing = false;
		} );
	}

	getConfigureRoot() {
		return document.querySelector( '[data-configure-landing="1"]' );
	}

	getActiveRailItemKey( configureRoot ) {
		const activeItem = configureRoot.querySelector( '.shield-rail-sidebar__item.active, .shield-rail-sidebar__item.is-active' );
		return activeItem !== null ? ( activeItem.dataset.shieldRailTarget || '' ).trim() : '';
	}

	parseRenderActionData( rawJson ) {
		if ( typeof rawJson !== 'string' || rawJson.trim().length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawJson );
			return ( parsed && typeof parsed === 'object' ) ? parsed : null;
		}
		catch ( e ) {
			return null;
		}
	}

	replaceConfigureRoot( renderOutput ) {
		if ( typeof renderOutput !== 'string' || renderOutput.length < 1 ) {
			return false;
		}

		const currentRoot = this.getConfigureRoot();
		if ( currentRoot === null ) {
			return false;
		}

		const parsed = ( new DOMParser() ).parseFromString( renderOutput, 'text/html' );
		const nextRoot = parsed.querySelector( '[data-configure-landing="1"]' );
		if ( nextRoot === null ) {
			return false;
		}

		currentRoot.replaceWith( nextRoot );
		return true;
	}

	restoreActiveRailItem( activeZoneKey ) {
		if ( typeof activeZoneKey !== 'string' || activeZoneKey.length < 1 ) {
			return;
		}

		const root = this.getConfigureRoot();
		if ( root === null ) {
			return;
		}

		const targetItem = root.querySelector( `[data-shield-rail-target="${activeZoneKey}"]` );
		if ( targetItem !== null ) {
			Tab.getOrCreateInstance( targetItem ).show();
		}
	}

	reinitializeConfigureComponents() {
		const app = global.shieldAppMain;
		if ( !app || typeof app.getComponent !== 'function' ) {
			return;
		}

		[ 'configure_landing', 'configure_expand_loader' ].forEach( ( componentId ) => {
			app.getComponent( componentId )?.initializeCurrentRoot?.();
		} );
	}
}
