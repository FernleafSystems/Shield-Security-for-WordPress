import { BlockPageAutoRecover } from "./components/block/BlockPageAutoRecover";
import { BlockPageMagicLink } from "./components/block/BlockPageMagicLink";

window.addEventListener( 'load', () => {
	new BlockPageAutoRecover();
	if ( 'shield_vars_blockpage' in window ) {
		const blockpage_vars = window.shield_vars_blockpage;
		( 'magic_unblock' in blockpage_vars ) && new BlockPageMagicLink( blockpage_vars.magic_unblock )
	}
}, false );
