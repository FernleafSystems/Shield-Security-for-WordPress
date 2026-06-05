import { ShieldTableBase } from "./ShieldTableBase";
import { AjaxService } from "../services/AjaxService";
import { OffCanvasService } from "../ui/OffCanvasService";
import { ObjectOps } from "../../util/ObjectOps";

export class ShieldTableImportExportSites extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-ImportExportSites';
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push( {
			text: this._base_data.strings.add_authorised_urls,
			name: 'authorise-urls',
			className: 'action authorise-urls btn-outline-primary mb-2',
			action: ( e, dt, node ) => {
				OffCanvasService.RenderCanvas(
					this._base_data.ajax.render_authorise_urls_offcanvas,
					{ launcher: this.resolveButtonLauncher( node ) }
				).finally();
			}
		} );
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
