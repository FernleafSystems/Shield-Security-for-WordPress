var iCWP_WPSF_OptionsPages = new function () {

	var showWaiting = function ( event ) {
		/* var $oLink = jQuery( this ); for the inner collapses
		jQuery( '#' + $oLink.data( 'targetcollapse' ) ).collapse( 'show' ); */

		iCWP_WPSF_BodyOverlay.show();
	};

	var moveCarousel0 = function ( event ) {
		moveCarousel( 0 );
	};
	var moveCarousel1 = function ( event ) {
		moveCarousel( 1 );
	};
	var moveCarousel2 = function ( event ) {
		moveCarousel( 2 );
	};
	var moveCarousel3 = function ( event ) {
		moveCarousel( 3 );
	};

	var moveCarousel = function ( nSlide ) {
		jQuery( '.icwp-carousel' ).carousel( nSlide );
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "click", "a.nav-link.module", showWaiting );
			jQuery( document ).on( "click", "a.icwp-carousel-0", moveCarousel0 );
			jQuery( document ).on( "click", "a.icwp-carousel-1", moveCarousel1 );
			jQuery( document ).on( "click", "a.icwp-carousel-2", moveCarousel2 );
			jQuery( document ).on( "click", "a.icwp-carousel-3", moveCarousel3 );

			/** Track active tab */
			jQuery( document ).on( "click", "#ModuleOptionsNav a.nav-link", function ( e ) {
				e.preventDefault();
				jQuery( this ).tab( 'show' );
				jQuery( 'html,body' ).scrollTop( 0 );
			} );
			jQuery( document ).on( "shown.bs.tab", "#ModuleOptionsNav a.nav-link", function ( e ) {
				window.location.hash = jQuery( e.target ).attr( "href" ).substr( 1 );
			} );

			var sActiveTabHash = window.location.hash;
			if ( sActiveTabHash ) {
				jQuery( '#ModuleOptionsNav a[href="' + window.location.hash + '"]' ).tab( 'show' );
			}
		} );
	};

}();

var iCWP_WPSF_OptionsFormSubmit = new function () {

	var bRequestCurrentlyRunning = false;

	this.submit = function ( sMessage, bSuccess ) {
		var $oDiv = createDynDiv( bSuccess ? 'success' : 'failed' );
		$oDiv.fadeIn().html( sMessage );
		setTimeout( function () {
			$oDiv.fadeOut( 5000 );
			$oDiv.remove();
		}, 4000 );
	};

	/**
	 */
	var submitOptionsForm = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		if ( bRequestCurrentlyRunning ) {
			return false;
		}
		bRequestCurrentlyRunning = true;
		event.preventDefault();

		var $oForm = jQuery( this );
		jQuery.post( ajaxurl, $oForm.serialize(),
			function ( oResponse ) {
				var sMessage;
				if ( oResponse.data.message === undefined ) {
					sMessage = oResponse.success ? 'Success' : 'Failure';
				}
				else {
					sMessage = oResponse.data.message;
				}
				iCWP_WPSF_Growl.showMessage( sMessage, oResponse.success );
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
				location.reload( true );
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", "form.icwpOptionsForm", submitOptionsForm );
		} );
	};
}();

var iCWP_WPSF_InsightsAdminNotes = new function () {

	var bRequestCurrentlyRunning = false;

	/**
	 */
	var renderNotes = function ( event ) {

		jQuery.post( ajaxurl, icwp_wpsf_vars_insights.ajax_admin_notes_render,
			function ( oResponse ) {
				if ( oResponse.success ) {
					jQuery( '#AdminNotesContainer' ).html( oResponse.data.html );
					jQuery( '.cell_delete_note button' ).tooltip( {
						placement: 'left',
						trigger: 'hover'
					} );
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
				}
			}
		).always( function () {
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	/**
	 */
	var deleteNote = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		icwp_wpsf_vars_insights.ajax_admin_notes_delete.note_id = jQuery( this ).data( 'note_id' );

		jQuery.post( ajaxurl, icwp_wpsf_vars_insights.ajax_admin_notes_delete,
			function ( oResponse ) {
				if ( oResponse.success ) {
					renderNotes();
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		).always( function () {
			}
		);
	};

	/**
	 */
	var submitForm = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

		if ( bRequestCurrentlyRunning ) {
			return false;
		}
		bRequestCurrentlyRunning = true;
		event.preventDefault();

		jQuery.post( ajaxurl, jQuery( this ).serialize(),
			function ( oResponse ) {
				if ( oResponse.success ) {
					renderNotes(); // this will remove the overlay
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
			}
		);
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "keydown", "form#NewAdminNote", function ( e ) {
				/* if ( e.ctrlKey && e.keyCode === 13 ) {
					can't get ctrl+return to submit!
					console.log( e );
					submitForm( e );
				} */
			} );
			jQuery( document ).on( "submit", "form#NewAdminNote", submitForm );
			jQuery( document ).on( "click", ".btn.note_delete", deleteNote );
		} );
	};
}();

iCWP_WPSF_OptionsPages.initialise();
iCWP_WPSF_OptionsFormSubmit.initialise();
iCWP_WPSF_InsightsAdminNotes.initialise();
