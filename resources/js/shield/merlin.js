(function ( $ ) {
	let $merlinContainer;

	$( document ).ready( function () {
		init();
	} );

	function init() {
		$merlinContainer = $( '#merlin' );
		$merlinContainer.smartWizard( {
			selected: 0, // Initial selected step, 0 = first step
			theme: 'arrows', // theme for the wizard, related css need to include for other than default theme
			justified: true, // Nav menu justification. true/false
			autoAdjustHeight: true, // Automatically adjust content height
			backButtonSupport: true, // Enable the back button support
			enableUrlHash: true, // Enable selection of the step based on url hash
		} );

		$( 'form', $merlinContainer ).on( 'submit', runSettingUpdate );
	}

	let runSettingUpdate = function ( evt ) {
		evt.preventDefault();
		merlin.ajax.merlin_action.form_params = $( evt.target ).serialize();
		iCWP_WPSF_StandardAjax.send_ajax_req( merlin.ajax.merlin_action );
		return false;
	};

	function initializeSteps() {
		var settings = {
			/* Appearance */
			headerTag: "h1",
			bodyTag: "div",
			contentContainerTag: "div",
			actionContainerTag: "div",
			stepsContainerTag: "div",
			cssClass: "wizard",
			stepsOrientation: $.fn.steps.stepsOrientation.horizontal,

			/* Templates */
			titleTemplate: '<div class="step"><span class="shield-progress-bar"></span><div class="step-number"></div><div class="step-title">#title#</div></div>',
			loadingTemplate: '<span class="spinner"></span> #text#',

			/* Behaviour */
			autoFocus: false,
			enableAllSteps: false,
			enableKeyNavigation: true,
			enablePagination: true,
			suppressPaginationOnFocus: true,
			enableContentCache: true,
			enableCancelButton: false,
			enableFinishButton: false,
			preloadContent: false,
			showFinishButtonAlways: false,
			forceMoveForward: false,
			saveState: false,
			startIndex: 0,

			/* Transition Effects */
			transitionEffect: $.fn.steps.transitionEffect.slide,
			transitionEffectSpeed: 100,

			/* Events */
			onStepChanging: function ( event, currentIndex, newIndex ) {
				return true;
			},
			onStepChanged: function ( event, currentIndex, priorIndex ) {
				if ( currentIndex >= priorIndex ) {
					return;
				}
				// have to use this workaround
				// instead of removing steps and then adding, which does not work with jquery.steps
				// remove effectively does something similar internally so let's just use it directly
				for ( var x = 1; x < 6; x++ ) {
					$( '#wizard-t-' + (currentIndex + x) ).parent( 'li' ).removeClass( 'done' ).addClass( 'disabled' );
				}
			},
			onCanceled: function ( event ) {
			},
			onFinishing: function ( event, currentIndex ) {
				return true;
			},
			onFinished: function ( event, currentIndex ) {
			},

			/* Labels */
			labels: {
				cancel: "Cancel",
				current: "current step:",
				pagination: "Pagination",
				finish: "Finish",
				next: "Next",
				previous: "Previous",
				loading: "Loading ..."
			}
		};
	}

})( jQuery );