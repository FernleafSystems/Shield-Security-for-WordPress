import A11yDialog from 'a11y-dialog';
import { focusElement } from './ShieldA11y';

const DEFAULT_OPTIONS = {
	id: 'ShieldAccessibleDialog',
	titleId: 'ShieldAccessibleDialogTitle',
	messageId: 'ShieldAccessibleDialogMessage',
	inputId: 'ShieldAccessibleDialogInput',
	inputLabelId: 'ShieldAccessibleDialogInputLabel',
	validationId: 'ShieldAccessibleDialogValidation',
	datasetKey: 'shieldAccessibleDialog',
	classPrefix: 'shield-accessible-dialog',
	stringsProvider: () => ( {} ),
	fallbackFocus: () => null,
	errorContext: 'Accessible dialog',
	alertTitleKeys: [ 'dialog_alert_title' ],
	confirmTitleKeys: [ 'dialog_confirm_title' ],
	promptTitleKeys: [ 'dialog_prompt_title', 'prompt_title' ],
	processingTitleKeys: [ 'loading', 'dialog_alert_title' ],
	alertConfirmLabelKeys: [ 'close', 'continue' ],
	actionConfirmLabelKeys: [ 'confirm' ],
	cancelLabelKeys: [ 'cancel' ],
};

export function resolveAccessibleDialogLauncher( event = null, node = null ) {
	if ( event?.currentTarget instanceof HTMLElement ) {
		return event.currentTarget;
	}
	if ( node?.[ 0 ] instanceof HTMLElement ) {
		return node[ 0 ];
	}
	if ( node instanceof HTMLElement ) {
		return node;
	}
	if ( typeof node?.get === 'function' && node.get( 0 ) instanceof HTMLElement ) {
		return node.get( 0 );
	}
	return null;
}

export function resolveAccessibleDialogConfirmLabel( launcher = null ) {
	if ( !( launcher instanceof HTMLElement ) ) {
		return '';
	}

	return [
		launcher.getAttribute( 'aria-label' ),
		launcher.getAttribute( 'title' ),
		launcher.textContent,
	]
	.map( ( label ) => normalizeText( label ) )
	.find( ( label ) => label.length > 0 ) || '';
}

export class AccessibleDialog {

	constructor( options = {} ) {
		this.options = {
			...DEFAULT_OPTIONS,
			...options,
		};
		this.dialogInstance = null;
		this.dialogEl = null;
		this.pendingResolver = null;
		this.pendingLauncher = null;
		this.pendingResult = null;
		this.currentConfig = null;
		this.allowProcessingClose = false;
	}

	confirm( config = {} ) {
		return this.show( {
			...config,
			type: 'confirm',
		}, false );
	}

	message( config = {} ) {
		return this.show( {
			...config,
			type: 'alert',
		}, undefined );
	}

	prompt( config = {} ) {
		return this.show( {
			...config,
			type: 'prompt',
		}, null );
	}

	processing( config = {} ) {
		if ( this.pendingResolver !== null ) {
			return { close: () => null };
		}

		this.show( {
			...config,
			type: 'processing',
		}, undefined );

		let closed = false;
		return {
			close: () => {
				if ( closed || this.currentConfig?.type !== 'processing' ) {
					return;
				}
				closed = true;
				this.allowProcessingClose = true;
				this.dialogInstance.hide();
				this.allowProcessingClose = false;
			},
		};
	}

	show( config, cancelValue ) {
		if ( this.pendingResolver !== null ) {
			return Promise.resolve( cancelValue );
		}

		const { dialog } = this.ensureDialog();
		config = this.normalizeDialogConfig( config );
		this.pendingLauncher = config.launcher instanceof HTMLElement ? config.launcher : document.activeElement;
		this.pendingResult = cancelValue;
		this.configureDialog( config );

		return new Promise( ( resolve ) => {
			this.pendingResolver = resolve;
			dialog.show();
		} );
	}

