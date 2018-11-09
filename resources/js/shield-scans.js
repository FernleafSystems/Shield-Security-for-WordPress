/**
 * Important Params:
 * @param aOptions
 * @returns {jQuery}
 */
jQuery.fn.icwpWpsfScans = function ( aOptions ) {

	var startScan = function ( evt ) {
		evt.preventDefault();
		// init scan
		// init poll
		poll();
		return false;
	};

	var poll = function () {
		setTimeout( function () {

			jQuery.post( ajaxurl, {},
				function ( oResponse ) {
					if ( oResponse.data.success ) {
						// process poll results
						poll();
					}
					else {
					}

				}
			).always( function () {
				}
			);
		}, aOpts[ 'poll_interval' ] );
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', startScan );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend(
		{
			'poll_interval': 10000
		},
		aOptions
	);
	initialise();

	return this;
};