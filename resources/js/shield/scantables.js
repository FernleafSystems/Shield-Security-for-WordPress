/**
 * https://css-tricks.com/snippets/jquery/jquery-plugin-template/
 */
(function ( $, window, document, undefined ) {

	$.fn.icwpWpsfScanTableActions = function ( runtimeOptions ) {
		return this.each(
			function () {
				new $.icwpWpsfScanTableActions( this, runtimeOptions )
			}
		);
	};

	$.icwpWpsfScanTableActions = function ( el, options ) {
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		var base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $( el );
		base.el = el;

		// Add a reverse reference to the DOM object
		base.$el.data( "icwpWpsfScanTableActions", base );

		base.init = function () {
			base.options = $.extend( {}, $.icwpWpsfScanTableActions.defaultOptions, options );
			base.setupDatatable();
			base.bindEvents();
		};

		base.rowSelectionChanged = function () {
			if ( this.$table.rows( { selected: true } ).count() > 0 ) {
				this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).enable();
			}
			else {
				this.$table.buttons( 'selected-ignore:name, selected-repair:name' ).disable();
			}
		};

		base.bindEvents = function () {

			base.$table.on( 'draw',
				function ( e, dt, type, row_index ) {
					base.rowSelectionChanged.call( base );
				}
			);

			base.$table.on( 'select',
				function ( e, dt, type, row_index ) {
					base.rowSelectionChanged.call( base );
				}
			);

			base.$table.on( 'deselect',
				function ( e, dt, type, row_index ) {
					base.rowSelectionChanged.call( base );
				}
			);

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.delete',
				function ( evt ) {
					evt.preventDefault();
					if ( confirm( icwp_wpsf_vars_insights.strings.are_you_sure ) ) {
						base.deleteEntry.call( base, $( this ).data( 'rid' ) );
					}
				}
			);

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.ignore',
				function ( evt ) {
					evt.preventDefault();
					base.bulkAction.call( base, 'ignore', [ $( this ).data( 'rid' ) ] );
				}
			);

			base.$table.on(
				'click' + '.' + base._name,
				'button.action.repair',
				function ( evt ) {
					evt.preventDefault();
					base.$table.rows().deselect();
					base.repairEntry.call( base, $( this ).data( 'rid' ) );
				}
			);

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.href-download',
				function ( evt ) {
					evt.preventDefault();
					var button = $( this );
					var href = button.data( 'href-download' );
					if ( href !== undefined ) {
						base.options[ 'working_href_download' ] = href;
						base.hrefDownload.call( base );
					}
				}
			);

		};

		base.bulkAction = function ( action, RIDs = [] ) {

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
		};

		base.deleteEntry = function ( rid ) {
			this.bulkAction( 'delete', [ rid ] )
		};

		base.ignoreEntry = function ( rid ) {
			this.bulkAction( 'ignore', [ rid ] )
		};

		base.repairEntry = function ( rid ) {
			this.bulkAction( 'repair', [ rid ] )
		};

		base.hrefDownload = function () {
			$.fileDownload( this.getOpts.working_href_download, {
				preparingMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file,
				failMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file_problem
			} );
			return false;
		};

		base.sendReq = function ( reqData ) {
			iCWP_WPSF_BodyOverlay.show();

			$.post( ajaxurl, reqData,
				function ( response ) {

					if ( response.success ) {
						iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
						if ( response.data.table_reload ) {
							base.tableReload();
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
		};

		base.getBaseAjaxData = function () {
			return JSON.parse( JSON.stringify( base.options.ajax[ 'scantable_action' ] ) );
		};

		base.setupDatatable = function () {

			this.$table = this.$el.DataTable( {
				ajax: function ( data, callback, settings ) {
					let reqData = base.getBaseAjaxData();
					// console.log( reqData );
					// console.log( base.options );
					reqData.sub_action = 'retrieve_table_data';
					reqData.type = base.options.type;
					reqData.file = base.options.file;
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
							base.tableReload.call( base );
						}
					},
					{
						text: 'Ignore Selected',
						name: 'selected-ignore',
						className: 'action selected-action ignore',
						action: function ( e, dt, node, config ) {
							base.bulkAction.call( base, 'ignore' );
						}
					},
					{
						text: 'Repair Selected',
						name: 'selected-repair',
						className: 'action selected-action repair',
						action: function ( e, dt, node, config ) {
							base.bulkAction.call( base, 'repair' );
						}
					},
					{
						text: 'Ignore All',
						name: 'all-ignore',
						className: 'action ignore-all',
						action: function ( e, dt, node, config ) {
							base.$table.rows().select();
							base.bulkAction.call( base, 'ignore' );
						}
					},
					{
						text: 'Repair All',
						name: 'all-repair',
						className: 'action repair-all',
						titleAttr: 'Repair All (that can be repaired)',
						action: function ( e, dt, node, config ) {
							base.$table.rows().select();
							base.bulkAction.call( base, 'repair' );
						}
					}
				],
				language: {
					emptyTable: "There are no items to display, or they've been set to be ignored."
				}
			} );

			$( '#ScanResultsPlugins a[data-toggle="tab"]' ).on( 'shown.bs.tab', function ( e ) {
				$.fn.dataTable.tables( { visible: true, api: true } ).columns.adjust();
			} );
		};

		base.tableReload = function ( full = false ) {
			this.$table.ajax.reload( null, full );
			this.rowSelectionChanged();
		};

		// Run initializer
		base.init();
	}

	$.icwpWpsfScanTableActions.defaultOptions = {};

})( jQuery );