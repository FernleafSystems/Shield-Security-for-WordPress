import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ConfigureExpandLoader extends BaseAutoExecComponent {

	canRun() {
		return !!this._base_data?.ajax?.offcanvas_zone_component_config;
	}

	run() {
		this.ajaxBase = this._base_data.ajax.offcanvas_zone_component_config;

		shieldEventsHandler_Main.addHandler(
			'shown.bs.collapse',
			'[data-shield-expand-body="1"]',
			( expansion ) => {
				if ( this.getConfigureRoot()?.contains( expansion ) ) {
					this.handleExpansionOpened( expansion );
				}
			},
			false
		);

		shieldEventsHandler_Main.add_Click(
			'.shield-detail-expansion__btn-save',
			( button ) => this.handleSaveClick( button ),
			false
		);
	}

	getConfigureRoot() {
		return document.querySelector( '[data-configure-landing="1"]' );
	}

	handleExpansionOpened( expansion ) {
		const placeholder = expansion.querySelector( '[data-configure-expand-ajax="1"]' );
		if ( placeholder === null || placeholder.dataset.configureExpandLoading === '1' ) {
			return;
		}
		if ( expansion.querySelector( 'form.options_form_for' ) !== null ) {
			return;
		}

		placeholder.dataset.configureExpandLoading = '1';
		placeholder.innerHTML = this.buildLoadingMarkup();
		this.setSaveButtonDisabled( expansion, true );

		const componentData = {};
		for ( const key in placeholder.dataset ) {
			if ( key !== 'configureExpandAjax' && key !== 'configureExpandLoading' ) {
				componentData[ key ] = placeholder.dataset[ key ];
			}
		}
		componentData.form_context = 'expansion';

		( new AjaxService() )
		.send(
			ObjectOps.Merge(
				ObjectOps.ObjClone( this.ajaxBase ),
				componentData
			),
			false,
			true
		)
		.then( ( resp ) => {
			if ( resp && resp.success && typeof resp.data?.html === 'string' ) {
				this.injectForm( placeholder, resp.data.html, expansion );
				return;
			}
			this.renderLoadFailure( placeholder, expansion, 'Unable to load these settings. Please try again.' );
		} )
		.catch( () => {
			this.renderLoadFailure( placeholder, expansion, 'Unable to load these settings. Please try again.' );
		} );
	}

	injectForm( placeholder, responseHtml, expansion ) {
		const tempDiv = document.createElement( 'div' );
		tempDiv.innerHTML = responseHtml;

		const form = tempDiv.querySelector( 'form.options_form_for' );
		if ( form === null ) {
			this.renderLoadFailure( placeholder, expansion, 'No settings are available for this component.' );
			return;
		}

		form.dataset.context = 'expansion';
		form.querySelector( '.shield-options-rail-save' )?.remove();

		const rail = form.querySelector( '.shield-options-rail' );
		if ( rail ) {
			rail.style.display = 'none';
		}

		placeholder.replaceWith( form );
		this.setSaveButtonDisabled( expansion, false );
		UiContentActivator.activateCurrentSubtree( expansion );
	}

	renderLoadFailure( placeholder, expansion, message ) {
		delete placeholder.dataset.configureExpandLoading;
		placeholder.innerHTML = `<div class="alert alert-warning mb-0">${this.escapeHtml( message )}</div>`;
		this.setSaveButtonDisabled( expansion, true );
	}

	handleSaveClick( button ) {
		const expansion = button.closest( '[data-shield-expand-body="1"]' );
		if ( expansion === null ) {
			return;
		}

		const form = expansion.querySelector( 'form.options_form_for' );
		if ( form !== null ) {
			form.requestSubmit();
		}
	}

	setSaveButtonDisabled( expansion, isDisabled ) {
		const button = expansion.querySelector( '.shield-detail-expansion__btn-save' );
		if ( button !== null ) {
			button.disabled = isDisabled;
		}
	}

	buildLoadingMarkup() {
		const spinner = document.getElementById( 'ShieldWaitSpinner' );
		if ( spinner instanceof HTMLElement ) {
			const clone = spinner.cloneNode( true );
			clone.id = '';
			clone.classList.remove( 'd-none' );
			return clone.outerHTML;
		}

		return '<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-3" role="status"><span class="visually-hidden">Loading...</span></div></div>';
	}

	escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}
}
