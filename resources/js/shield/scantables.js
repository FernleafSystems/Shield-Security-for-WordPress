/**
 * Add ajax actions to table buttons, and automatically refreshes the table.
 */
(function ( $, window, document, undefined ) {

	var pluginName = 'icwpWpsfScanTableActions';

	function Ob_TableActions( element, options ) {
		this.element = element;
		this._name = pluginName;
		this._defaults = $.fn.icwpWpsfScanTableActions.defaults;
		this.options = $.extend(
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
				this.setupDatatable();
				this.bindEvents();
			},
			destroy: function () {
				this.unbindEvents();
			},
			buildCache: function () {
				this.$element = $( this.element );
			},
			setupDatatable: function () {
				this.$element.DataTable( {
					data: this.options[ 'file_guard_data' ],
					columns: [
						{ data: 'rid', title: 'ID', visible: false, searchable: false },
						{ data: 'path_fragment', title: 'File' },
						{ data: 'status', title: 'Status', searchable: false },
						{ data: 'file_type', title: 'Type' },
						{ data: 'actions', title: 'Actions', orderable: false, searchable: false },
					],
					select: {
						style: 'multi'
					},
					dom: 'Bfrtip',
					buttons: [
						{
							text: 'Refresh',
							action: function ( e, dt, node, config ) {
								alert( 'Refresh' );
							}
						},
						{
							text: 'Ignore Selected',
							action: function ( e, dt, node, config ) {
								alert( 'Ignore Selected' );
							}
						},
						{
							text: 'Repair Selected',
							action: function ( e, dt, node, config ) {
								alert( 'Ignore Selected' );
							}
						},
						{
							text: 'Ignore All',
							action: function ( e, dt, node, config ) {
								alert( 'Ignore All' );
							}
						},
						{
							text: 'Repair All',
							className: '',
							titleAttr: 'Repair All (that can be repaired)',
							action: function ( e, dt, node, config ) {
								alert( 'Repair All' );
							}
						}
					]
				} );

				$( '#ScanResultsPlugins a[data-toggle="tab"]' ).on( 'shown.bs.tab', function ( e ) {
					$.fn.dataTable.tables( { visible: true, api: true } ).columns.adjust();
				} );
			},
			bindEvents: function () {
				var plugin = this;

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.delete',
					function ( evt ) {
						evt.preventDefault();
						if ( confirm( icwp_wpsf_vars_insights.strings.are_you_sure ) ) {
							plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
							plugin.deleteEntry.call( plugin );
						}
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
						var button = $( this );
						var href = button.data( 'href-download' );
						if ( href !== undefined ) {
							plugin.options[ 'working_href_download' ] = href;
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

			deleteEntry: function () {
				let reqData = this.options[ 'ajax_item_delete' ];
				reqData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( reqData );
			},

			ignoreEntry: function () {
				let reqData = this.options[ 'ajax_item_ignore' ];
				reqData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( reqData );
			},

			repairEntry: function () {
				let reqData = this.options[ 'ajax_item_repair' ];
				reqData[ 'rid' ] = this.options[ 'working_rid' ];
				this.sendReq( reqData );
			},

			itemAction: function () {
				let reqData = this.options[ 'ajax_item_action' ];
				reqData[ 'rid' ] = this.options[ 'working_rid' ];
				reqData[ 'item_action' ] = this.options[ 'working_item_action' ];
				this.sendReq( reqData );
			},

			customAction: function () {
				this.sendReq( this.options[ 'working_custom_action' ] );
			},

			hrefDownload: function () {
				$.fileDownload( this.options[ 'working_href_download' ], {
					preparingMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file,
					failMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file_problem
				} );
				return false;
			},

			sendReq: function ( reqData ) {
				iCWP_WPSF_BodyOverlay.show();

				var plugin = this;

				$.post( ajaxurl, $.extend( reqData, plugin.options[ 'req_params' ] ),
					function ( response ) {

						if ( response.success ) {
							iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
							if ( response.data.page_reload ) {
								location.reload();
							}
							else {
								plugin.options[ 'table' ].reloadTable();
								iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
								iCWP_WPSF_BodyOverlay.hide();
							}
						}
						else {
							let sMessage = 'Communications error with site.';
							if ( response.data.message !== undefined ) {
								sMessage = response.data.message;
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

	$.fn.icwpWpsfScanTableActions = function ( aOptions ) {
		return this.each(
			function () {
				if ( !$.data( this, "plugin_" + pluginName ) ) {
					$.data( this, "plugin_" + pluginName, new Ob_TableActions( this, aOptions ) );
				}
			}
		);
	};

	$.fn.icwpWpsfScanTableActions.defaults = {
		'custom_actions_ajax': {},
		'req_params': {}
	};

})( jQuery );