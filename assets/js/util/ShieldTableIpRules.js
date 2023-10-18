import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableIpRules extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-IpRules';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.language.search = "Search IP";
		return cfg;
	}

	/** https://datatables.net/forums/discussion/comment/164708/#Comment_164708 **/
	// TODO: $.fn.dataTable.Debounce = function ( table, options ) {
	// 	let tableId = table.settings()[ 0 ].sTableId;
	// 	$( '.dataTables_filter input[aria-controls="' + tableId + '"]' ) // select the correct input field
	// 	.unbind() // Unbind previous default bindings
	// 	.bind( 'input', (delay( function ( e ) { // Bind our desired behavior
	// 		table.search( $( this ).val() ).draw();
	// 	}, 600 )) ); // Set delay in milliseconds
	// }
}