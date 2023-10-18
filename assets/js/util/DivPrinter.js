export class DivPrinter {
	print( divID ) {
		let divContents = document.getElementById( divID ).innerHTML;
		let a = window.open( '', '', 'height=800, width=800' );
		a.document.write( `<html lang=en><body>${divContents}</body></html>` );
		a.print();
	};
}