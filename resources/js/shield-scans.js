/**
 * Add ajax actions to table buttons, and automatically refreshes the table.
 */
;(function ( $, window, document, undefined ) {

	var pluginName = 'icwpWpsfScanResults';

	function Ob_ScanResults( element, options ) {
		this.element = element;
		this._name = pluginName;
		this._defaults = $.fn.icwpWpsfScanResults.defaults;
		this.options = $.extend( {}, this._defaults, options );
		this.init();
	}

	$.extend(
		Ob_ScanResults.prototype,
		{
			init: function () {
				this.buildCache();
				this.bindEvents();
			},
			destroy: function () {
				this.unbindEvents();
				this.$element.removeData();
			},
			buildCache: function () {
				this.$element = $( this.element );
			},
			bindEvents: function () {
				var plugin = this;

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.delete',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.deleteEntry.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.ignore',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.ignoreEntry.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.repair',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.repairEntry.call( plugin );
					}
				);
			},
			unbindEvents: function () {
				/*
					Unbind all events in our plugin's namespace that are attached
					to "this.$element".
				*/
				this.$element.off( '.' + this._name );
			},

			deleteEntry: function () {
				var requestData = this.options[ 'ajax_item_delete' ];
				this.sendReq( requestData );
			},

			ignoreEntry: function () {
				var aRequestData = this.options[ 'ajax_item_ignore' ];
				this.sendReq( aRequestData );
			},

			repairEntry: function () {
				var requestData = this.options[ 'ajax_item_repair' ];
				this.sendReq( requestData );
			},

			sendReq: function ( aRequestData ) {
				iCWP_WPSF_BodyOverlay.show();

				var plugin = this;
				aRequestData[ 'rid' ] = plugin.options[ 'working_rid' ];

				$.post( ajaxurl, $.extend( aRequestData, plugin.options[ 'req_params' ] ),
					function ( oResponse ) {

						if ( oResponse.success ) {
							iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
							if ( oResponse.data.page_reload ) {
								location.reload( true );
							}
							else {
								plugin.options[ 'table' ].reloadTable();
								iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
							}
						}
						else {
							var sMessage = 'Communications error with site.';
							if ( oResponse.data.message !== undefined ) {
								sMessage = oResponse.data.message;
							}
							alert( sMessage );
							iCWP_WPSF_BodyOverlay.hide();
						}
					}
				).always( function () {
					}
				);
			},
			callback: function () {
			}
		}
	);

	$.fn.icwpWpsfScanResults = function ( aOptions ) {
		return this.each(
			function () {
				if ( !$.data( this, "plugin_" + pluginName ) ) {
					$.data( this, "plugin_" + pluginName, new Ob_ScanResults( this, aOptions ) );
				}
			}
		);
	};

	$.fn.icwpWpsfScanResults.defaults = {};

})( jQuery );

/**
 */
jQuery.fn.icwpWpsfStartScans = function ( aOptions ) {

	var startScans = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $oThis.serialize() } );
		return false;
	};

	var sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		var aReqData = aOpts[ 'ajax_start_scans' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
					if ( oResponse.data.page_reload ) {
						location.reload( true );
					}
					else {
						plugin.options[ 'table' ].reloadTable();
						iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
					}
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}

			}
		).always( function () {
			}
		);
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', startScans );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};