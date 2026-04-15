import introJs from "intro.js";
import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

/**
 * @typedef {Object} ShieldTourStepData
 * @property {string} selector
 * @property {string} title
 * @property {string} intro
 * @property {string} position
 * @property {boolean} required
 */

/**
 * @typedef {Object} ShieldTourData
 * @property {string} key
 * @property {boolean} is_available
 * @property {ShieldTourStepData[]} steps
 * @property {Record<string, any>} options
 */

/**
 * @typedef {Object} ShieldToursBaseData
 * @property {{ finished: Record<string, any> }} ajax
 * @property {{ tour: ShieldTourData }} vars
 */

/**
 * @extends {BaseComponent<ShieldToursBaseData>}
 */
export class Tours extends BaseComponent {

	init() {
		const tour = this._base_data.vars.tour;
		if ( tour.is_available !== true || this.hasSecurityAdminOverlay() ) {
			return;
		}

		const steps = this.buildStepsForTour( tour.steps );
		if ( steps === null || steps.length < 1 ) {
			return;
		}

		this.setupTour( tour.key, steps, tour.options );
	}

	/**
	 * @param {string} tourKey
	 * @param {Array<Record<string, any>>} steps
	 * @param {Record<string, any>} options
	 */
	setupTour( tourKey, steps, options ) {
		let markedComplete = false;
		const markComplete = () => {
			if ( markedComplete ) {
				return;
			}
			markedComplete = true;
			this.markTourFinished( tourKey );
		};

		introJs.tour()
		.setOptions( {
			...options,
			steps: steps
		} )
		.onExit( markComplete )
		.onSkip( markComplete )
		.onComplete( markComplete )
		.start()
		.catch( ( error ) => console.log( error ) );
	}

	/**
	 * @param {ShieldTourStepData[]} tourItems
	 * @returns {Array<Record<string, any>>|null}
	 */
	buildStepsForTour( tourItems ) {
		const steps = [];
		for ( let i = 0; i < tourItems.length; i++ ) {
			const tourItem = tourItems[ i ];
			const targetElement = document.querySelector( tourItem.selector );
			if ( targetElement === null ) {
				if ( tourItem.required === true ) {
					return null;
				}
				continue;
			}

			const step = {
				element: targetElement,
				intro: tourItem.intro,
				title: tourItem.title,
				position: tourItem.position
			};
			steps.push( step );
		}
		return steps;
	}

	hasSecurityAdminOverlay() {
		return document.getElementById( 'SecurityAdminOverlay' ) !== null;
	}

	/**
	 * @param {string} tourKey
	 */
	markTourFinished( tourKey ) {
		const req = ObjectOps.ObjClone( this._base_data.ajax.finished );
		req[ 'tour_key' ] = tourKey;
		( new AjaxService() ).bg( req ).finally();
	}
}
