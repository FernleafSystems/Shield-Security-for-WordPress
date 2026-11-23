import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { OffCanvasService } from "../ui/OffCanvasService";

/**
 * An Actions Queue row whose name opens the item in a drawer. The drawer says which item it shows, and a button in
 * it stands for that item's own row button of the same kind: pressing it presses the row's, so the queue handles
 * it exactly as it does from the row, and the drawer closes.
 */
export class ActionsQueueRowDrawer extends BaseAutoExecComponent {

	canRun() {
		return shieldServices.container_ShieldPage();
	}

	run() {
		shieldEventsHandler_Main.add_Click( '[data-actions-queue-row-drawer]', ( targetEl ) => {
			const request = this.drawerRequestOf( targetEl );
			if ( request !== null ) {
				OffCanvasService.RenderCanvas( request, { launcher: targetEl } ).finally();
			}
		} );

		shieldEventsHandler_Main.add_Click( '#AptoOffcanvas [data-actions-queue-drawer-press]', ( targetEl ) => {
			// The item is the one the drawer is showing, whichever row was pressed to open it, and its row is found
			// afresh, as the queue may have redrawn its rows since.
			const shown = targetEl.closest( '[data-actions-queue-drawer-for]' )?.dataset.actionsQueueDrawerFor || '';
			const opener = [ ...document.querySelectorAll( '[data-actions-queue-row-drawer]' ) ]
			.find( ( el ) => shown !== '' && this.drawerRequestOf( el )?.identifier === shown );
			const rowButton = opener?.closest( '.actions-queue-row' )?.querySelector(
				`[data-actions-queue-maintenance-kind="${targetEl.dataset.actionsQueueDrawerPress || ''}"][data-actions-queue-maintenance-action]`
			);
			OffCanvasService.CloseCanvas();
			if ( rowButton instanceof HTMLElement ) {
				rowButton.click();
			}
		} );
	}

	/**
	 * The request a row's name makes for its drawer; null when it isn't one.
	 */
	drawerRequestOf( el ) {
		try {
			const request = JSON.parse( el instanceof HTMLElement ? el.dataset.actionsQueueRowDrawer || '' : '' );
			return request !== null && typeof request === 'object' ? request : null;
		}
		catch ( e ) {
			return null;
		}
	}
}
