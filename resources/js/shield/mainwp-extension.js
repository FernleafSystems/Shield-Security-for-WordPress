(function ( $, window, document, undefined ) {

	var pluginName = 'icwpWpsfMainwpExtension';

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
							plugin.options[ 'ajax_sh_site_action' ],
							{
								'sid': $( this ).parent().data( 'sid' ),
								'saction': $( this ).data( 'saction' )
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
						var sAction = $( '#bulk-action-selector-top', plugin.$element ).find( ":selected" ).val();

						if ( sAction === "-1" ) {
							alert( icwp_wpsf_vars_insights.strings.select_action );
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
						else {
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
				let reqData = this.options[ 'ajax_bulk_action' ];
				this.sendReq( reqData );
			},

			site_action: function () {
				this.sendReq();
			},

			customAction: function () {
				this.sendReq( this.options[ 'working_custom_action' ] );
			},

			hrefDownload: function () {
				$.fileDownload( this.options[ 'working_href_download' ], {
					preparingMessageHtml: icwp_wpsf_vars_insights.strings.downloading_file,
					failMessageHtml: icwp_wpsf_vars_insights.strings.downloading_file_problem
				} );
				return false;
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
			columnDefs: [ {
				orderable: false,
				className: 'select-checkbox',
				targets: 0
			} ],
			select: {
				style: 'os',
				selector: 'td:first-child'
			},
			order: [ [ 1, 'asc' ] ]
		} );
*/
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