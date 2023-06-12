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
				customClass: 'audit-meta',
				placement: 'left',
				selector: 'td.meta > button[data-toggle="popover"]',
				container: 'body',
				title: 'Request Meta Info',
				content: function ( element ) {
					let content = 'no meta';
					let reqData = base.getBaseAjaxData();
					reqData.sub_action = 'get_request_meta';
					reqData.rid = element.dataset.rid;
					reqData.apto_wrap_response = 1;

					jQuery.ajax( {
						type: "POST",
						url: ajaxurl,
						data: reqData,
						dataType: "text",
						async: false,
						success: function ( raw ) {
							let resp = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );
							content = resp.data.html;
						}
					} ).fail( function () {
						alert( 'Something went wrong with the request - it was either blocked or there was an error.' );
					} ).always( function () {
						iCWP_WPSF_BodyOverlay.hide();
					} );

					return content;
				},
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
						dom: 'PrBpftip',
						serverSide: true,
						searchDelay: 600,
						ajax: function ( data, callback, settings ) {
							iCWP_WPSF_BodyOverlay.show();
							let reqData = base.getBaseAjaxData();
							reqData.sub_action = 'retrieve_table_data';
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
							} )
							 .always( function () {
								 iCWP_WPSF_BodyOverlay.hide();
							 } );
						},
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
									className: 'btn btn-sm'
								}
							}
						},
						deferRender: true,
						language: {
							emptyTable: "There are no items to display.",
							zeroRecords: "No entries found - please try adjusting your search filters."
						},
						pageLength: 25,
						search: {},
						select: {
							style: 'multi'
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