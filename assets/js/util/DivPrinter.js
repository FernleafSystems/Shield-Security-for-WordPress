export class DivPrinter {

	constructor() {
		document.addEventListener( 'click', ( evt ) => {
			if ( 'classList' in evt.target && evt.target.classList.contains( 'shield_div_print' ) ) {
				this.print( evt.target.dataset );
			}
		}, false );
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