	ensureDialog() {
		if ( this.dialogInstance !== null && this.dialogEl !== null ) {
			return { dialog: this.dialogInstance, element: this.dialogEl };
		}

		const {
			id,
			titleId,
			messageId,
			inputId,
			inputLabelId,
			validationId,
			datasetKey,
			classPrefix,
		} = this.options;

		this.dialogEl = document.createElement( 'div' );
		this.dialogEl.id = id;
		if ( datasetKey.length > 0 ) {
			this.dialogEl.dataset[ datasetKey ] = '1';
		}
		this.dialogEl.setAttribute( 'aria-hidden', 'true' );
		this.dialogEl.setAttribute( 'aria-labelledby', titleId );
		this.dialogEl.innerHTML = `
			<div class="${classPrefix}__overlay" data-a11y-dialog-hide></div>
			<div class="${classPrefix}__surface" role="document">
				<h2 id="${titleId}" class="${classPrefix}__title"></h2>
				<div id="${messageId}" class="${classPrefix}__message"></div>
				<div class="${classPrefix}__field" hidden>
					<label id="${inputLabelId}" for="${inputId}"></label>
					<input id="${inputId}" type="text" autocomplete="off" />
				</div>
				<div id="${validationId}" class="${classPrefix}__validation" role="alert" hidden></div>
				<div class="${classPrefix}__actions">
					<button type="button" class="button ${classPrefix}__cancel" data-a11y-dialog-hide></button>
					<button type="button" class="button button-primary ${classPrefix}__confirm"></button>
				</div>
			</div>
		`;
		document.body.appendChild( this.dialogEl );

		this.dialogInstance = new A11yDialog( this.dialogEl );
		this.dialogInstance.on( 'hide', ( evt ) => this.onHide( evt ) );

		this.dialogEl.querySelector( `.${classPrefix}__confirm` ).addEventListener( 'click', () => {
			this.submitCurrentDialog();
		} );
		this.dialogEl.querySelector( `#${inputId}` ).addEventListener( 'keydown', ( evt ) => {
			if ( evt.key === 'Enter' ) {
				evt.preventDefault();
				this.submitCurrentDialog();
			}
		} );

		return { dialog: this.dialogInstance, element: this.dialogEl };
	}

	onHide( evt = null ) {
		if ( this.currentConfig?.type === 'processing' && !this.allowProcessingClose ) {
			evt?.preventDefault();
			return;
		}

		const resolver = this.pendingResolver;
		const result = this.pendingResult;
		const launcher = this.pendingLauncher;
		this.pendingResolver = null;
		this.pendingResult = null;
		this.pendingLauncher = null;
		this.currentConfig = null;
		this.resetProcessingState();

		window.setTimeout( () => {
			if ( resolver ) {
				resolver( result );
			}
			this.restoreFocus( launcher );
		}, 0 );
	}

	restoreFocus( launcher ) {
		if ( isFocusableLauncher( launcher ) && focusElement( launcher ) ) {
			return;
		}

		const fallback = this.options.fallbackFocus();
		if ( fallback instanceof HTMLElement ) {
			focusElement( fallback );
		}
	}

	normalizeDialogConfig( config ) {
		const type = [ 'alert', 'confirm', 'prompt', 'processing' ].includes( config.type ) ? config.type : 'alert';
		const titleFallbacks = {
			alert: this.localizedText( this.options.alertTitleKeys, 'Notice' ),
			confirm: this.localizedText( this.options.confirmTitleKeys, 'Confirm Action' ),
			prompt: this.localizedText( this.options.promptTitleKeys, 'Information Required' ),
			processing: this.localizedText( this.options.processingTitleKeys, 'Loading' ),
		};

		const normalized = {
			...config,
			type,
			title: normalizeText( config.title || titleFallbacks[ type ] ),
			message: normalizeText( config.message ),
			label: type === 'prompt' ? normalizeText( config.label ) : '',
			confirmLabel: type === 'processing' ? '' : normalizeText(
				config.confirmLabel || this.localizedText(
					type === 'alert' ? this.options.alertConfirmLabelKeys : this.options.actionConfirmLabelKeys,
					type === 'alert' ? 'Close' : 'Confirm'
				)
			),
			cancelLabel: [ 'alert', 'processing' ].includes( type ) ? '' : normalizeText(
				config.cancelLabel || this.localizedText( this.options.cancelLabelKeys, 'Cancel' )
			),
			showTitle: config.showTitle === true || ( config.showTitle !== false && ![ 'alert', 'processing' ].includes( type ) ),
		};

		if ( normalized.title.length < 1 ) {
			throw new Error( `${this.options.errorContext} requires a non-empty title.` );
		}
		if ( type !== 'processing' && normalized.confirmLabel.length < 1 ) {
			throw new Error( `${this.options.errorContext} requires a non-empty title and confirm label.` );
		}
		if ( ![ 'alert', 'processing' ].includes( type ) && normalized.cancelLabel.length < 1 ) {
			throw new Error( `${this.options.errorContext} requires a non-empty cancel label when cancel is visible.` );
		}
		if ( type === 'processing' && normalized.message.length < 1 ) {
			throw new Error( `${this.options.errorContext} requires a non-empty processing message.` );
		}
		if ( type === 'prompt' && normalized.label.length < 1 ) {
			throw new Error( `${this.options.errorContext} requires a non-empty prompt label.` );
		}

		return normalized;
	}

