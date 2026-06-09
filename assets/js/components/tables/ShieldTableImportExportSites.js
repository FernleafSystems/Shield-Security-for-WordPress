import { ShieldTableBase } from "./ShieldTableBase";
import { AjaxService } from "../services/AjaxService";
import { OffCanvasService } from "../ui/OffCanvasService";
import { ObjectOps } from "../../util/ObjectOps";
import { Popover } from "bootstrap";

export class ShieldTableImportExportSites extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-ImportExportSites';
	}

	run() {
		super.run();
		this.bindSyncDetailsPopovers();
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push( {
			text: 'Queue Sync',
			name: 'queue-sync',
			className: 'action selected-action queue-sync btn-outline-primary mb-2',
			action: () => this.bulkTableAction( 'queue_sync' )
		} );
		return buttons;
	}

	bindEvents() {
		super.bindEvents();
		shieldEventsHandler_Main.add_Submit(
			'#ImportExportSitesAuthoriseUrlsForm',
			( targetEl ) => this.submitAuthoriseUrlsForm( targetEl )
		);
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.offcanvas',
			'.offcanvas.offcanvas_import_export_sites_authorise_urls',
			() => this.tableReload()
		);
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'rBpftip';
		cfg.pageLength = 100;
		return cfg;
	}

	submitAuthoriseUrlsForm( form ) {
		if ( !( form instanceof HTMLFormElement ) || this.authoriseUrlsRequestRunning ) {
			return;
		}

		this.authoriseUrlsRequestRunning = true;
		const submitButton = form.querySelector( 'button[type="submit"]' );
		if ( submitButton instanceof HTMLButtonElement ) {
			submitButton.disabled = true;
		}

		( new AjaxService() )
		.send( ObjectOps.Merge(
			this._base_data.ajax.authorise_urls_submit,
			{ 'form_data': Object.fromEntries( new FormData( form ) ) }
		) )
		.then( ( resp ) => {
			if ( resp?.success ) {
				OffCanvasService.CloseCanvas();
			}
		} )
		.finally( () => {
			this.authoriseUrlsRequestRunning = false;
			if ( submitButton instanceof HTMLButtonElement ) {
				submitButton.disabled = false;
			}
		} );
	}

	bindSyncDetailsPopovers() {
		const container = this.resolveTableContainer();
		if ( !( container instanceof HTMLElement ) || container.dataset.shieldSyncDetailsPopoverBound === '1' ) {
			return;
		}

		container.dataset.shieldSyncDetailsPopoverBound = '1';
		container.addEventListener( 'click', ( event ) => {
			const target = event.target instanceof Element
				? event.target.closest( '[data-shield-sync-details-trigger="1"]' )
				: null;
			if ( !( target instanceof HTMLElement ) || !container.contains( target ) ) {
				return;
			}

			event.preventDefault();
			this.hideOtherSyncDetailsPopovers( container, target );
			Popover.getOrCreateInstance( target, {
				trigger: 'manual',
				html: true,
				sanitize: false,
				placement: 'left',
				container: document.body,
				customClass: 'import-export-sync-details-popover',
				title: target.dataset.shieldSyncDetailsTitle,
				content: target.dataset.shieldSyncDetails,
			} ).toggle();
		} );
	}

	hideOtherSyncDetailsPopovers( container, currentTarget ) {
		container.querySelectorAll( '[data-shield-sync-details-trigger="1"]' ).forEach( ( target ) => {
			if ( target !== currentTarget ) {
				Popover.getInstance( target )?.hide();
			}
		} );
	}

	addButtons() {
		super.addButtons();
		this.$table.buttons( 'queue-sync:name' ).disable();
	}

	rowSelectionChanged() {
		if ( this.$table.rows( { selected: true } ).count() > 0 ) {
			this.$table.buttons( 'queue-sync:name' ).enable();
		}
		else {
			this.$table.buttons( 'queue-sync:name' ).disable();
		}
	}
}
