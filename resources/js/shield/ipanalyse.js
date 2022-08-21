jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	let initialise = function () {
		jQuery( document ).on( 'click', 'a.ip_analyse_action', function ( evt ) {
			evt.preventDefault();
			if ( confirm( 'Are you sure?' ) ) {
				let $this = jQuery( this );
				let params = opts[ 'ip_analyse_action' ];
				params.ip = $this.data( 'ip' );
				params.ip_action = $this.data( 'ip_action' );
				iCWP_WPSF_StandardAjax.send_ajax_req( params );
			}
			return false;
		} );
	};

	let opts = jQuery.extend( {}, options );
	initialise();

	return this;
};

jQuery.fn.icwpWpsfIpRules = function ( options ) {

	let initialise = function () {

		jQuery( document ).on( 'click', 'td.ip_linked a.ip_delete', function ( evt ) {

			if ( confirm( opts[ 'strings' ][ 'are_you_sure' ] ) ) {
				let reqData = jQuery.extend(
					opts[ 'ajax' ][ 'ip_rule_delete' ],
					{
						'rid': jQuery( evt.currentTarget ).data( 'rid' )
					}
				);
				jQuery.post( ajaxurl, reqData,
					function ( response ) {

						let msg = 'Communications error with site.';
						if ( response.success ) {
							msg = response.data.message;
							alert( msg );
							if ( response.data.page_reload ) {
								location.reload();
							}
						}
						else {
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
			}
		} );

		document.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			if ( typeof evt.target.id !== 'undefined' && evt.target.id === ipRuleAddFormSelector ) {

				let reqData = jQuery.extend(
					opts[ 'ajax' ][ 'ip_rule_add_form' ],
					{
						'form_data': Object.fromEntries( new FormData( evt.target ) )
					}
				);
				jQuery.post( ajaxurl, reqData,
					function ( response ) {

						let msg = 'Communications error with site.';
						if ( response.success ) {
							msg = response.data.message;
							alert( msg );
							if ( response.data.page_reload ) {
								location.reload();
							}
						}
						else {
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
			}

			return false;
		} );
	};

	let opts = jQuery.extend( {}, options );
	let ipRuleAddFormSelector = 'IpRuleAddForm';
	initialise();

	return this;
};