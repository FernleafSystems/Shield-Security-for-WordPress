(function ( $, window, document, undefined ) {

	$.fn.icwpWpsfAuditTableActions = function ( runtimeOptions ) {
		return this.each(
			function () {
				new $.icwpWpsfAuditTableActions( this, runtimeOptions )
			}
		);
	};

	$.icwpWpsfAuditTableActions = function ( el, options ) {
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		const base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $( el );
		base.el = el;

		// Add a reverse reference to the DOM object
		base.$el.data( "icwpWpsfAuditTableActions", base );

		base.init = function () {
			base.options = $.extend( {}, $.icwpWpsfAuditTableActions.defaultOptions, options );
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

			$( 'body' ).popover( {
				trigger: 'click',
				sanitize: false,
				html: true,
				animation: true,
				selector: 'td.meta > button[data-toggle="popover"]',
				container: '#SectionAuditTable',
			} );

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
			return JSON.parse( JSON.stringify( base.options.ajax[ 'logtable_action' ] ) );
		};

		base.setupDatatable = function () {

			this.$table = this.$el.DataTable(
				$.extend( base.options.datatables_init,
					{
						ajax: function ( data, callback, settings ) {
							let reqData = base.getBaseAjaxData();
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
						select: {
							style: 'multi'
						},
						dom: 'PBfrtip',
						searchPanes: {
							cascadePanes: true,
							viewTotal: true,
							initCollapsed: true
						},
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
							}
						],
						language: {
							emptyTable: "There are no items to display, or they've been set to be ignored."
						}
					}
				) );
		};

		base.tableReload = function ( full = false ) {
			this.$table.ajax.reload( null, full );
			this.rowSelectionChanged();
		};

		// Run initializer
		base.init();
	}

	$.icwpWpsfAuditTableActions.defaultOptions = {};

})( jQuery );