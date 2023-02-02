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
				$.extend(
					base.options.table_init,
					{
						dom: 'BPrpftip',
						serverSide: true,
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
						search: {},
						searchDelay: 400,
						buttons: {
							buttons: [
								{
									text: 'Reload Table',
									name: 'table-reload',
									className: 'action table-refresh btn-outline-secondary mb-2',
									action: function ( e, dt, node, config ) {
										base.tableReload.call( base );
									}
								}
							],
							dom: {
								button: {
									className: 'btn'
								}
							}
						},
						language: {
							search: "Search IP",
							emptyTable: "There are no items to display.",
							zeroRecords: "No entries found - please try adjusting your search filters."
						}
					}
				) );

			new $.fn.dataTable.Debounce( this.$table );
		};

		base.tableReload = function ( full = false ) {
			this.$table.ajax.reload( null, full );
			this.rowSelectionChanged();
		};

		base.ipAdd = function () {
			iCWP_WPSF_OffCanvas.renderIpRuleAddForm();
		};

		// Run initializer
		base.init();
	}

	$.icwpWpsfIpRulesTableActions.defaultOptions = {};

	/** https://datatables.net/forums/discussion/comment/164708/#Comment_164708 **/
	$.fn.dataTable.Debounce = function ( table, options ) {
		var tableId = table.settings()[ 0 ].sTableId;
		$( '.dataTables_filter input[aria-controls="' + tableId + '"]' ) // select the correct input field
		.unbind() // Unbind previous default bindings
		.bind( 'input', (delay( function ( e ) { // Bind our desired behavior
			table.search( $( this ).val() ).draw();
		}, 600 )) ); // Set delay in milliseconds
	}

	function delay( callback, ms ) {
		let timer = 0;
		return function () {
			let context = this, args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				callback.apply( context, args );
			}, ms || 0 );
		};
	}

})( jQuery );