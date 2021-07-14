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
				var plugin = this;

				this.$table = this.$element.DataTable( {
					// data: this.options[ 'file_guard_data' ],
					ajax: function ( data, callback, settings ) {
						let reqData = plugin.getBaseAjaxData();
						reqData.sub_action = 'retrieve_table_data';
						reqData.type = plugin.options.type;
						reqData.file = plugin.options.file;

						$.post( ajaxurl, reqData, function ( response ) {
							if ( response.success ) {
								callback( response.data.vars );
							}
							else {
								let msg = 'Communications error with site.';
								if ( response.data.message !== undefined ) {
									msg = response.data.message;
								}
								alert( msg );
							}
						} );
					},
					deferRender: true,
					columns: [
						{ data: 'rid', title: 'ID', visible: false, searchable: false },
						{ data: 'file', title: 'File' },
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
							text: 'Reload',
							name: 'table-reload',
							className: 'action table-refresh',
							action: function ( e, dt, node, config ) {
								plugin.tableReload.call( plugin );
							}
						},
						{
							text: 'Ignore Selected',
							name: 'selected-ignore',
							className: 'action selected-action ignore',
							action: function ( e, dt, node, config ) {
								plugin.bulkAction.call( plugin, 'ignore' );
							}
						},
						{
							text: 'Repair Selected',
							name: 'selected-repair',
							className: 'action selected-action repair',
							action: function ( e, dt, node, config ) {
								plugin.bulkAction.call( plugin, 'repair' );
							}
						},
						{
							text: 'Ignore All',
							name: 'all-ignore',
							className: 'action ignore-all',
							action: function ( e, dt, node, config ) {
								plugin.allAction.call( plugin, 'ignore' );
							}
						},
						{
							text: 'Repair All',
							name: 'all-repair',
							className: 'action repair-all',
							titleAttr: 'Repair All (that can be repaired)',
							action: function ( e, dt, node, config ) {
								plugin.allAction.call( plugin, 'repair' );
							}
						}
					],
					language: {
						emptyTable: "There are no item to display, or they've all been set to be ignored."
					}
				} );

				$( '#ScanResultsPlugins a[data-toggle="tab"]' ).on( 'shown.bs.tab', function ( e ) {
					$.fn.dataTable.tables( { visible: true, api: true } ).columns.adjust();
				} );
			},
			bindEvents: function () {
				var plugin = this;

				plugin.$table.on( 'draw',
					function ( e, dt, type, row_index ) {
						plugin.rowSelectionChanged.call( plugin );
					}
				);

				plugin.$table.on( 'select',
					function ( e, dt, type, row_index ) {
						plugin.options[ 'working_rid' ] = plugin.$table.rows( row_index ).data().pluck( 'rid' )[ 0 ];
						plugin.rowSelectionChanged.call( plugin );
					}
				);

				plugin.$table.on( 'deselect',
					function ( e, dt, type, row_index ) {
						plugin.rowSelectionChanged.call( plugin );
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.delete',
					function ( evt ) {
						evt.preventDefault();
						if ( confirm( icwp_wpsf_vars_insights.strings.are_you_sure ) ) {
							plugin.deleteEntry.call( plugin, $( this ).data( 'rid' ) );
						}
					}
				);

				plugin.$element.on(
					'click' + '.' + plugin._name,
					'button.action.ignore',
					function ( evt ) {
						evt.preventDefault();
						plugin.bulkAction.call( plugin, 'ignore', [ $( this ).data( 'rid' ) ] );
					}
				);

				plugin.$table.on(
					'click' + '.' + plugin._name,
					'button.action.repair',
					function ( evt ) {
						evt.preventDefault();
						plugin.$table.rows().deselect();
						plugin.repairEntry.call( plugin, $( this ).data( 'rid' ) );
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

			tableReload: function ( full = false ) {
				this.$table.ajax.reload( null, full );
				this.rowSelectionChanged();
			},

			bulkAction: function ( action, RIDs = [] ) {

				if ( RIDs.length === 0 ) {
					this.$table.rows( { selected: true } ).every( function ( rowIdx, tableLoop, rowLoop ) {
						RIDs.push( this.data()[ 'rid' ] );
					} );
				}

				if ( RIDs.length > 0 ) {
					let reqData = this.getBaseAjaxData();
					reqData[ 'sub_action' ] = action;
					reqData[ 'rids' ] = RIDs;
					this.sendReq( reqData );
				}
			},

			rowSelectionChanged: function () {
				if ( this.$table.rows( { selected: true } ).count() > 0 ) {
					this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).enable();
				}
				else {
					this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).disable();
				}
			},

			allAction: function ( action ) {
				this.$table.rows().select();
				this.bulkAction( action )
			},

			deleteEntry: function ( rid ) {
				this.bulkAction( 'delete', [ rid ] )
			},

			ignoreEntry: function ( rid ) {
				this.bulkAction( 'ignore', [ rid ] )
			},

			repairEntry: function ( rid ) {
				this.bulkAction( 'repair', [ rid ] )
			},

			getBaseAjaxData: function () {
				return JSON.parse( JSON.stringify( this.options.ajax[ 'scantable_action' ] ) );
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
							if ( response.data.table_reload ) {
								plugin.tableReload();
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
						iCWP_WPSF_BodyOverlay.hide();
					}
				);
			},
			callback: function () {
			}
		}
	);

	$.fn.icwpWpsfScanTableActions = function ( runtimeOptions ) {
		return this.each(
			function () {
				if ( !$.data( this, "plugin_" + pluginName ) ) {
					$.data( this, "plugin_" + pluginName, new Ob_TableActions( this, runtimeOptions ) );
				}
			}
		);
	};

	$.fn.icwpWpsfScanTableActions.defaults = {
		'custom_actions_ajax': {},
		'req_params': {}
	};

})( jQuery );