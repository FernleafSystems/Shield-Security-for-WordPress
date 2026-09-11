import { AjaxService } from '../services/AjaxService';
import { focusElement } from '../ui/ShieldA11y';

/**
 * PHP ProtectionEnableDialogBuilder owns this payload, including the optional path value.
 * @typedef {Object} ProtectionEnableDialogConfig
 * @property {string} title
 * @property {string} description
 * @property {string} setting_label
 * @property {string} icon_class
 * @property {Record<string, unknown>} action
 * @property {string} path
 * @property {string} save_label
 * @property {string} cancel_label
 * @property {string} saving_label
 * @property {string} error_message
 */

/**
 * @param {ProtectionEnableDialogConfig} config
 * @param {HTMLElement} launcher
 * @returns {Promise<boolean>}
 */

export async function openProtectionEnableDialog( config, launcher ) {
	const content = document.createElement( 'div' );
	if ( config.path !== '' ) {
		const path = document.createElement( 'p' );
		path.className = 'shield-accessible-dialog__path';
		path.textContent = config.path;
		content.appendChild( path );
	}
	const setting = document.createElement( 'label' );
	setting.className = 'shield-accessible-dialog__setting form-check form-switch';
	const checkbox = document.createElement( 'input' );
	checkbox.type = 'checkbox';
	checkbox.className = 'form-check-input';
	checkbox.setAttribute( 'role', 'switch' );
	const label = document.createElement( 'span' );
	label.textContent = config.setting_label;
	setting.append( checkbox, label );
	content.appendChild( setting );
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
	save.disabled = true;
	footer.append( cancel, save );
	const handle = shieldServices.dialog().content( {
		title: config.title, message: config.description, iconClass: config.icon_class,
		content, footer, launcher,
	} );
	if ( handle === null ) return false;
	let saved = false;
	cancel.addEventListener( 'click', () => handle.close() );
	checkbox.addEventListener( 'change', () => { save.disabled = !checkbox.checked; } );
	save.addEventListener( 'click', async () => {
		handle.setBusy( true );
		save.disabled = cancel.disabled = checkbox.disabled = true;
		save.textContent = config.saving_label;
		error.textContent = '';
		try {
			const response = await ( new AjaxService() ).send( config.action, false, true );
			saved = response?.success === true;
			if ( !saved ) error.textContent = config.error_message;
		}
		catch ( e ) {
			error.textContent = config.error_message;
		}
		finally {
			handle.setBusy( false );
			save.disabled = cancel.disabled = checkbox.disabled = false;
			save.textContent = config.save_label;
		}
		if ( saved ) handle.close();
		else focusElement( save );
	} );
	await handle.closed;
	return saved;
}
