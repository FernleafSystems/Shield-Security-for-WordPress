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
			( trigger ) => this.handleTriggerClick( trigger ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-row="1"]',
			( row, evt ) => this.handleRowClick( row, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-close="1"]',
			( button ) => this.handleCloseClick( button ),
			false
		);
		document.addEventListener(
			'shown.bs.collapse',
			( evt ) => this.handleCollapseEvent( evt, true ),
			false
		);
		document.addEventListener(
			'hidden.bs.collapse',
			( evt ) => this.handleCollapseEvent( evt, false ),
			false
		);
	}

	handleTriggerClick( trigger ) {
		this.toggleExpandForTrigger( trigger );
	}

	handleCloseClick( button ) {
		const expansion = button.closest( '[data-shield-expand-body="1"]' );
		if ( expansion !== null ) {
			Collapse.getOrCreateInstance( expansion, { toggle: false } ).hide();
		}
	}

	handleRowClick( row, evt ) {
		if ( this.shouldIgnoreRowShortcut( row, evt ) ) {
			return;
		}

		this.toggleExpandForRow( row );
	}

	handleCollapseEvent( evt, isExpanded ) {
		const expansion = evt.target;
		if ( !( expansion instanceof Element ) || !expansion.matches( '[data-shield-expand-body="1"]' ) ) {
			return;
		}

		if ( isExpanded ) {
			this.handleCollapseShown( expansion );
		}
		else {
			this.handleCollapseHidden( expansion );
		}
	}

	shouldIgnoreRowShortcut( row, evt ) {
		const target = evt.target;
		if ( !( target instanceof Element ) || !row.contains( target ) ) {
			return true;
		}

		const expandTrigger = target.closest( '[data-shield-expand-trigger="1"]' );
		if ( expandTrigger !== null && row.contains( expandTrigger ) ) {
			return true;
		}

		const interactive = target.closest( 'a, button, input, select, textarea, summary, [role="button"], [role="link"], [tabindex]' );
		return interactive !== null && row.contains( interactive );
	}

	toggleExpandForTrigger( trigger ) {
		const expansion = this.findExpansionForTrigger( trigger );
		if ( expansion === null ) {
			return;
		}

		Collapse.getOrCreateInstance( expansion, { toggle: false } ).toggle();
	}

	toggleExpandForRow( row ) {
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
		this.setTriggerExpanded( row, true );
		UiContentActivator.activateCurrentSubtree( expansion );
	}

	handleCollapseHidden( expansion ) {
		BootstrapTooltips.DisposeTooltipsWithin( expansion );

		const row = this.findRowForExpansion( expansion );
		if ( row !== null ) {
			row.classList.remove( 'is-expanded' );
			this.setTriggerExpanded( row, false );
		}
	}

	findExpansionForTrigger( trigger ) {
		const targetId = ( trigger.getAttribute( 'aria-controls' ) || '' ).trim();
		return targetId.length > 0 ? document.getElementById( targetId ) : null;
	}

	findExpansionForRow( row ) {
		const targetId = ( row.dataset.shieldExpandTarget || '' ).trim();
		return targetId.length > 0 ? document.getElementById( targetId ) : null;
	}

	setTriggerExpanded( row, isExpanded ) {
		const trigger = this.findTriggerForRow( row );
		if ( trigger !== null ) {
			trigger.setAttribute( 'aria-expanded', isExpanded ? 'true' : 'false' );
		}
	}

	findTriggerForRow( row ) {
		const trigger = row.querySelector( '[data-shield-expand-trigger="1"]' );
		return trigger instanceof HTMLElement ? trigger : null;
	}

	findRowForExpansion( expansion ) {
		const itemWrapper = expansion.closest( '.shield-detail-item' );
		if ( itemWrapper !== null ) {
			const row = itemWrapper.querySelector( '[data-shield-expand-row="1"]' );
			if ( row !== null ) {
				return row;
			}
		}

		return expansion.id.length > 0
			? document.querySelector( `[data-shield-expand-row="1"][data-shield-expand-target="${expansion.id}"]` )
			: null;
	}
}
