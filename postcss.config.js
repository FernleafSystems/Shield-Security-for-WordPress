const purgecss = require('@fullhuman/postcss-purgecss');

module.exports = {
	plugins: [
		purgecss({
			// Files to scan for CSS class usage
			content: [
				'./templates/**/*.twig',
				'./templates/**/*.php',
				'./src/**/*.php',
				'./assets/**/*.js',
				'./assets/**/*.scss',
				'./*.php'
			],

			// Comprehensive safelist for all dynamically-generated classes
			safelist: {
				// Exact matches and simple patterns
				standard: [
					// ===================
					// BOOTSTRAP 5 DYNAMIC
					// ===================
					// Modal
					'modal-open',
					'modal-static',
					'modal-backdrop',

					// General states (used by many components)
					'show',
					'fade',
					'active',
					'disabled',
					'collapsed',
					'collapsing',
					'collapse-horizontal',

					// Offcanvas
					'showing',
					'hiding',
					'offcanvas-backdrop',

					// Toast
					'hide',

					// Carousel
					'carousel-item-next',
					'carousel-item-prev',
					'carousel-item-start',
					'carousel-item-end',
					'pointer-event',

					// Tooltip/Popover elements
					'tooltip',
					'tooltip-arrow',
					'tooltip-inner',
					'popover',
					'popover-arrow',
					'popover-header',
					'popover-body',

					// ===================
					// DATATABLES CORE
					// ===================
					'dataTable',
					'current',
					'processing',
					'odd',
					'even',
					'selected',
					'highlight',

					// ===================
					// TOASTIFY
					// ===================
					'toastify',
					'on',

					// ===================
					// DATEPICKER
					// ===================
					'datepicker',
					'days',
					'weeks',
					'months',
					'dow',
					'week',
					'day',
					'prev',
					'next',
					'focused',
					'range',
					'range-start',
					'range-end',
					'today',
					'highlighted',
					'view-switch',
					'prev-button',
					'next-button',
					'in-edit',

					// ===================
					// SMARTWIZARD
					// ===================
					'sw',
					'done',
					'error',
					'warning',
					'hidden',
					'default',
					'num',

					// ===================
					// LOADING OVERLAY
					// ===================
					'overlay',
					'spinner',

					// ===================
					// MICROMODAL
					// ===================
					'is-open',
				],

				// Regex patterns for class families
				deep: [
					// ===================
					// BOOTSTRAP 5 PATTERNS
					// ===================
					// Tooltip placement
					/^bs-tooltip-/,
					// Popover placement
					/^bs-popover-/,
					// Dropdown positioning
					/^dropdown-menu-(start|end)$/,
					/^dropdown-menu-(sm|md|lg|xl|xxl)-(start|end)$/,
					// Offcanvas direction
					/^offcanvas-(start|end|top|bottom)$/,
					// Modal sizes
					/^modal-(sm|lg|xl|fullscreen)/,

					// ===================
					// DATATABLES PATTERNS
					// ===================
					// Core dt-* classes
					/^dt-/,
					// Sorting indicators
					/^sorting/,
					// Pagination
					/^paginate/,
					/^page-/,
					// Buttons extension
					/^buttons-/,
					// Select extension
					/^select-/,
					// SearchPanes extension
					/^dtsp-/,
					// DataTables responsive
					/^dtr-/,
					// Child row details
					/^child/,

					// ===================
					// SELECT2 PATTERNS
					// ===================
					/^select2-/,

					// ===================
					// SMARTWIZARD PATTERNS
					// ===================
					/^sw-/,
					/^toolbar/,

					// ===================
					// INTRO.JS PATTERNS
					// ===================
					/^introjs-/,
					/^introjsFloatingElement/,

					// ===================
					// TOASTIFY PATTERNS
					// ===================
					/^toastify-/,
					/^toast-/,

					// ===================
					// DATEPICKER PATTERNS
					// ===================
					/^datepicker-/,

					// ===================
					// LOADING OVERLAY PATTERNS
					// ===================
					/^la-/,
					/^loading-overlay/,

					// ===================
					// BIGPICTURE PATTERNS
					// ===================
					/^bp-/,

					// ===================
					// BOOTSTRAP UTILITIES (commonly dynamically applied)
					// ===================
					// Display utilities
					/^d-(none|block|flex|inline|grid)/,
					// Visibility
					/^visible/,
					/^invisible/,
				],

				// Greedy patterns (matches any selector containing these)
				greedy: [
					// Data attributes used by Bootstrap JS
					/data-bs-/,
					// Micromodal data attributes
					/data-micromodal/,
				]
			},

			// Keep CSS variables
			variables: true,

			// Keep @font-face rules
			fontFace: true,

			// Keep @keyframes
			keyframes: true,
		})
	]
};
