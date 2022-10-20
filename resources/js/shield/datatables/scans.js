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
		const base = this;

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
						base.bulkAction.call( base, 'delete', [ $( this ).data( 'rid' ) ] );
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

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.repair',
				function ( evt ) {
					evt.preventDefault();
					base.$table.rows().deselect();
					base.bulkAction.call( base, 'repair', [ $( this ).data( 'rid' ) ] );
				}
			);

			base.$el.on(
				'click' + '.' + base._name,
				'.action.view-file',
				function ( evt ) {
					evt.preventDefault();

					Shield_AjaxRender
					.send_ajax_req( {
						render_slug: 'scanitemanalysis_container',
						rid: $( this ).data( 'rid' )
					} )
					.then( ( response ) => {
						if ( response.success ) {
							let $fileViewModal = jQuery( '#ShieldModalContainer' );
							jQuery( '.modal-content', $fileViewModal ).html( response.data.html );
							$fileViewModal.modal( 'show' );
							$fileViewModal[ 0 ].querySelectorAll( 'pre.icwp-code-render code' ).forEach( ( el ) => {
								hljs.highlightElement( el );
							} );
						}
						else {
							alert( response.data.message );
							// console.log( response );
						}
					} )
					.catch( ( error ) => {
						console.log( error );
					} )
					.finally( ( response ) => {
						iCWP_WPSF_BodyOverlay.hide();
					} );
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
						base.hrefDownload.call( base, href );
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

		base.hrefDownload = function ( href ) {
			$.fileDownload( href, {
				preparingMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file,
				failMessageHtml: icwp_wpsf_vars_plugin.strings.downloading_file_problem
			} );
			return false;
		};

		base.sendReq = function ( reqData, forceTableReload = false ) {

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

					if ( response.data.table_reload ) {
						base.tableReload();
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

		base.setupDatatable = function () {

			this.$table = this.$el.DataTable(
				$.extend( base.options.datatables_init,
					{
						serverSide: true,
						ajax: function ( data, callback, settings ) {
							let reqData = base.getBaseAjaxData();
							reqData.sub_action = 'retrieve_table_data';
							reqData.type = base.options.type;
							reqData.file = base.options.file;
							reqData.table_data = data;
							$.post( ajaxurl, reqData, function ( response ) {
								if ( response.success ) {
									callback( response.data.datatable_data );
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
						select: {
							style: 'multi'
						},
						dom: 'Brpftip',
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
								text: 'De/Select All',
								name: 'all-select',
								className: 'select-all',
								action: function ( e, dt, node, config ) {
									let total = base.$table.rows().count()
									if ( base.$table.rows( { selected: true } ).count() < total ) {
										base.$table.rows().select();
									}
									else {
										base.$table.rows().deselect();
									}
								}
							},
							{
								text: 'Ignore Selected',
								name: 'selected-ignore',
								className: 'action selected-action ignore',
								action: function ( e, dt, node, config ) {
									if ( confirm( icwp_wpsf_vars_insights.strings.are_you_sure ) ) {
										base.bulkAction.call( base, 'ignore' );
									}
								}
							},
							{
								text: 'Delete/Repair Selected',
								name: 'selected-repair',
								className: 'action selected-action repair',
								action: function ( e, dt, node, config ) {

									if ( base.$table.rows( { selected: true } ).count() > 20 ) {
										alert( "Sorry, this tool isn't designed for such large repairs. We recommend completely removing and reinstalling the item." )
									}
									else if ( confirm( icwp_wpsf_vars_insights.strings.absolutely_sure ) ) {
										base.bulkAction.call( base, 'repair-delete' );
									}
								}
							}
						],
						language: {
							emptyTable: "There are no items to display, or they've been set to be ignored."
						}
					}
				) );

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