jQuery.fn.icwpWpsfTours = function ( options ) {

	var setupAllTours = function ( forceShow = false ) {
		shield_vars_tourmanager.tours.forEach( function ( tour_key, i ) {
			if ( forceShow || !(tour_key in shield_vars_tourmanager.tour_states) ) {
				setupTour( tour_key, forceShow );
			}
		} );
	}

	var setupTour = function ( tour_key ) {
		introJs().setOptions( getTourSettings( tour_key ), )
				 .onexit( function () {
					 markTourFinished( tour_key );
				 } )
				 .start();
	}

	var getTourSettings = function ( tourKey ) {
		return {
			overlayOpacity: 0.7,
			highlightClass: "tour-" + tourKey,
			tooltipClass: "shield_tour_tooltip",
			showProgress: true,
			scrollToElement: true
		}
	};

	var markTourFinished = function ( tourKey ) {
		shield_vars_tourmanager.ajax[ 'tour_key' ] = tourKey;
		jQuery.post( ajaxurl, shield_vars_tourmanager.ajax );
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			setupAllTours( false );
		} );
	};

	initialise();

	return this;
};