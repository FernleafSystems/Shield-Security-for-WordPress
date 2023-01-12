(function ( $, window, document, undefined ) {

	let pluginName = 'icwpWpsfMainwpExtension';
	let siteFrame;

	function Ob_TableActions( element, options ) {
		this.element = element;
		this._name = pluginName;
		this._defaults = $.fn.icwpWpsfMainwpExt.defaults;
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
					'.site-dropdown a.site_action',
					function ( evt ) {
						evt.preventDefault();

						plugin.options[ 'req_params' ] = $.extend(
							plugin.options.ajax_actions[ 'site_action' ],
							{
								'site_id': $( this ).parent().data( 'sid' ),
								'site_action': $( this ).data( 'site_action' )
							}
						);
						plugin.site_action.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.item_action',
					function ( evt ) {
						evt.preventDefault();
						plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
						plugin.options[ 'working_item_action' ] = $( this ).data( 'item_action' );
						plugin.itemAction.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'.tablenav.top input[type=submit].button.action',
					function ( evt ) {
						evt.preventDefault();
						let action = $( '#bulk-action-selector-top', plugin.$element ).find( ":selected" ).val();

						if ( action === "-1" ) {
							alert( icwp_wpsf_vars_insights.strings.select_action );
						}
						else {
							let checkedIds = $( "input:checkbox[name=ids]:checked", plugin.$element ).map(
								function () {
									return $( this ).val()
								} ).get();

							if ( checkedIds.length < 1 ) {
								alert( 'No rows currently selected' );
							}
							else {
								plugin.options[ 'req_params' ][ 'bulk_action' ] = action;
								plugin.options[ 'req_params' ][ 'ids' ] = checkedIds;
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
						let $button = $( this );
						let customAction = $button.data( 'custom-action' );
						if ( customAction in plugin.options[ 'custom_actions_ajax' ] ) {
							plugin.options[ 'working_custom_action' ] = plugin.options[ 'custom_actions_ajax' ][ customAction ];
							plugin.options[ 'working_custom_action' ][ 'rid' ] = $button.data( 'rid' );
							plugin.customAction.call( plugin );
						}
						else {
							/** This should never be reached live: **/
							alert( 'custom action not supported: ' + customAction );
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
				let reqData = this.options[ 'ajax_bulk_action' ];
				this.sendReq( reqData );
			},

			site_action: function () {
				this.sendReq();
			},

			customAction: function () {
				this.sendReq( this.options[ 'working_custom_action' ] );
			},

			sendReq: function ( reqData = {} ) {
				iCWP_WPSF_BodyOverlay.show();

				var plugin = this;

				$.post( ajaxurl, $.extend( reqData, plugin.options[ 'req_params' ] ),
					function ( oR ) {
						if ( oR.success ) {
							iCWP_WPSF_Growl.showMessage( oR.data.message, oR.success );
							if ( oR.data.page_reload ) {
								setTimeout( function () {
									location.reload();
								}, 1500 );
							}
						}
						else {
							let msg = 'Communications error with site.';
							if ( oR.data.message !== undefined ) {
								msg = oR.data.message;
							}
							alert( msg );
							iCWP_WPSF_BodyOverlay.hide();
						}
					}
				).always( function () {
						iCWP_WPSF_BodyOverlay.hide();
					}
				);
			},
			callback: function () {
			}
		}
	);

	$.fn.icwpWpsfMainwpExt = function ( options ) {
/*
		jQuery( '#mainwp-shield-extension-table-sites' ).DataTable( {
			serverSide: true,
			"ajax": {
				"url": ajaxurl,
				"type": "POST",
				"data": function ( d ) {
					return $.extend( {}, d, this.options.ajax_actions[ 'site_action' ] );
				},
				"dataSrc": function ( json ) {
					for ( var i = 0, ien = json.data.length; i < ien; i++ ) {
						json.data[ i ].syncError = json.rowsInfo[ i ].syncError ? json.rowsInfo[ i ].syncError : false;
						json.data[ i ].rowClass = json.rowsInfo[ i ].rowClass;
						json.data[ i ].siteID = json.rowsInfo[ i ].siteID;
						json.data[ i ].siteUrl = json.rowsInfo[ i ].siteUrl;
					}
					return json.data;
				}
			},
		} );
*/
		// siteFrame = $( "iframe#SiteContent" );
		// if ( siteFrame.length === 1 ) {
		// 	siteFrame.attr( "srcdoc", "<p>This content was updated dynamically!</p>" );
		// }

		return this.each(
			function () {
				if ( !$.data( this, "plugin_" + pluginName ) ) {
					$.data( this, "plugin_" + pluginName, new Ob_TableActions( this, options ) );
				}
			}
		);
	};

	$.fn.icwpWpsfMainwpExt.defaults = {
		'custom_actions_ajax': {},
		'req_params': {}
	};

})( jQuery );