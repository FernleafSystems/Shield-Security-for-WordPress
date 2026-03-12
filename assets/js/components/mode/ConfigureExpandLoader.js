import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ConfigureExpandLoader extends BaseAutoExecComponent {

	canRun() {
		return !!this._base_data?.ajax?.offcanvas_zone_component_config;
	}

	run() {
		this.ajaxBase = this._base_data.ajax.offcanvas_zone_component_config;
		this.batchRequestData = this._base_data.ajax.batch_requests || {};

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

		this.initializeCurrentRoot();
	}

	initializeCurrentRoot() {
		this.rootEl = this.getConfigureRoot();
		if ( this.rootEl !== null ) {
			this.preloadExpansionForms();
		}
	}

	getConfigureRoot() {
		return document.querySelector( '[data-configure-landing="1"]' );
	}

	handleExpansionOpened( expansion ) {
		if ( this.expansionHasLoadedForm( expansion ) ) {
			this.setSaveButtonDisabled( expansion, false );
			UiContentActivator.activateCurrentSubtree( expansion );
			return;
		}

		const placeholder = this.getExpansionPlaceholder( expansion );
		if ( placeholder === null ) {
			return;
		}

		if ( this.isPlaceholderLoading( placeholder ) ) {
			this.showExpansionLoadingState( expansion, placeholder );
			return;
		}

		this.requestPlaceholderLoad( placeholder, {
			showLoading: true
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
		if ( expansion?.classList.contains( 'show' ) ) {
			UiContentActivator.activateCurrentSubtree( expansion );
		}
	}

	preloadExpansionForms() {
		const rootEl = this.rootEl;
		if ( rootEl === null || ObjectOps.IsEmpty( this.batchRequestData ) ) {
			return;
		}
		if ( rootEl.dataset.configurePreloadStarted === '1' ) {
			return;
		}

		const placeholders = this.getPreloadablePlaceholders( rootEl );
		if ( placeholders.length < 1 ) {
			return;
		}

		rootEl.dataset.configurePreloadStarted = '1';
		const batch = new AjaxBatchService( this.batchRequestData );

		placeholders.forEach( ( placeholder, index ) => {
			const requestData = this.preparePlaceholderRequest( placeholder );
			if ( ObjectOps.IsEmpty( requestData ) ) {
				return;
			}

			const expansion = this.getExpansionFromPlaceholder( placeholder );
			batch.add( {
				id: this.buildBatchItemID( placeholder, index ),
				request: requestData,
				onSuccess: ( result ) => this.handleBatchSuccess( placeholder, expansion, result ),
				onError: () => this.handleBatchFailure( placeholder, expansion ),
			} );
		} );

		batch.flush()
		.finally( () => {
			delete rootEl.dataset.configurePreloadStarted;
		} );
	}

	getPreloadablePlaceholders( root ) {
		return [ ...root.querySelectorAll( '[data-configure-expand-ajax="1"]' ) ].filter( ( placeholder ) => {
			return !this.isPlaceholderLoading( placeholder )
				&& !this.expansionHasLoadedForm( this.getExpansionFromPlaceholder( placeholder ) )
				&& !ObjectOps.IsEmpty( this.buildRequestData( placeholder ) );
		} );
	}

	buildBatchItemID( placeholder, index ) {
		const expansionId = this.getExpansionFromPlaceholder( placeholder )?.id?.trim() || '';
		if ( expansionId.length > 0 ) {
			return expansionId;
		}

		const zoneComponentSlug = ( placeholder.dataset.zoneComponentSlug || '' ).trim();
		return `configure-expand-${zoneComponentSlug || 'item'}-${index}`;
	}

	requestPlaceholderLoad( placeholder, {
		showLoading = false
	} = {} ) {
		const requestData = this.preparePlaceholderRequest( placeholder );
		if ( ObjectOps.IsEmpty( requestData ) ) {
			return;
		}

		const expansion = this.getExpansionFromPlaceholder( placeholder );
		if ( showLoading && expansion !== null ) {
			this.showExpansionLoadingState( expansion, placeholder );
		}

		( new AjaxService() )
		.send( requestData, false, true )
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

	preparePlaceholderRequest( placeholder ) {
		if ( this.isPlaceholderLoading( placeholder ) ) {
			return {};
		}

		const expansion = this.getExpansionFromPlaceholder( placeholder );
		if ( this.expansionHasLoadedForm( expansion ) ) {
			return {};
		}

		const requestData = this.buildRequestData( placeholder );
		if ( ObjectOps.IsEmpty( requestData ) ) {
			return {};
		}

		placeholder.dataset.configureExpandLoading = '1';
		this.setSaveButtonDisabled( expansion, true );
		return requestData;
	}

	buildRequestData( placeholder ) {
		const componentData = {};

		for ( const key in placeholder.dataset ) {
			if ( key !== 'configureExpandAjax' && key !== 'configureExpandLoading' ) {
				componentData[ key ] = placeholder.dataset[ key ];
			}
		}

		componentData.form_context = 'expansion';
		return ObjectOps.Merge(
			ObjectOps.ObjClone( this.ajaxBase ),
			componentData
		);
	}

	handleBatchSuccess( placeholder, expansion, result ) {
		const html = result?.data?.html || '';
		if ( result?.success && typeof html === 'string' && html.length > 0 ) {
			this.injectForm( placeholder, html, expansion );
			return;
		}
		this.handleBatchFailure( placeholder, expansion );
	}

	handleBatchFailure( placeholder, expansion ) {
		delete placeholder.dataset.configureExpandLoading;
		this.setSaveButtonDisabled( expansion, true );

		if ( expansion !== null && expansion.classList.contains( 'show' ) ) {
			this.renderLoadFailure( placeholder, expansion, 'Unable to load these settings. Please try again.' );
		}
	}

	renderLoadFailure( placeholder, expansion, message ) {
		delete placeholder.dataset.configureExpandLoading;
		placeholder.innerHTML = `<div class="alert alert-warning mb-0">${this.escapeHtml( message )}</div>`;
		this.setSaveButtonDisabled( expansion, true );
	}

	showExpansionLoadingState( expansion, placeholder ) {
		placeholder.innerHTML = this.buildLoadingMarkup();
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
		if ( !( expansion instanceof Element ) ) {
			return;
		}

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

	getExpansionPlaceholder( expansion ) {
		return expansion.querySelector( '[data-configure-expand-ajax="1"]' );
	}

	getExpansionFromPlaceholder( placeholder ) {
		return placeholder.closest( '[data-shield-expand-body="1"]' );
	}

	expansionHasLoadedForm( expansion ) {
		return expansion instanceof Element && expansion.querySelector( 'form.options_form_for' ) !== null;
	}

	isPlaceholderLoading( placeholder ) {
		return placeholder.dataset.configureExpandLoading === '1';
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
