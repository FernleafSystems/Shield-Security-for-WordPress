import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

/** TODO: https://vanillajstoolkit.com/polyfills/matches/ */
export class PluginBadge extends BaseComponent {

	init() {
		const close = document.getElementById( 'icwpWpsfCloseButton' ) || false;
		if ( close ) {
			close.addEventListener( 'click', () =>
				( new AjaxService() )
				.send( this._base_data.ajax.plugin_badge_close, false, true )
				.finally( () => document.querySelector( '.icwp_wpsf_site_badge.floating_badge' ).remove() ) );
		}
	}
}

/*
PluginBadge.prototype.handleEvent = function ( evt ) {
	if ( evt.type === 'click' ) {
		( new AjaxService() )
		.send( this._base_data.ajax.plugin_badge_close, false, true )
		.finally( () => document.querySelector( '.icwp_wpsf_site_badge.floating_badge' ).remove() );
	}
};
 */