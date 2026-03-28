import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import {
	getActiveLayerIndex,
	getLayersForShell,
	parseJsonAttribute,
	parseLayerIndex
} from "./DrillDownShared";

export class ReportsLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		this.bindHandlers();
		this.initializeCurrentRoot();
	}

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		this.shellEl = this.getShell( this.rootEl );

		if ( this.rootEl === null || this.shellEl === null ) {
			return;
		}

		const selection = this.getVisibleWorkspaceSelection();
		if ( selection === null ) {
			return;
		}

		if ( getActiveLayerIndex( getLayersForShell( this.shellEl ) ) > 0 ) {
			this.updateWorkspaceHeader( selection );
			this.activateWorkspaceSection( selection.key );
		}
	}

	bindHandlers() {
		if ( this.hasBoundHandlers ) {
			return;
		}
		this.hasBoundHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-reports-landing="1"] [data-drill-target="workspace"]',
			( item, evt ) => this.handleWorkspaceSelectionClick( item, evt ),
			false
		);
	}

	getRoot() {
		return document.querySelector( '[data-reports-landing="1"]' );
	}

	getShell( root = this.rootEl ) {
		return root?.querySelector( '[data-drill-shell="1"]' ) || null;
	}

	handleWorkspaceSelectionClick( item, evt ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		const shell = this.getShell( root );
		const drillCtrl = this.getDrillDownController();
		if ( shell === null || drillCtrl === null ) {
			return;
		}

		const selection = this.readWorkspaceSelection(
			item.dataset.reportsWorkspaceSelection || ''
		);
		if ( selection === null ) {
			return;
		}

		evt.preventDefault();

		this.rootEl = root;
		this.shellEl = shell;
		this.setActiveWorkspace( selection.key );
		this.updateWorkspaceHeader( selection );

		const workspaceLayerIndex = this.getLayerIndexByKey( shell, 'workspace' );
		if ( workspaceLayerIndex >= 0 ) {
			drillCtrl.drillTo( shell, workspaceLayerIndex );
		}

		this.activateWorkspaceSection( selection.key );
	}

	readWorkspaceSelection( rawValue ) {
		const selection = parseJsonAttribute( rawValue, {} );
		const key = String( selection?.key || '' ).trim();
		if ( key.length < 1 ) {
			return null;
		}

		return {
			key,
			header: selection?.header && typeof selection.header === 'object'
				? selection.header
				: {},
		};
	}

	getVisibleWorkspaceSelection() {
		const activeSection = this.getWorkspaceSections().find(
			( section ) => !section.classList.contains( 'd-none' )
		) || this.getWorkspaceSections()[ 0 ] || null;

		return activeSection instanceof HTMLElement
			? this.readWorkspaceSelection( activeSection.dataset.reportsWorkspaceSelection || '' )
			: null;
	}

	setActiveWorkspace( workspaceKey ) {
		this.getWorkspaceSections().forEach( ( section ) => {
			const isActive = String( section.dataset.reportsWorkspace || '' ).trim() === workspaceKey;
			section.classList.toggle( 'd-none', !isActive );
		} );
	}

	activateWorkspaceSection( workspaceKey ) {
		const section = this.getWorkspaceSection( workspaceKey );
		if ( section !== null ) {
			UiContentActivator.activateCurrentSubtree( section );
		}
	}

	getWorkspaceSections() {
		return Array.from(
			this.rootEl?.querySelectorAll( '[data-reports-workspace]' ) || []
		).filter( ( section ) => section.closest( '[data-reports-landing="1"]' ) === this.rootEl );
	}

	getWorkspaceSection( workspaceKey ) {
		return this.getWorkspaceSections().find(
			( section ) => String( section.dataset.reportsWorkspace || '' ).trim() === workspaceKey
		) || null;
	}

	updateWorkspaceHeader( selection ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		const workspaceLayerIndex = this.getLayerIndexByKey( this.shellEl, 'workspace' );
		if ( workspaceLayerIndex >= 0 ) {
			drillCtrl.updateLayerHeader( this.shellEl, workspaceLayerIndex, selection.header || {} );
		}
	}

	getLayerIndexByKey( shell, layerKey ) {
		const layer = getLayersForShell( shell )
			.find( ( candidate ) => String( candidate.dataset.drillLayerKey || '' ).trim() === layerKey ) || null;

		return layer === null ? -1 : parseLayerIndex( layer.dataset.drillLayer );
	}

	getDrillDownController() {
		return window.shieldAppMain?.components?.drill_down || null;
	}
}
