jQuery.fn.icwpWpsfTours = function ( options ) {

	var setupAllTours = function ( forceShow = false ) {
		shield_vars_tourmanager.tours.forEach( function ( tour_key, i ) {
			if ( forceShow || !(tour_key in shield_vars_tourmanager.tour_states) ) {
				setupTour( tour_key, forceShow );
			}
		} );
	}

	var setupTour = function ( tourKey ) {
		introJs().setOptions( getTourSettings( tourKey ) )
				 .onexit( function () {
					 markTourFinished( tourKey );
				 } )
				 .start();
	}

	var buildStepsForTour = function ( tourKey ) {
		let steps = [];
		let tourItems = document.querySelectorAll( '.tour-' + tourKey );
		for ( var i = 0; i < tourItems.length; i++ ) {
			let step = {
				element: tourItems[ i ],
				intro: tourItems[ i ].dataset.intro
			};
			if ( typeof tourItems[ i ].dataset.introtitle !== typeof undefined ) {
				step.title = tourItems[ i ].dataset.introtitle;
			}
			steps.push(step);
		}
		return steps;
	}

	var getTourSettings = function ( tourKey ) {
		return {
			steps: buildStepsForTour( tourKey ),
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