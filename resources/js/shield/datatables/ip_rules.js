(function ( $, window, document, undefined ) {

	$.fn.icwpWpsfIpRulesTableActions = function ( runtimeOptions ) {
		return this.each(
			function () {
				new $.icwpWpsfIpRulesTableActions( this, runtimeOptions )
			}
		);
	};

	$.icwpWpsfIpRulesTableActions = function ( el, options ) {
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		const base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $( el );
		base.el = el;

		// Add a reverse reference to the DOM object
		base.$el.data( "icwpWpsfIpRulesTableActions", base );

		base.init = function () {
			base.options = $.extend( {}, $.icwpWpsfIpRulesTableActions.defaultOptions, options );
			base.setupDatatable();
			base.bindEvents();
		};

		base.rowSelectionChanged = function () {
		};

		base.bindEvents = function () {

			base.$el.on(
				'click' + '.' + base._name,
				'button.action.ignore',
				function ( evt ) {
					evt.preventDefault();
					base.bulkAction.call( base, 'ignore', [ $( this ).data( 'rid' ) ] );
				}
			);

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
			return JSON.parse( JSON.stringify( base.options.ajax[ 'table_action' ] ) );
		};

		base.setupDatatable = function () {

			this.$table = this.$el.DataTable(
				$.extend( base.options.datatables_init,
					{
						serverSide: true,
						searchDelay: 600,
						ajax: function ( data, callback, settings ) {
							let reqData = base.getBaseAjaxData();
							reqData.sub_action = 'retrieve_table_data';
							reqData.type = base.options.type;
							reqData.file = base.options.file;
							reqData.table_data = data;

							iCWP_WPSF_BodyOverlay.show();
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
							} )
							 .always( function () {
								 iCWP_WPSF_BodyOverlay.hide();
							 } );
						},
						deferRender: true,
						select: {
							style: 'multi'
						},
						dom: 'BPrptip',
						searchPanes: {
							cascadePanes: false,
							viewTotal: false,
							viewCount: false,
							initCollapsed: true
						},
						search: {},
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
								text: 'Add New IP',
								name: 'ip-add',
								className: 'action ipadd',
								action: function ( e, dt, node, config ) {
									base.ipAdd.call( base );
								}
							}
						],
						language: {
							emptyTable: "There are no items to display.",
							zeroRecords: "No entries found - please try adjusting your search filters."
						}
					}
				) );
		};

		base.tableReload = function ( full = false ) {
			this.$table.ajax.reload( null, full );
			this.rowSelectionChanged();
		};

		base.ipAdd = function () {
			iCWP_WPSF_Modals.renderModalIpAdd();
			base.tableReload();
		};

		// Run initializer
		base.init();
	}

	$.icwpWpsfIpRulesTableActions.defaultOptions = {};

})( jQuery );