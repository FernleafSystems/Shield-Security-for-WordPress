import $ from 'jquery';
import 'select2';

export class InvestigateLookupSelect2 {

	initializeWithin( contextEl ) {
		if ( contextEl === null || !$.fn.select2 ) {
			return;
		}

		contextEl.querySelectorAll( 'select[data-investigate-select2="1"]' ).forEach( ( selectEl ) => {
			const $select = $( selectEl );
			const shouldAutoSubmit = selectEl.dataset.investigateAutoSubmit === '1';
			if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
				if ( shouldAutoSubmit ) {
					this.bindAutoSubmit( selectEl, $select );
				}
				return;
			}

			const firstOption = selectEl.querySelector( 'option[value=""]' );
			const placeholder = firstOption ? ( firstOption.textContent || '' ) : '';
			const ajaxContract = this.parseAjaxConfig( selectEl.dataset.investigateSelect2Ajax || '' );
			const select2Config = {
				width: '100%',
				placeholder,
			};

			if ( ajaxContract !== null ) {
				select2Config.minimumInputLength = ajaxContract.minimumInputLength;
				select2Config.ajax = this.buildAjaxConfig( ajaxContract );
			}

			$select.select2( select2Config );

			if ( shouldAutoSubmit ) {
				this.bindAutoSubmit( selectEl, $select );
			}
		} );
	}

	bindAutoSubmit( selectEl, $select ) {
		$select.off( 'select2:select.investigateAutoSubmit select2:clear.investigateAutoSubmit' );
		$select.on( 'select2:select.investigateAutoSubmit select2:clear.investigateAutoSubmit', () => {
			const form = selectEl.closest( 'form[data-investigate-panel-form="1"]' );
			if ( form !== null ) {
				form.requestSubmit();
			}
		} );
	}

	parseAjaxConfig( rawJson ) {
		if ( typeof rawJson !== 'string' || rawJson.trim().length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawJson );
			const action = ( parsed.action && typeof parsed.action === 'object' ) ? parsed.action : null;
			const subject = typeof parsed.subject === 'string' ? parsed.subject.trim() : '';
			const ajaxUrl = typeof action?.ajaxurl === 'string' ? action.ajaxurl : '';
			if ( action === null || subject.length < 1 || ajaxUrl.length < 1 ) {
				return null;
			}

			return {
				action,
				subject,
				ajaxUrl,
				minimumInputLength: Math.max( 1, Number.isInteger( parsed.minimum_input_length ) ? parsed.minimum_input_length : 2 ),
				delayMs: Math.max( 0, Number.isInteger( parsed.delay_ms ) ? parsed.delay_ms : 700 ),
			};
		}
		catch ( e ) {
			return null;
		}
	}

	buildAjaxConfig( ajaxContract ) {
		return {
			type: 'POST',
			delay: ajaxContract.delayMs,
			url: ajaxContract.ajaxUrl,
			dataType: 'json',
			data: ( params ) => this.buildQuery( ajaxContract, params?.term || '' ),
			processResults: ( response ) => {
				return {
					results: Array.isArray( response?.data?.results ) ? response.data.results : [],
				};
			},
		};
	}

	buildQuery( ajaxContract, term ) {
		return {
			...ajaxContract.action,
			subject: ajaxContract.subject,
			search: typeof term === 'string' ? term : '',
		};
	}
}
