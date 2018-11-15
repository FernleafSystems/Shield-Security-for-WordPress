/**
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
					'td.column-actions a.delete',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.deleteEntry.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'td.column-actions a.ignore',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.ignoreEntry.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'td.column-actions a.repair',
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
							plugin.options[ 'table' ].reloadTable();
							iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
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
				// // Cache onComplete option
				// var onComplete = this.options.onComplete;
				//
				// if ( typeof onComplete === 'function' ) {
				// 	/*
				// 		Use the "call" method so that inside of the onComplete
				// 		callback function the "this" keyword refers to the
				// 		specific DOM node that called the plugin.
				//
				// 		More:
				// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Function/call */
				// onComplete.call( this.element ); }
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