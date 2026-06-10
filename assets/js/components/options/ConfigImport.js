import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class ConfigImport extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#ImportSiteForm', ( form ) => {
			this.syncConnectionFormState( form );
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.import_from_site, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( 'form#ImportExportNetworkInviteAcceptForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.network_invite_accept, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( 'form#ImportExportNetworkInviteRejectForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.network_invite_reject, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
		shieldEventsHandler_Main.add_Click( '[data-import-export-task]', ( targetEl ) => {
			this.selectNetworkTask( targetEl );
		} );
		shieldEventsHandler_Main.add_Click( '[data-import-export-connect-reveal]', ( targetEl ) => {
			this.revealConnectionForm( targetEl );
		} );
		shieldEventsHandler_Main.add_Change( '[data-import-export-link-choice]', ( targetEl ) => {
			this.updatePrimaryActionLabel( targetEl );
		} );
		shieldEventsHandler_Main.add_Change( '[data-import-export-auth-choice]', ( targetEl ) => {
			this.syncConnectionFormState( targetEl );
		} );
		shieldEventsHandler_Main.add_Change( '[data-import-export-sync-toggle]', ( targetEl ) => {
			this.setSyncEnabled( targetEl );
		} );
		shieldEventsHandler_Main.add_Click( '[data-import-export-disconnect]', () => {
			this.disconnectMasterSite();
		} );
		shieldEventsHandler_Main.add_Click( '[data-import-export-add-clients]', ( targetEl ) => {
			this.openAddClientSites( targetEl );
		} );
		shieldEventsHandler_Main.add_Submit(
			'#ImportExportSitesAuthoriseUrlsForm',
			( targetEl ) => this.submitAuthoriseUrlsForm( targetEl )
		);

		document.querySelectorAll( 'form#ImportSiteForm' ).forEach( ( form ) => this.syncConnectionFormState( form ) );
	}

	revealConnectionForm( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-connect-reveal]' ) : null;
		if ( !( button instanceof HTMLButtonElement ) ) {
			return;
		}

		const panelID = button.getAttribute( 'aria-controls' ) || '';
		const panel = panelID.length > 0 ? document.getElementById( panelID ) : null;
		if ( !( panel instanceof HTMLElement ) ) {
			return;
		}

		panel.hidden = false;
		button.setAttribute( 'aria-expanded', 'true' );

		const form = panel.querySelector( 'form#ImportSiteForm' );
		if ( form instanceof HTMLFormElement ) {
			this.syncConnectionFormState( form );
		}
		const masterUrl = panel.querySelector( '#MasterSiteUrl' );
		if ( masterUrl instanceof HTMLInputElement ) {
			masterUrl.focus();
		}
	}

	selectNetworkTask( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-task]' ) : null;
		if ( !( button instanceof HTMLButtonElement ) ) {
			return;
		}

		const key = button.dataset.importExportTask;
		const workbench = button.closest( '[data-import-export-workbench]' );
		if ( !( workbench instanceof HTMLElement ) || typeof key !== 'string' || key.length < 1 ) {
			return;
		}

		workbench.querySelectorAll( '[data-import-export-task]' ).forEach( ( taskButton ) => {
			const isActive = taskButton === button;
			taskButton.classList.toggle( 'active', isActive );
			taskButton.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		workbench.querySelectorAll( '[data-import-export-pane]' ).forEach( ( pane ) => {
			if ( !( pane instanceof HTMLElement ) ) {
				return;
			}
			const isActive = pane.dataset.importExportPane === key;
			pane.classList.toggle( 'active', isActive );
			pane.hidden = !isActive;
		} );
	}

	updatePrimaryActionLabel( targetEl ) {
		const input = targetEl instanceof Element ? targetEl.closest( '[data-import-export-link-choice]' ) : null;
		if ( !( input instanceof HTMLInputElement ) ) {
			return;
		}
		const form = input.closest( 'form#ImportSiteForm' );
		const button = form?.querySelector( '[data-import-export-primary-action]' );
		const labelTarget = button instanceof HTMLElement ? button.querySelector( 'span' ) : null;

		if ( labelTarget instanceof HTMLElement && typeof input.dataset.actionLabel === 'string' ) {
			labelTarget.textContent = input.dataset.actionLabel;
		}
	}

	syncConnectionFormState( targetEl ) {
		const form = targetEl instanceof Element ? targetEl.closest( 'form#ImportSiteForm' ) : null;
		if ( !( form instanceof HTMLFormElement ) ) {
			return;
		}

		const keyOption = form.querySelector( '[data-import-export-auth-choice="key"]' );
		const secretField = form.querySelector( '[data-import-export-secret-field]' );
		const secretInput = form.querySelector( '#MasterSiteSecretKey' );
		const useSecretKey = keyOption instanceof HTMLInputElement && keyOption.checked;

		if ( secretField instanceof HTMLElement ) {
			secretField.classList.toggle( 'd-none', !useSecretKey );
		}
		if ( secretInput instanceof HTMLInputElement ) {
			secretInput.disabled = !useSecretKey;
			if ( !useSecretKey ) {
				secretInput.value = '';
			}
		}

		const selectedImportMode = form.querySelector( '[data-import-export-link-choice]:checked' );
		if ( selectedImportMode instanceof HTMLInputElement ) {
			this.updatePrimaryActionLabel( selectedImportMode );
		}
	}

	setSyncEnabled( targetEl ) {
		const toggle = targetEl instanceof Element ? targetEl.closest( '[data-import-export-sync-toggle]' ) : null;
		if ( !( toggle instanceof HTMLInputElement ) ) {
			return;
		}

		toggle.disabled = true;
		( new AjaxService() )
		.send( ObjectOps.Merge(
			this._base_data.ajax.set_enabled,
			{ enabled: toggle.checked ? 'Y' : 'N' }
		) )
		.finally( () => {
			toggle.disabled = false;
		} );
	}

	disconnectMasterSite() {
		( new AjaxService() )
		.send( this._base_data.ajax.disconnect_master )
		.finally();
	}

	openAddClientSites( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-add-clients]' ) : null;
		OffCanvasService.RenderCanvas(
			this._base_data.ajax.render_authorise_urls_offcanvas,
			{ launcher: button instanceof HTMLElement ? button : null }
		).finally();
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
}
