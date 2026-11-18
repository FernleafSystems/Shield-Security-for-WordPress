import { Tooltip } from 'bootstrap';
import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { focusElement } from "../ui/ShieldA11y";

/**
 * PHP EmailDeliveryVerificationDialog owns this payload.
 * @typedef {Object} EmailDelivery
 * @property {'sent'|'expired'|'unsent'} tone
 * @property {string} icon_class
 * @property {string} detail
 *
 * @typedef {Object} EmailDeliveryVerifyConfig
 * @property {string} title
 * @property {string} description
 * @property {string} icon_class
 * @property {string} address
 * @property {string} address_source
 * @property {EmailDelivery} delivery
 * @property {Record<string, string>} strings
 * @property {{send: Record<string, unknown>, disable: Record<string, unknown>, confirm: Record<string, unknown>}} actions
 */

/**
 * Opens on every Shield page while email 2FA is switched on but email delivery isn't verified. Closing it only hides
 * it on this page: it comes back until the emailed link is clicked, delivery is confirmed here, or email 2FA is
 * turned off. The last two reload the page, as does a resend once there's nothing left to verify.
 */
export class EmailDeliveryVerifyDialog extends BaseComponent {

	init() {
		/** @type {EmailDeliveryVerifyConfig} */
		const config = this._base_data;
		const s = config.strings;

		const content = element( 'div', 'shield-email-verify' );

		const delivery = element( 'div', 'shield-email-verify__delivery' );
		const deliveryIcon = element( 'i' );
		deliveryIcon.setAttribute( 'aria-hidden', 'true' );
		const address = element( 'div', 'shield-email-verify__address', config.address );
		const source = element( 'button', 'shield-email-verify__source' );
		source.type = 'button';
		source.setAttribute( 'aria-label', s.address_source_label );
		source.dataset.bsTitle = config.address_source;
		const sourceIcon = element( 'i', 'bi bi-info-circle' );
		sourceIcon.setAttribute( 'aria-hidden', 'true' );
		source.append( sourceIcon );
		address.append( source );
		const detail = element( 'div', 'shield-email-verify__detail' );
		detail.setAttribute( 'role', 'status' );
		const text = element( 'div' );
		text.append( address, detail );
		const resend = button( 'button shield-email-verify__resend', s.resend );
		delivery.append( deliveryIcon, text, resend );

		const skip = element( 'details', 'shield-email-verify__skip' );
		const setting = element( 'label', 'shield-accessible-dialog__setting form-check form-switch' );
		const sure = element( 'input', 'form-check-input' );
		sure.type = 'checkbox';
		sure.setAttribute( 'role', 'switch' );
		setting.append( sure, element( 'span', '', s.skip_setting ) );
		const risk = element( 'p', 'shield-email-verify__risk' );
		risk.id = 'ShieldEmailVerifyRisk';
		risk.append( element( 'strong', '', s.risk_title ), ` ${s.risk_text}` );
		const confirm = button( 'button shield-email-verify__confirm', s.skip_confirm );
		confirm.disabled = true;
		// Tabbing from the switch to the button skips the warning between them, so both are described by it.
		sure.setAttribute( 'aria-describedby', risk.id );
		confirm.setAttribute( 'aria-describedby', risk.id );
		const summary = element( 'summary', '', s.skip_summary );
		const chevron = element( 'i', 'bi bi-chevron-right' );
		chevron.setAttribute( 'aria-hidden', 'true' );
		summary.prepend( chevron );
		skip.append( summary, setting, risk, confirm );

		const error = element( 'p', 'shield-accessible-dialog__validation' );
		error.setAttribute( 'role', 'alert' );
		error.hidden = true;
		content.append( delivery, skip, error );

		const footer = element( 'div', 'shield-accessible-dialog__footer shield-email-verify__footer' );
		const turnOff = button( 'button-link shield-email-verify__turn-off', s.turn_off );
		const close = button( 'button', s.close );
		footer.append( turnOff, close );

		/** @param {EmailDelivery} state */
		const showDelivery = ( state ) => {
			delivery.dataset.tone = state.tone;
			deliveryIcon.className = state.icon_class;
			detail.textContent = state.detail;
		};
		showDelivery( config.delivery );

		const handle = shieldServices.dialog().content( {
			title: config.title, message: config.description, iconClass: config.icon_class, content, footer,
		} );
		if ( handle === null ) {
			return;
		}
		// Below the address, so it doesn't cover the title.
		const tooltip = new Tooltip( source, { container: content, placement: 'bottom' } );
		// While it shows, Escape closes only the tooltip. The dialog catches Escape on its way down, so this listens
		// above it.
		const hideTooltipOnEscape = ( evt ) => {
			if ( evt.key === 'Escape' ) {
				evt.stopPropagation();
				tooltip.hide();
			}
		};
		source.addEventListener( 'shown.bs.tooltip', () => window.addEventListener( 'keydown', hideTooltipOnEscape, true ) );
		source.addEventListener( 'hidden.bs.tooltip', () => window.removeEventListener( 'keydown', hideTooltipOnEscape, true ) );
		handle.closed.then( () => {
			window.removeEventListener( 'keydown', hideTooltipOnEscape, true );
			tooltip.dispose();
		} );

		const controls = [ resend, sure, confirm, turnOff, close ];
		const setBusy = ( busy ) => {
			handle.setBusy( busy );
			controls.forEach( ( control ) => control.disabled = busy );
			if ( !busy ) {
				confirm.disabled = !sure.checked;
			}
		};

		/**
		 * Runs an action. Keep Close available while the page reloads in case navigation is cancelled.
		 * @returns {Promise<any>} the response, or null when the request failed
		 */
		const run = async ( action, quiet ) => {
			setBusy( true );
			error.hidden = true;
			const response = await ( new AjaxService() ).send( action, false, quiet );
			const answered = typeof response?.success === 'boolean';
			if ( answered && response.data.page_reload ) {
				handle.setBusy( false );
				close.disabled = false;
				focusElement( close );
				return response;
			}
			setBusy( false );
			if ( !answered ) {
				error.textContent = s.error;
				error.hidden = false;
			}
			return answered ? response : null;
		};

		sure.addEventListener( 'change', () => confirm.disabled = !sure.checked );
		close.addEventListener( 'click', () => handle.close() );
		turnOff.addEventListener( 'click', () => run( config.actions.disable, false ) );
		confirm.addEventListener( 'click', () => run( config.actions.confirm, false ) );
		resend.addEventListener( 'click', async () => {
			resend.textContent = s.sending;
			const response = await run( config.actions.send, true );
			if ( response?.data.page_reload ) {
				return;
			}
			resend.textContent = s.resend;
			if ( response !== null ) {
				// The action always says where the new send stands. A request refused before reaching it (such as
				// an expired Security Admin session) carries only a message, shown here as the resend is quiet.
				if ( response.data.delivery ) {
					showDelivery( response.data.delivery );
				}
				else {
					error.textContent = response.data.message || s.error;
					error.hidden = false;
				}
			}
			focusElement( resend );
		} );
	}
}

/**
 * @param {string} tag
 * @param {string} [className]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
function element( tag, className = '', text = '' ) {
	const el = document.createElement( tag );
	if ( className !== '' ) {
		el.className = className;
	}
	if ( text !== '' ) {
		el.textContent = text;
	}
	return el;
}

/**
 * @param {string} className
 * @param {string} text
 * @returns {HTMLButtonElement}
 */
function button( className, text ) {
	const el = /** @type {HTMLButtonElement} */ ( element( 'button', className, text ) );
	el.type = 'button';
	return el;
}
