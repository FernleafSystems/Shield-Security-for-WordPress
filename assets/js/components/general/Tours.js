import introJs from "intro.js";
import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class Tours extends BaseComponent {

	init() {
		this._base_data.vars.tours.forEach( ( tour_key, i ) => {
			if ( this._base_data.vars.states[ tour_key ].is_available ) {
				this.setupTour( tour_key );
			}
		} );
	}

	setupTour( tourKey ) {
		introJs()
		.setOptions( {
			steps: this.buildStepsForTour( tourKey ),
			overlayOpacity: 0.7,
			highlightClass: "tour-" + tourKey,
			tooltipClass: "shield_tour_tooltip",
			showProgress: true,
			scrollToElement: true
		} )
		.onexit( () => {
			this.#markTourFinished( tourKey );
		} )
		.start();
	}

	buildStepsForTour( tourKey ) {
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

	#markTourFinished( tourKey ) {
		this._base_data.ajax.finished[ 'tour_key' ] = tourKey;
		( new AjaxService() ).bg( this._base_data.ajax.finished ).finally();
	};
}