	configureDialog( config ) {
		const { element } = this.ensureDialog();
		const {
			titleId,
			messageId,
			inputId,
			inputLabelId,
			classPrefix,
		} = this.options;

		const isProcessing = config.type === 'processing';
		this.allowProcessingClose = false;
		element.classList.toggle( `${classPrefix}--danger`, config.danger === true );
		element.classList.toggle( `${classPrefix}--processing`, isProcessing );
		if ( isProcessing ) {
			element.setAttribute( 'aria-busy', 'true' );
		}
		else {
			element.removeAttribute( 'aria-busy' );
		}
		const titleEl = this.setText( `#${titleId}`, config.title );
		titleEl.classList.toggle( `${classPrefix}__title--hidden`, config.showTitle !== true );
		this.setText( `#${messageId}`, config.message );
		if ( config.message.length > 0 ) {
			element.setAttribute( 'aria-describedby', messageId );
		}
		else {
			element.removeAttribute( 'aria-describedby' );
		}

		const confirmButton = element.querySelector( `.${classPrefix}__confirm` );
		this.setActionButton( confirmButton, !isProcessing, config.confirmLabel );
		confirmButton.classList.toggle( 'button-primary', config.danger !== true );
		confirmButton.classList.toggle( 'button-link-delete', config.danger === true );

		const cancelButton = element.querySelector( `.${classPrefix}__cancel` );
		this.setActionButton( cancelButton, ![ 'alert', 'processing' ].includes( config.type ), config.cancelLabel );
		element.querySelector( `.${classPrefix}__actions` ).hidden = isProcessing;
		this.resetValidation();

		const field = element.querySelector( `.${classPrefix}__field` );
		const input = element.querySelector( `#${inputId}` );
		const label = element.querySelector( `#${inputLabelId}` );
		if ( config.type === 'prompt' ) {
			field.hidden = false;
			label.textContent = config.label;
			input.value = config.value || '';
			input.setAttribute( 'autofocus', 'autofocus' );
			cancelButton.removeAttribute( 'autofocus' );
		}
		else {
			field.hidden = true;
			input.value = '';
			input.removeAttribute( 'autofocus' );
			this.setActionButton( cancelButton, ![ 'alert', 'processing' ].includes( config.type ), config.cancelLabel, config.danger === true );
		}

		this.currentConfig = config;
	}

	setText( selector, value ) {
		const el = this.dialogEl.querySelector( selector );
		el.textContent = value || '';
		return el;
	}

	setActionButton( button, isVisible, label, shouldAutofocus = false ) {
		button.textContent = isVisible ? label : '';
		button.hidden = !isVisible;
		button.disabled = !isVisible;
		button.toggleAttribute( 'autofocus', isVisible && shouldAutofocus );
		if ( isVisible ) {
			button.removeAttribute( 'aria-hidden' );
		}
		else {
			button.setAttribute( 'aria-hidden', 'true' );
		}
	}

	resetProcessingState() {
		if ( this.dialogEl === null ) {
			return;
		}

		const { classPrefix } = this.options;
		this.dialogEl.classList.remove( `${classPrefix}--processing` );
		this.dialogEl.removeAttribute( 'aria-busy' );
		const actions = this.dialogEl.querySelector( `.${classPrefix}__actions` );
		if ( actions instanceof HTMLElement ) {
			actions.hidden = false;
		}
	}

	resetValidation() {
		const input = this.dialogEl.querySelector( `#${this.options.inputId}` );
		const validation = this.dialogEl.querySelector( `#${this.options.validationId}` );
		input.removeAttribute( 'aria-invalid' );
		input.removeAttribute( 'aria-describedby' );
		validation.hidden = true;
		validation.textContent = '';
	}

	showValidation( message ) {
		const input = this.dialogEl.querySelector( `#${this.options.inputId}` );
		const validation = this.dialogEl.querySelector( `#${this.options.validationId}` );
		input.setAttribute( 'aria-invalid', 'true' );
		input.setAttribute( 'aria-describedby', this.options.validationId );
		validation.hidden = false;
		validation.textContent = message || '';
		focusElement( input );
	}

	submitCurrentDialog() {
		const config = this.currentConfig;
		if ( !config ) {
			return;
		}

		if ( config.type === 'prompt' ) {
			const input = this.dialogEl.querySelector( `#${this.options.inputId}` );
			const value = input.value;
			const validation = typeof config.validate === 'function' ? config.validate( value ) : true;
			if ( validation !== true ) {
				this.showValidation( String( validation || '' ) );
				return;
			}
			this.pendingResult = value;
		}
		else if ( config.type === 'confirm' ) {
			this.pendingResult = true;
		}
		else {
			this.pendingResult = undefined;
		}

		this.dialogInstance.hide();
	}

	localizedText( keys, fallback ) {
		const strings = this.options.stringsProvider() || {};
		return keys
		.map( ( key ) => normalizeText( strings[ key ] ) )
		.find( ( value ) => value.length > 0 ) || fallback;
	}
}

function isFocusableLauncher( element ) {
	return element instanceof HTMLElement
		&& element.isConnected
		&& !element.hasAttribute( 'disabled' )
		&& element.getAttribute( 'aria-hidden' ) !== 'true';
}

function normalizeText( value ) {
	return String( value || '' ).trim();
}
