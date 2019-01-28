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

/**
 * Add ajax actions to table buttons, and automatically refreshes the table.
 */
(function ( $, window, document, undefined ) {

	var pluginName = 'icwpWpsfTableActions';

	function Ob_TableActions( element, options ) {
		this.element = element;
		this._name = pluginName;
		this._defaults = $.fn.icwpWpsfTableActions.defaults;
		this.options = $.extend(
			{
				'forms': {
					'insert': ''
				}
			},
			this._defaults,
			options
		);
		this.init();
	}

	$.extend(
		Ob_TableActions.prototype,
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
				this.$oFormInsert = this.options[ 'forms' ][ 'insert' ];
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

				if ( typeof this.$oFormInsert !== 'undefined' && this.$oFormInsert.length ) {
					this.$oFormInsert.on(
						'submit' + '.' + plugin._name,
						function ( evt ) {
							evt.preventDefault();
							plugin.insertEntry.call( plugin );
						}
					);
				}

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.repair',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.repairEntry.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'.tablenav.top input[type=submit].button.action',
					function ( evt ) {
						evt.preventDefault();
						var sAction = $( '#bulk-action-selector-top', plugin.$element ).find( ":selected" ).val();

						if ( sAction === "-1" ) {
							alert( 'Please first select an action to perform' );
						}
						else {
							var aCheckedIds = $( "input:checkbox[name=ids]:checked", plugin.$element ).map(
								function () {
									return $( this ).val()
								} ).get();

							if ( aCheckedIds.length < 1 ) {
								alert( 'No rows currently selected' );
							}
							else {
								plugin.options[ 'req_params' ][ 'bulk_action' ] = sAction;
								plugin.options[ 'req_params' ][ 'ids' ] = aCheckedIds;
								plugin.bulkAction.call( plugin );
							}
						}
						return false;
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.custom-action',
					function ( evt ) {
						evt.preventDefault();
						var $oButt = $( this );
						var sCustomAction = $oButt.data( 'custom-action' );
						if ( sCustomAction in plugin.options[ 'custom_actions_ajax' ] ) {
							plugin.options[ 'working_custom_action' ] = plugin.options[ 'custom_actions_ajax' ][ sCustomAction ];
							plugin.options[ 'working_custom_action' ][ 'rid' ] = $oButt.data( 'rid' );
							plugin.customAction.call( plugin );
						}
						else{
							/** This should never be reached live: **/
							alert( 'custom action not supported: ' + sCustomAction );
						}
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.href-download',
					function ( evt ) {
						evt.preventDefault();
						var $oButt = $( this );
						var sHref = $oButt.data( 'href-download' );
						if ( sHref !== undefined ) {
							plugin.options[ 'working_href_download' ] = sHref;
							plugin.hrefDownload.call( plugin );
						}
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

			bulkAction: function () {
				var aRequestData = this.options[ 'ajax_bulk_action' ];
				this.sendReq( aRequestData );
			},

			deleteEntry: function () {
				var aRequestData = this.options[ 'ajax_item_delete' ];
				aRequestData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( aRequestData );
			},

			insertEntry: function () {
				var requestData = this.options[ 'ajax_item_insert' ];
				requestData[ 'form_params' ] = this.$oFormInsert.serialize();
				this.sendReq( requestData );
			},

			ignoreEntry: function () {
				var aRequestData = this.options[ 'ajax_item_ignore' ];
				aRequestData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( aRequestData );
			},

			repairEntry: function () {
				var aRequestData = this.options[ 'ajax_item_repair' ];
				aRequestData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( aRequestData );
			},

			customAction: function () {
				var aRequestData = this.options[ 'working_custom_action' ];
				this.sendReq( aRequestData );
			},

			hrefDownload: function () {
				$.fileDownload( this.options[ 'working_href_download' ], {
					preparingMessageHtml: "Downloading file, please wait...",
					failMessageHtml: "There was a problem downloading the file."
				} );
				return false;
			},

			sendReq: function ( aRequestData ) {
				iCWP_WPSF_BodyOverlay.show();

				var plugin = this;

				$.post( ajaxurl, $.extend( aRequestData, plugin.options[ 'req_params' ] ),
					function ( oResponse ) {

						if ( oResponse.success ) {
							iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.success );
							if ( oResponse.data.page_reload ) {
								location.reload( true );
							}
							else {
								plugin.options[ 'table' ].reloadTable();
								iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.success );
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

	$.fn.icwpWpsfTableActions = function ( aOptions ) {
		return this.each(
			function () {
				if ( !$.data( this, "plugin_" + pluginName ) ) {
					$.data( this, "plugin_" + pluginName, new Ob_TableActions( this, aOptions ) );
				}
			}
		);
	};

	$.fn.icwpWpsfTableActions.defaults = {
		'custom_actions_ajax': {},
		'req_params': {}
	};

})( jQuery );