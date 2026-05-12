import $ from 'jquery';

export class DataTableVisibilityAdjuster {

	static adjustWithin( contextEl ) {
		if ( contextEl === null || !$.fn.dataTable || !$.fn.dataTable.isDataTable ) {
			return;
		}

		contextEl.querySelectorAll( 'table' ).forEach( ( tableEl ) => {
			if ( $.fn.dataTable.isDataTable( tableEl ) ) {
				$( tableEl ).DataTable().columns.adjust();
			}
		} );
	}

	static adjustWithinNextFrame( contextEl ) {
		window.requestAnimationFrame( () => DataTableVisibilityAdjuster.adjustWithin( contextEl ) );
	}
}
