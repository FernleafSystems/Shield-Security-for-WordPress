import { PluginBadge } from "./js/util/PluginBadge";

window.addEventListener( 'load', () => {
	if ( typeof window.shield_vars_badge !== 'undefined' ) {
		new PluginBadge( window.shield_vars_badge.comps.badge );
	}
}, false );