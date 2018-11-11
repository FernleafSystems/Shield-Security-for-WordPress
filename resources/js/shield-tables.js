jQuery.fn.icwpWpsfTableWithFilter = function ( aOptions ) {

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
		aOpts[ 'table' ].renderTableFromForm( $oForm );
	};

	var submitFilters = function ( evt ) {
		evt.preventDefault();
		aOpts[ 'table' ].renderTableFromForm( $oForm );
		return false;
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oForm = jQuery( aOpts[ 'selector_filter_form' ] );
			$oForm.on( 'submit', submitFilters );
			$oForm.on( 'click', 'a#ClearForm', resetFilters );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	var $oForm;
	initialise();

	return this;
};