import { Collapse } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

export class DetailRowExpandController extends BaseAutoExecComponent {

	canRun() {
		return true;
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
		shieldEventsHandler_Main.addHandler(
			'shown.bs.collapse',
			'[data-shield-expand-body="1"]',
			( expansion ) => this.handleCollapseShown( expansion ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'hidden.bs.collapse',
			'[data-shield-expand-body="1"]',
			( expansion ) => this.handleCollapseHidden( expansion ),
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
			Collapse.getOrCreateInstance( expansion, { toggle: false } ).hide();
		}
	}

	toggleExpand( row ) {
		const expansion = this.findExpansionForRow( row );
		if ( expansion === null ) {
			return;
		}

		Collapse.getOrCreateInstance( expansion, { toggle: false } ).toggle();
	}

	handleCollapseShown( expansion ) {
		const row = this.findRowForExpansion( expansion );
		if ( row === null ) {
			return;
		}

		row.classList.add( 'is-expanded' );
		row.setAttribute( 'aria-expanded', 'true' );
		UiContentActivator.activateCurrentSubtree( expansion );
	}

	handleCollapseHidden( expansion ) {
		BootstrapTooltips.DisposeTooltipsWithin( expansion );

		const row = this.findRowForExpansion( expansion );
		if ( row !== null ) {
			row.classList.remove( 'is-expanded' );
			row.setAttribute( 'aria-expanded', 'false' );
		}
	}

	findExpansionForRow( row ) {
		const targetId = ( row.dataset.shieldExpandTarget || '' ).trim();
		return targetId.length > 0 ? document.getElementById( targetId ) : null;
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
