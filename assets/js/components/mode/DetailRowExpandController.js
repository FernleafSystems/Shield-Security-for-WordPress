import { Tooltip } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

export class DetailRowExpandController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-shield-expand-trigger="1"]' ) !== null;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-trigger="1"]',
			( row, evt ) => this.handleRowClick( row, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-close="1"]',
			( button ) => this.handleCloseClick( button ),
			false
		);
		shieldEventsHandler_Main.add_Keyup(
			'[data-shield-expand-trigger="1"]',
			( row, evt ) => this.handleRowKeyup( row, evt ),
			false
		);
	}

	handleRowClick( row, evt ) {
		if ( evt.target.closest( '.shield-action-chip' ) ) {
			return;
		}
		this.toggleExpand( row );
	}

	handleRowKeyup( row, evt ) {
		if ( evt.target !== row ) {
			return;
		}

		if ( evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar' ) {
			evt.preventDefault();
			this.toggleExpand( row );
		}
	}

	handleCloseClick( button ) {
		const expansion = button.closest( '[data-shield-expand-body="1"]' );
		if ( expansion !== null ) {
			this.closeExpansion( expansion );
		}
	}

	toggleExpand( row ) {
		const targetId = ( row.dataset.shieldExpandTarget || '' ).trim();
		if ( targetId.length === 0 ) {
			return;
		}

		const expansion = document.getElementById( targetId );
		if ( expansion === null ) {
			return;
		}

		if ( expansion.classList.contains( 'is-open' ) ) {
			this.closeExpansion( expansion );
			return;
		}

		this.closeSiblingExpansions( row, expansion );
		this.openExpansion( row, expansion );
	}

	closeSiblingExpansions( row, currentExpansion ) {
		const itemWrapper = row.closest( '.shield-detail-item' );
		const container = itemWrapper !== null ? itemWrapper.parentElement : null;
		if ( container === null ) {
			return;
		}

		container.querySelectorAll( '[data-shield-expand-body="1"].is-open' ).forEach( ( expansion ) => {
			if ( expansion !== currentExpansion ) {
				this.closeExpansion( expansion );
			}
		} );
	}

	openExpansion( row, expansion ) {
		row.classList.add( 'is-expanded' );
		row.setAttribute( 'aria-expanded', 'true' );

		expansion.classList.add( 'is-open' );
		expansion.setAttribute( 'aria-hidden', 'false' );

		BootstrapTooltips.RegisterNewTooltipsWithin( expansion );

		expansion.dispatchEvent( new CustomEvent( 'shield:expansion-opened', {
			bubbles: true,
			detail: {
				row,
				expansion
			}
		} ) );
	}

	closeExpansion( expansion ) {
		expansion.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( el ) => {
			const tip = Tooltip.getInstance( el );
			if ( tip ) {
				tip.dispose();
			}
		} );

		expansion.classList.remove( 'is-open' );
		expansion.setAttribute( 'aria-hidden', 'true' );

		const row = this.findRowForExpansion( expansion );
		if ( row !== null ) {
			row.classList.remove( 'is-expanded' );
			row.setAttribute( 'aria-expanded', 'false' );
		}
	}

	findRowForExpansion( expansion ) {
		const itemWrapper = expansion.closest( '.shield-detail-item' );
		if ( itemWrapper !== null ) {
			const row = itemWrapper.querySelector( '[data-shield-expand-trigger="1"]' );
			if ( row !== null ) {
				return row;
			}
		}

		return expansion.id.length > 0
			? document.querySelector( `[data-shield-expand-target="${expansion.id}"]` )
			: null;
	}
}
