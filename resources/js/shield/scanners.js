/**
 * https://css-tricks.com/snippets/jquery/jquery-plugin-template/
 */
(function ( $, window, document, undefined ) {

	$.fn.icwpWpsfScanResultsActions = function ( runtimeOptions ) {
		return this.each(
			function () {
				new $.icwpWpsfScanResultsActions( this, runtimeOptions )
			}
		);
	};

	$.icwpWpsfScanResultsActions = function ( el, options ) {
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		var base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $( el );
		base.el = el;

		// Add a reverse reference to the DOM object
		base.$el.data( "icwpWpsfScanResultsActions", base );

		base.init = function () {
			base.options = $.extend( {}, $.icwpWpsfScanResultsActions.defaultOptions, options );
			base.bindEvents();
		};

		base.bindEvents = function () {

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.standalone-action.ignore',
				function ( evt ) {
					evt.preventDefault();
					base.bulkAction.call( base, 'ignore', [ $( this ).data( 'rid' ) ] );
				}
			);

		};

		base.bulkAction = function ( action, RIDs ) {
			let reqData = this.getBaseAjaxData();
			reqData[ 'sub_action' ] = action;
			reqData[ 'rids' ] = RIDs;
			this.sendReq( reqData );
		};

		base.sendReq = function ( reqData, ) {

			$( 'html' ).css( 'cursor', 'wait' );

			$.post( ajaxurl, reqData,
				function ( response ) {

					if ( response.success ) {
						iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
						if ( response.data.table_reload ) {
						}
						else {
							iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
						}
					}
					else {
						let msg = 'Communications error with site.';
						if ( response.data.message !== undefined ) {
							msg = response.data.message;
						}
						alert( msg );
					}
				}
			).always( function () {
					$( "html" ).css( "cursor", 'initial' );
				}
			);
		};

		base.getBaseAjaxData = function () {
			return JSON.parse( JSON.stringify( base.options.ajax[ 'scanresults_action' ] ) );
		};

		// Run initializer
		base.init();
	}

	$.icwpWpsfScanResultsActions.defaultOptions = {};

})( jQuery );