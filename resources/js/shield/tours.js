jQuery.fn.icwpWpsfTours = function ( options ) {

	let setupAllTours = function () {
		shield_vars_tourmanager.tours.forEach( function ( tour_key, i ) {
			if ( shield_vars_tourmanager.states[ tour_key ].is_available ) {
				setupTour( tour_key );
			}
		} );
	}

	let setupTour = function ( tourKey ) {
		introJs()
		.setOptions( getTourSettings( tourKey ) )
		.onexit( function () {
			markTourFinished( tourKey );
		} )
		.start();
	}

	let buildStepsForTour = function ( tourKey ) {
		let steps = [];
		let tourItems = document.querySelectorAll( '.tour-' + tourKey );
		for ( let i = 0; i < tourItems.length; i++ ) {
			let step = {
				element: tourItems[ i ],
				intro: tourItems[ i ].dataset.intro
			};
			if ( typeof tourItems[ i ].dataset.introtitle !== typeof undefined ) {
				step.title = tourItems[ i ].dataset.introtitle;
			}
			if ( typeof tourItems[ i ].dataset.introposition !== typeof undefined ) {
				step.position = tourItems[ i ].dataset.introposition;
			}
			steps.push( step );
		}
		return steps;
	}

	let getTourSettings = function ( tourKey ) {
		return {
			steps: buildStepsForTour( tourKey ),
			overlayOpacity: 0.7,
			highlightClass: "tour-" + tourKey,
			tooltipClass: "shield_tour_tooltip",
			showProgress: true,
			scrollToElement: true
		}
	};

	let markTourFinished = function ( tourKey ) {
		shield_vars_tourmanager.ajax[ 'tour_key' ] = tourKey;
		jQuery.post( ajaxurl, shield_vars_tourmanager.ajax );
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			setupAllTours();
		} );
	};

	initialise();

	return this;
};