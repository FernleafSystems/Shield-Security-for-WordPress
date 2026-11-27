import { AjaxService } from '../services/AjaxService';
import { focusElement } from '../ui/ShieldA11y';

/**
 * PHP ProtectionEnableDialogBuilder owns this payload.
 * @typedef {Object} ProtectionEnableSetting
 * @property {string} key
 * @property {string} label
 * @property {'off'|'on'|'locked'} state off: can be turned on; on: already is; locked: outside the site's plan
 * @property {boolean} is_required nothing is saved while this one is off; at most one setting is
 * @property {boolean} is_nested belongs to the required setting above it
 *
 * @typedef {Object} ProtectionEnableDialogConfig
 * @property {string} title
 * @property {string} description
 * @property {string} icon_class
 * @property {string} notice
 * @property {ProtectionEnableSetting[]} settings
 * @property {string} locked_label
 * @property {Record<string, unknown>} action
 * @property {string} path
 * @property {string} save_label
 * @property {string} cancel_label
 * @property {string} saving_label
 * @property {string} error_message
 */

/**
 * Any element carrying a dialog payload in data-enable-protection opens the dialog, wherever it sits. Once
 * the protection is on, the launcher raises 'shield:protection-enabled' so whatever holds it can refresh;
 * a change that affects the whole page reloads it from the action's own response.
 */
export class ProtectionEnableLaunchers {

	static EVENT = 'shield:protection-enabled';

	constructor() {
		shieldEventsHandler_Main.add_Click( '[data-enable-protection]', async ( launcher ) => {
			if ( launcher instanceof HTMLElement
				&& await openProtectionEnableDialog( JSON.parse( launcher.dataset.enableProtection ), launcher ) ) {
				launcher.dispatchEvent( new CustomEvent( ProtectionEnableLaunchers.EVENT, { bubbles: true } ) );
			}
		}, false );
	}
}

/**
 * @param {ProtectionEnableDialogConfig} config
 * @param {HTMLElement} launcher
 * @returns {Promise<boolean>}
 */
async function openProtectionEnableDialog( config, launcher ) {
	const content = document.createElement( 'div' );
	if ( config.path !== '' ) {
		const path = document.createElement( 'p' );
		path.className = 'shield-accessible-dialog__path';
		path.textContent = config.path;
		content.appendChild( path );
	}
	const notice = config.notice === '' ? null : document.createElement( 'p' );
	if ( notice ) {
		notice.className = 'shield-accessible-dialog__notice';
		notice.textContent = config.notice;
		content.appendChild( notice );
	}

	const list = document.createElement( 'div' );
	list.className = 'shield-accessible-dialog__settings';
	const upgradeButtons = [];
	const switches = config.settings.map( ( setting ) => {
		const isLocked = setting.state === 'locked';
		// A locked setting has a button of its own, which a label would also set off.
		const row = document.createElement( isLocked ? 'div' : 'label' );
		row.className = 'shield-accessible-dialog__setting form-check form-switch';
		row.classList.toggle( 'is-nested', setting.is_nested );
		row.classList.toggle( 'is-locked', isLocked );
		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.className = 'form-check-input';
		checkbox.setAttribute( 'role', 'switch' );
		checkbox.setAttribute( 'aria-label', setting.label );
		checkbox.checked = setting.state === 'on';
		checkbox.disabled = setting.state !== 'off';
		const label = document.createElement( 'span' );
		label.textContent = setting.label;
		row.append( checkbox, label );
		if ( isLocked ) {
			// Opens the same "what Pro adds" dialog as the plugin's other upgrade prompts, once this one is out of its way.
			const upgrade = document.createElement( 'button' );
			upgrade.type = 'button';
			upgrade.className = 'shield-pro-only-button';
			upgrade.dataset.proUpsell = '1';
			upgrade.setAttribute( 'aria-haspopup', 'dialog' );
			upgrade.setAttribute( 'aria-label', `${config.locked_label}: ${setting.label}` );
			const icon = document.createElement( 'i' );
			icon.className = 'bi bi-lock-fill';
			icon.setAttribute( 'aria-hidden', 'true' );
			upgrade.append( icon, config.locked_label );
			row.appendChild( upgrade );
			upgradeButtons.push( upgrade );
		}
		list.appendChild( row );
		return { setting, checkbox };
	} );
	content.appendChild( list );

	const error = document.createElement( 'p' );
	error.className = 'shield-accessible-dialog__validation';
	error.setAttribute( 'role', 'alert' );
	content.appendChild( error );
	const footer = document.createElement( 'div' );
	footer.className = 'shield-accessible-dialog__footer';
	const cancel = document.createElement( 'button' );
	cancel.type = 'button';
	cancel.className = 'button';
	cancel.textContent = config.cancel_label;
	const save = document.createElement( 'button' );
	save.type = 'button';
	save.className = 'button button-primary';
	save.textContent = config.save_label;
	footer.append( cancel, save );

	const canBeTurnedOn = switches.filter( ( { setting } ) => setting.state === 'off' );
	const required = canBeTurnedOn.find( ( { setting } ) => setting.is_required );
	// The notice says why the required setting matters, so it is read out with that switch.
	if ( required && notice ) {
		notice.id = 'ShieldProtectionEnableNotice';
		required.checkbox.setAttribute( 'aria-describedby', notice.id );
	}
	const keysTurnedOn = () => canBeTurnedOn.filter( ( { checkbox } ) => checkbox.checked ).map( ( { setting } ) => setting.key );
	const canSave = () => keysTurnedOn().length > 0 && ( !required || required.checkbox.checked );
	const setControlsDisabled = ( disabled ) => {
		cancel.disabled = disabled;
		canBeTurnedOn.forEach( ( { checkbox } ) => checkbox.disabled = disabled );
		upgradeButtons.forEach( ( button ) => button.disabled = disabled );
		save.disabled = disabled || !canSave();
	};
	save.disabled = !canSave();

	const handle = shieldServices.dialog().content( {
		title: config.title, message: config.description, iconClass: config.icon_class,
		content, footer, launcher,
	} );
	if ( handle === null ) return false;
	let saved = false;
	cancel.addEventListener( 'click', () => handle.close() );
	upgradeButtons.forEach( ( button ) => button.addEventListener( 'click', () => handle.close() ) );
	canBeTurnedOn.forEach( ( { setting, checkbox } ) => checkbox.addEventListener( 'change', () => {
		// Turning on a setting that belongs to the required one turns that on too: it does nothing without it.
		if ( required && setting.is_nested && checkbox.checked ) {
			required.checkbox.checked = true;
		}
		save.disabled = !canSave();
	} ) );
	save.addEventListener( 'click', async () => {
		handle.setBusy( true );
		setControlsDisabled( true );
		save.textContent = config.saving_label;
		error.textContent = '';
		try {
			const response = await ( new AjaxService() ).send( { ...config.action, enable: keysTurnedOn() }, false, true );
			saved = response?.success === true;
			if ( !saved ) error.textContent = config.error_message;
		}
		catch ( e ) {
			error.textContent = config.error_message;
		}
		finally {
			handle.setBusy( false );
			setControlsDisabled( false );
			save.textContent = config.save_label;
		}
		if ( saved ) handle.close();
		else focusElement( save );
	} );
	await handle.closed;
	return saved;
}
