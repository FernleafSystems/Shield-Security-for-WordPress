import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

export class ModePanelStateController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-mode-shell="1"][data-mode-interactive="1"] [data-mode-tile="1"]' ) !== null;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-mode-tile="1"]',
			( tile, evt ) => this.handleTileActivation( tile, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-mode-panel-close="1"]',
			( button, evt ) => this.handlePanelClose( button, evt ),
			false
		);
	}

	handleTileActivation( tile, evt, trigger = 'pointer' ) {
		const shell = this.getModeShell( tile );
		if ( shell === null || !this.isInteractiveShell( shell ) ) {
			return;
		}

		if ( this.isDisabledTile( tile ) ) {
			evt.preventDefault();
			return;
		}

		const panelTarget = ( tile.dataset.modePanelTarget || tile.dataset.modeTileKey || '' ).trim();
		if ( panelTarget.length === 0 ) {
			return;
		}

		evt.preventDefault();

		const activePanelTarget = ( shell.dataset.modeActivePanel || '' ).trim();
		if ( activePanelTarget === panelTarget ) {
			this.closePanel( shell, panelTarget, trigger );
			return;
		}

		this.openPanel( shell, tile, panelTarget, trigger );
	}

	handlePanelClose( button, evt ) {
		const shell = this.getModeShell( button );
		if ( shell === null || !this.isInteractiveShell( shell ) ) {
			return;
		}

		evt.preventDefault();
		this.closePanel( shell, ( shell.dataset.modeActivePanel || '' ).trim(), 'close' );
	}

	openPanel( shell, tile, panelTarget, trigger ) {
		const panel = this.findPanelForTarget( shell, panelTarget );
		if ( panel === null ) {
			return;
		}

		this.dispatchModeEvent( shell, 'shield:mode-panel-opening', tile, panelTarget, trigger );
		this.clearShellState( shell );

		tile.classList.add( 'is-active' );
		tile.setAttribute( 'aria-expanded', 'true' );

		panel.dataset.modePanelTarget = panelTarget;
		panel.classList.remove( 'd-none' );
		panel.classList.add( 'is-open' );
		panel.setAttribute( 'aria-hidden', 'false' );

		shell.dataset.modeActivePanel = panelTarget;

		this.dispatchModeEvent( shell, 'shield:mode-panel-opened', tile, panelTarget, trigger );
	}

	closePanel( shell, panelTarget, trigger ) {
		const panel = this.getActivePanel( shell );
		const tile = this.getActiveTile( shell );
		const target = panelTarget.length > 0 ? panelTarget : ( shell.dataset.modeActivePanel || '' ).trim();

		this.clearShellState( shell );
		delete shell.dataset.modeActivePanel;

		if ( panel !== null ) {
			panel.dataset.modePanelTarget = '';
		}

		this.dispatchModeEvent( shell, 'shield:mode-panel-closed', tile, target, trigger );
	}

	clearShellState( shell ) {
		shell.querySelectorAll( '[data-mode-tile="1"]' ).forEach( ( tile ) => {
			tile.classList.remove( 'is-active' );
			tile.setAttribute( 'aria-expanded', 'false' );
		} );

		shell.querySelectorAll( '[data-mode-panel="1"]' ).forEach( ( panel ) => {
			panel.classList.add( 'd-none' );
			panel.classList.remove( 'is-open' );
			panel.setAttribute( 'aria-hidden', 'true' );
		} );
	}

	findPanelForTarget( shell, panelTarget ) {
		const panels = Array.from( shell.querySelectorAll( '[data-mode-panel="1"]' ) );
		if ( panels.length === 0 ) {
			return null;
		}

		return panels.find( ( panel ) => ( panel.dataset.modePanelTarget || '' ) === panelTarget )
			|| panels.find( ( panel ) => ( panel.dataset.modePanelTarget || '' ) === '' )
			|| null;
	}

	getActivePanel( shell ) {
		return shell.querySelector( '[data-mode-panel="1"].is-open' );
	}

	getActiveTile( shell ) {
		return shell.querySelector( '[data-mode-tile="1"].is-active' );
	}

	getModeShell( element ) {
		return element.closest( '[data-mode-shell="1"]' );
	}

	isInteractiveShell( shell ) {
		return shell.dataset.modeInteractive === '1';
	}

	isDisabledTile( tile ) {
		return tile.dataset.modeTileDisabled === '1'
			|| tile.getAttribute( 'aria-disabled' ) === 'true'
			|| tile.classList.contains( 'is-disabled' );
	}

	dispatchModeEvent( shell, eventName, tile, panelTarget, trigger ) {
		shell.dispatchEvent( new CustomEvent( eventName, {
			bubbles: true,
			detail: {
				mode: shell.dataset.mode || '',
				tile_key: tile ? ( tile.dataset.modeTileKey || '' ) : '',
				panel_target: panelTarget || '',
				trigger,
			}
		} ) );
	}
}
