import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";

export class ConfigureLandingController extends BaseAutoExecComponent {

	canRun() {
		return this.getConfigureRoot() !== null;
	}

	run() {
		this.isRefreshing = false;
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_zone_component_config',
			() => this.refreshConfigureSection(),
			false
		);
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

		const activePanelTarget = this.getActivePanelTarget();
		this.isRefreshing = true;

		( new AjaxService() )
		.send( requestData, false, true )
		.then( ( resp ) => {
			const renderOutput = ( resp && resp.success && typeof resp?.data?.render_output === 'string' )
				? resp.data.render_output
				: '';
			if ( this.replaceConfigureRoot( renderOutput ) ) {
				this.restoreActivePanel( activePanelTarget );
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

	getConfigureModeShell() {
		const root = this.getConfigureRoot();
		return root ? root.closest( '[data-mode-shell="1"][data-mode="configure"]' ) : null;
	}

	getActivePanelTarget() {
		const shell = this.getConfigureModeShell();
		return shell ? ( shell.dataset.modeActivePanel || '' ).trim() : '';
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

	restoreActivePanel( activePanelTarget ) {
		if ( typeof activePanelTarget !== 'string' || activePanelTarget.length < 1 ) {
			return;
		}

		const shell = this.getConfigureModeShell();
		if ( shell === null ) {
			return;
		}

		delete shell.dataset.modeActivePanel;

		const targetTile = Array.from( shell.querySelectorAll( '[data-mode-tile="1"]' ) )
		.find( ( tile ) => {
			return ( tile.dataset.modePanelTarget || tile.dataset.modeTileKey || '' ).trim() === activePanelTarget;
		} );

		if ( targetTile !== undefined && targetTile.dataset.modeTileDisabled !== '1' ) {
			targetTile.click();
		}
	}
}
