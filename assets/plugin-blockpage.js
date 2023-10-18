import { BlockPageAutoRecover } from "./js/util/BlockPageAutoRecover";
import { BlockPageMagicLink } from "./js/util/BlockPageMagicLink";
import { ShieldServicesPlugin } from "./js/util/ShieldServicesPlugin";

window.addEventListener( 'load', () => {
	global.shieldServices = ShieldServicesPlugin.Instance();

	( new BlockPageAutoRecover() ).exec();
	new BlockPageMagicLink( window.shield_vars_unblock );
}, false );