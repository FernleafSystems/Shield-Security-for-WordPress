import $ from 'jquery';
import { BaseService } from "./BaseService";

/**
 * incomplete - needed for custom charts (which have been removed)
 */
export class ChartsWithFilters extends BaseService {
	$chartForm;

	constructor() {
		super();
		this.$chartForm = $( 'form#CustomChart' );
		this.$chartForm.on( 'click', 'input[type=submit]', this.#submitFilters );
		this.$chartForm.on( 'click', 'a#ClearForm', this.#resetFilters );
	}

	retrieveBaseData() {
		return window.icwp_wpsf_vars_plugin.components.charts;
	}

	#resetFilters( evt ) {
		$( 'select', this.$chartForm ).each( function () {
			$( this ).prop( 'selectedIndex', 0 );
		} );
		opts[ 'chart' ].renderChartFromForm( this.$chartForm );
	};

	#submitFilters( evt ) {
		evt.preventDefault();
		opts[ 'chart' ].renderChartFromForm( this.$chartForm );
		return false;
	};
}