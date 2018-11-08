var iCWP_WPSF_InsightsAdminNotes = new function () {

	var bRequestCurrentlyRunning = false;

	/**
	 */
	var renderNotes = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();

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
			jQuery( document ).ready( function () {
				renderNotes();
			} );
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
iCWP_WPSF_InsightsAdminNotes.initialise();