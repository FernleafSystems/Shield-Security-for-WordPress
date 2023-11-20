export class DivPrinter {

	constructor() {
		shieldEventsHandler_Main.add_Click( '.shield_div_print', ( targetEl ) => this.print( targetEl.dataset ) );
	}

	print( params ) {
		const el = document.querySelector( params.selector );
		if ( el ) {
			const height = 'height' in params ? params.height : 800;
			const width = 'width' in params ? params.width : 800;
			const w = window.open( '', '', `height=${height}, width=${width}` );
			w.document.write( `<html lang=en><body>${el.innerHTML}</body></html>` );
			w.print();
		}
	};
}