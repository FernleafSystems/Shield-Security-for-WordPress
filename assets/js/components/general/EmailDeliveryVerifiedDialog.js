import { BaseComponent } from "../BaseComponent";

/**
 * PHP EmailDeliveryVerifiedDialog owns this payload.
 * @typedef {Object} EmailDeliveryVerifiedConfig
 * @property {string} title
 * @property {string} message
 * @property {string} icon_class
 * @property {{ok: string}} strings
 */

/**
 * Opens on the page the emailed verification link opens, when that link has just verified email delivery.
 */
export class EmailDeliveryVerifiedDialog extends BaseComponent {

	init() {
		/** @type {EmailDeliveryVerifiedConfig} */
		const config = this._base_data;
		shieldServices.dialog().message( {
			title: config.title,
			message: config.message,
			iconClass: config.icon_class,
			confirmLabel: config.strings.ok,
			showTitle: true,
		} );
	}
}
