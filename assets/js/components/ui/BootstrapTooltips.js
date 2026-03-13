import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
		// this.popovers();
		this.tooltips();
	}

	popovers1() {
		/*
		shieldEventsHandler_Main.add_Mouseover(
			'[data-bs-toggle="popover"]',
			( targetEl ) => {
				let po = Popover.getInstance( targetEl );
				if ( !po ) {
					po = Popover.getOrCreateInstance( targetEl );
					targetEl.addEventListener( 'shown.bs.popover', () => {
						alert( 'asdf' );
						window.setTimeout( () => po.hide(), 7000 );
					} );
				}
			},
			false
		);
		 */
	};

	popovers() {
		this.actionTooltips = [];
		shieldEventsHandler_Main.add_Mouseover(
			'[data-bs-toggle="popover"]',
			( targetEl ) => {
				Popover.getOrCreateInstance( targetEl )
			},
			false
		);
	};

	tooltips() {
		const primaryContainer = document.getElementById( 'PageContainer-Apto' ) || false;
		if ( primaryContainer ) {
			BootstrapTooltips.RegisterNewTooltipsWithin( primaryContainer );
		}
		// shieldEventsHandler_Main.add_Mouseover(
		// 	'[data-bs-toggle="tooltip"]',
		// 	( targetEl ) => {
		// 		Tooltip.getOrCreateInstance( targetEl )
		// 	},
		// 	false
		// );
	};

	static RegisterNewTooltipsWithin( container ) {
		BootstrapTooltips.collectTooltipTargetsWithin( container ).forEach( ( targetEl ) => {
			const title = targetEl.getAttribute( 'data-bs-title' ) ?? targetEl.getAttribute( 'title' );
			if ( typeof title !== 'string' || title.trim().length < 1 ) {
				return;
			}
			Tooltip.getOrCreateInstance( targetEl );
		} );
		// console.log( container );
	}

	static DisposeTooltipsWithin( container ) {
		BootstrapTooltips.collectTooltipTargetsWithin( container )
		.forEach( ( targetEl ) => BootstrapTooltips.HideAndDisposeTooltip( targetEl ) );
	}

	static HideAndDisposeTooltip( targetEl ) {
		const tip = Tooltip.getInstance( targetEl );
		if ( tip ) {
			tip.dispose();
		}
		if ( targetEl instanceof Element ) {
			targetEl.removeAttribute( 'aria-describedby' );
		}
	}

	static collectTooltipTargetsWithin( container ) {
		const root = container instanceof Element || container instanceof Document
			? container
			: null;
		if ( root === null ) {
			return [];
		}

		const targets = [];
		if ( root instanceof Element && root.matches( '[data-bs-toggle="tooltip"]' ) ) {
			targets.push( root );
		}

		root.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( targetEl ) => {
			if ( !targets.includes( targetEl ) ) {
				targets.push( targetEl );
			}
		} );

		return targets;
	}
}
