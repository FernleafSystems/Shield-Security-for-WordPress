import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
		this.tooltips();
	}

	tooltips() {
		const primaryContainer = document.getElementById( 'PageContainer-Apto' ) || false;
		if ( primaryContainer ) {
			BootstrapTooltips.RegisterNewTooltipsWithin( primaryContainer );
		}
	}

	static RegisterNewTooltipsWithin( container ) {
		BootstrapTooltips.collectTooltipTargetsWithin( container ).forEach( ( targetEl ) => {
			if ( !BootstrapTooltips.HasTooltipTitle( targetEl ) ) {
				return;
			}
			Tooltip.getOrCreateInstance( targetEl );
		} );
	}

	static HasTooltipTitle( targetEl ) {
		const title = targetEl.getAttribute( 'data-bs-title' )
			?? targetEl.getAttribute( 'title' )
			?? targetEl.getAttribute( 'data-bs-original-title' );
		return typeof title === 'string' && title.trim().length > 0;
	}

	static DisposeTooltipsWithin( container ) {
		BootstrapTooltips.collectTooltipTargetsWithin( container )
		.forEach( ( targetEl ) => BootstrapTooltips.HideAndDisposeTooltip( targetEl ) );
	}

	static DisposeFloatingUiWithin( container ) {
		BootstrapTooltips.DisposeTooltipsWithin( container );
		BootstrapTooltips.DisposePopoversWithin( container );
	}

	static DisposePopoversWithin( container ) {
		BootstrapTooltips.collectPopoverTargetsWithin( container )
		.forEach( ( targetEl ) => BootstrapTooltips.HideAndDisposePopover( targetEl ) );
	}

	static HideAndDisposeTooltip( targetEl ) {
		const describedBy = targetEl instanceof Element ? targetEl.getAttribute( 'aria-describedby' ) : '';
		const tip = Tooltip.getInstance( targetEl );
		if ( tip ) {
			tip.dispose();
		}
		BootstrapTooltips.RemoveDescribedFloatingElement( describedBy );
		if ( targetEl instanceof Element ) {
			targetEl.removeAttribute( 'aria-describedby' );
		}
	}

	static HideAndDisposePopover( targetEl ) {
		const describedBy = targetEl instanceof Element ? targetEl.getAttribute( 'aria-describedby' ) : '';
		const targetHTMLElement = targetEl instanceof HTMLElement ? targetEl : null;
		if ( targetHTMLElement?.dataset.shieldPopoverDisposing === '1' ) {
			return;
		}

		const popover = Popover.getInstance( targetEl );
		if ( !popover ) {
			BootstrapTooltips.RemoveDescribedFloatingElement( describedBy );
			if ( targetEl instanceof Element ) {
				targetEl.removeAttribute( 'aria-describedby' );
			}
			return;
		}

		const disposePopover = () => {
			if ( Popover.getInstance( targetEl ) === popover ) {
				popover.dispose();
			}
			BootstrapTooltips.RemoveDescribedFloatingElement( describedBy );
			if ( targetHTMLElement !== null ) {
				delete targetHTMLElement.dataset.shieldPopoverDisposing;
				targetHTMLElement.removeAttribute( 'aria-describedby' );
			}
		};

		const describedEl = BootstrapTooltips.FindDescribedFloatingElement( describedBy );
		if ( describedEl instanceof Element && describedEl.classList.contains( 'show' ) ) {
			if ( targetHTMLElement !== null ) {
				targetHTMLElement.dataset.shieldPopoverDisposing = '1';
			}
			popover.disable();
			targetEl.addEventListener( 'hidden.bs.popover', () => {
				window.setTimeout( disposePopover, 0 );
			}, { once: true } );
			popover.hide();
		}
		else {
			disposePopover();
		}
	}

	static RemoveDescribedFloatingElement( describedBy ) {
		BootstrapTooltips.FindDescribedFloatingElement( describedBy )?.remove();
	}

	static FindDescribedFloatingElement( describedBy ) {
		if ( typeof describedBy !== 'string' || describedBy.length < 1 ) {
			return null;
		}

		const describedEl = document.getElementById( describedBy );
		return describedEl instanceof Element && describedEl.matches( '.tooltip, .popover' )
			? describedEl
			: null;
	}

	static collectTooltipTargetsWithin( container ) {
		return BootstrapTooltips.collectTargetsWithin( container, '[data-bs-toggle="tooltip"]' );
	}

	static collectPopoverTargetsWithin( container ) {
		return [
			...BootstrapTooltips.collectTargetsWithin( container, '[data-toggle="popover"]' ),
			...BootstrapTooltips.collectTargetsWithin( container, '[data-bs-toggle="popover"]' )
		].filter( ( targetEl, index, targets ) => targets.indexOf( targetEl ) === index );
	}

	static collectTargetsWithin( container, selector ) {
		const root = container instanceof Element || container instanceof Document
			? container
			: null;
		if ( root === null ) {
			return [];
		}

		const targets = [];
		if ( root instanceof Element && root.matches( selector ) ) {
			targets.push( root );
		}

		root.querySelectorAll( selector ).forEach( ( targetEl ) => {
			if ( !targets.includes( targetEl ) ) {
				targets.push( targetEl );
			}
		} );

		return targets;
	}
}
