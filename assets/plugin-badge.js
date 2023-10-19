import { PluginBadge } from "./js/util/PluginBadge";

window.addEventListener( 'load', () => {
	( 'shield_vars_badge' in window ) && new PluginBadge( window.shield_vars_badge.comps.badge );
}, false );