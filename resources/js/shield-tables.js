jQuery.fn.icwpWpsfTableWithFilter = function ( aOptions ) {

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	/**
	 * @var icwpWpsfAjaxTable
	 */
	var $oTable;
	var $oForm;

	initialise();

	function initialise() {
		$oTable = jQuery( aOpts[ 'selector_table_container' ] ).icwpWpsfAjaxTable( aOpts );
		jQuery( document ).ready( function () {
			$oForm = jQuery( aOpts[ 'selector_filter_form' ] );
			$oForm.on( 'submit', submitFilters );
			$oForm.on( 'click', 'a#ClearForm', resetFilters );
		} );
	}

	var resetFilters = function ( evt ) {
		jQuery( 'input[type=text]', $oForm ).each( function () {
			jQuery( this ).val( '' );
		} );
		jQuery( 'select', $oForm ).each( function () {
			jQuery( this ).prop( 'selectedIndex', 0 );
		} );
		jQuery( 'input[type=checkbox]', $oForm ).each( function () {
			jQuery( this ).prop( 'checked', false );
		} );
		$oTable.renderTableFromForm( $oForm );
	};

	var submitFilters = function ( evt ) {
		evt.preventDefault();
		$oTable.renderTableFromForm( $oForm );
		return false;
	};

	return this;
};