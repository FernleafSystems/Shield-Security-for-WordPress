import { ShieldTableBase } from "./ShieldTableBase";
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
		}, {
			text: 'Bulk Remove',
			name: 'bulk-remove',
			className: 'action selected-action bulk-remove btn-outline-warning mb-2',
			action: async ( e, dt, node ) => this.bulkRemoveManagedSites( this.resolveButtonLauncher( node ) )
		} );
		return buttons;
	}

	bindEvents() {
		super.bindEvents();
		shieldEventsHandler_Main.add_Click( '[data-import-export-site-delete]', async ( targetEl ) => {
			await this.deleteManagedSite( targetEl );
		} );
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

	async deleteManagedSite( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-site-delete]' ) : null;
		if ( !( button instanceof HTMLElement ) ) {
			return;
		}

		const rid = button.dataset.rid;
		if ( typeof rid !== 'string' || rid.length < 1 ) {
			return;
		}

		const dialog = shieldServices.dialog();
		const confirmed = await dialog.confirm( {
			message: this._base_data.strings.remove_site_confirm,
			confirmLabel: dialog.resolveConfirmLabel( button ),
			danger: true,
			launcher: button,
		} );
		if ( !confirmed ) {
			return;
		}

		this.bulkTableAction( 'delete_site', [ rid ], { launcher: button } );
	}

	async bulkRemoveManagedSites( launcher = null ) {
		const rids = this.getSelectedRIDs();
		if ( rids.length < 1 ) {
			return;
		}

		const dialog = shieldServices.dialog();
		const confirmed = await dialog.confirm( {
			message: this._base_data.strings.remove_selected_sites_confirm,
			confirmLabel: dialog.resolveConfirmLabel( launcher ),
			danger: true,
			launcher,
		} );
		if ( !confirmed ) {
			return;
		}

		this.bulkTableAction( 'delete_site', rids, { launcher } );
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
		this.syncSelectedActionButtons();
	}

	rowSelectionChanged() {
		this.syncSelectedActionButtons();
	}

	syncSelectedActionButtons() {
		const hasSelection = this.$table.rows( { selected: true } ).count() > 0;
		[ 'queue-sync:name', 'bulk-remove:name' ].forEach( ( selector ) => {
			if ( hasSelection ) {
				this.$table.buttons( selector ).enable();
			}
			else {
				this.$table.buttons( selector ).disable();
			}
		} );
	}
}
