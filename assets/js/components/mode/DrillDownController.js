import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

export class DrillDownController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-drill-strip="1"]',
			( strip ) => this.handleStripClick( strip ),
			false
		);
	}

	handleStripClick( strip ) {
		const layer = strip.closest( '[data-drill-layer]' );
		const shell = layer?.closest( '[data-drill-shell="1"]' ) || null;
		if ( layer === null || shell === null ) {
			return;
		}

		this.drillTo( shell, this.parseLayerIndex( layer.dataset.drillLayer ) );
	}

	/**
	 * Drill a shell to the requested layer index.
	 *
	 * @param {HTMLElement|null} shellEl
	 * @param {number} layerIndex
	 */
	drillTo( shellEl, layerIndex ) {
		if ( !this.isDrillShell( shellEl ) ) {
			return;
		}

		const layers = this.getLayersForShell( shellEl );
		if ( layers.length < 1 ) {
			return;
		}

		const targetIndex = this.clampLayerIndex( layerIndex, layers.length );
		const currentActiveIndex = this.getActiveLayerIndex( layers );
		if ( targetIndex === currentActiveIndex ) {
			return;
		}

		const eventName = targetIndex > currentActiveIndex
			? 'shield:drill-to'
			: 'shield:drill-back';
		const activeLayer = layers.find( ( layer ) => this.parseLayerIndex( layer.dataset.drillLayer ) === targetIndex ) || null;

		layers.forEach( ( layer ) => {
			const currentLayerIndex = this.parseLayerIndex( layer.dataset.drillLayer );
			const nextState = currentLayerIndex < targetIndex
				? 'compact'
				: ( currentLayerIndex === targetIndex ? 'active' : 'hidden' );

			if ( nextState !== 'active' ) {
				BootstrapTooltips.DisposeTooltipsWithin( layer );
			}

			this.applyLayerState( layer, nextState );
		} );

		const activeLayerBody = activeLayer?.querySelector( '.drill-layer__body' ) || null;
		if ( activeLayerBody !== null ) {
			UiContentActivator.activateCurrentSubtree( activeLayerBody );
		}

		shellEl.dispatchEvent( new CustomEvent( eventName, {
			bubbles: true,
			detail: {
				mode: shellEl.dataset.drillShellMode || '',
				layer_key: activeLayer?.dataset.drillLayerKey || '',
				layer_index: targetIndex,
			}
		} ) );
	}

	/**
	 * Update the strip badge for a shell layer.
	 *
	 * @param {HTMLElement|null} shellEl
	 * @param {number} layerIndex
	 * @param {string|number} text
	 * @param {string} statusClass
	 */
	updateStripBadge( shellEl, layerIndex, text, statusClass ) {
		if ( !this.isDrillShell( shellEl ) ) {
			return;
		}

		const layer = this.getLayersForShell( shellEl )
			.find( ( candidate ) => this.parseLayerIndex( candidate.dataset.drillLayer ) === this.parseLayerIndex( layerIndex ) );
		const badge = layer?.querySelector( '[data-drill-strip="1"] .shield-badge' ) || null;
		if ( badge === null ) {
			return;
		}

		[ ...badge.classList ]
			.filter( ( className ) => className.startsWith( 'badge-' ) )
			.forEach( ( className ) => badge.classList.remove( className ) );

		const nextStatusClass = String( statusClass || '' ).trim();
		if ( nextStatusClass.length > 0 ) {
			badge.classList.add( `badge-${nextStatusClass}` );
		}

		badge.textContent = String( text ?? '' );
	}

	getLayersForShell( shellEl ) {
		if ( !this.isDrillShell( shellEl ) ) {
			return [];
		}

		return Array.from( shellEl.querySelectorAll( '[data-drill-layer]' ) )
			.filter( ( layer ) => layer.closest( '[data-drill-shell="1"]' ) === shellEl )
			.sort( ( a, b ) => this.parseLayerIndex( a.dataset.drillLayer ) - this.parseLayerIndex( b.dataset.drillLayer ) );
	}

	getActiveLayerIndex( layers ) {
		for ( const layer of layers ) {
			if ( !layer.classList.contains( 'drill-layer--compact' )
				&& !layer.classList.contains( 'drill-layer--hidden' ) ) {
				return this.parseLayerIndex( layer.dataset.drillLayer );
			}
		}

		return -1;
	}

	isDrillShell( shellEl ) {
		return shellEl instanceof HTMLElement
			&& shellEl.dataset.drillShell === '1';
	}

	clampLayerIndex( layerIndex, layerCount ) {
		const parsedIndex = this.parseLayerIndex( layerIndex );
		if ( layerCount < 1 ) {
			return -1;
		}

		return Math.min( Math.max( parsedIndex, 0 ), layerCount - 1 );
	}

	parseLayerIndex( layerIndex ) {
		const parsedIndex = parseInt( String( layerIndex ), 10 );
		return Number.isNaN( parsedIndex ) ? -1 : parsedIndex;
	}

	applyLayerState( layer, state ) {
		layer.classList.add( 'drill-layer' );
		layer.classList.remove( 'drill-layer--compact', 'drill-layer--hidden' );

		if ( state === 'compact' ) {
			layer.classList.add( 'drill-layer--compact' );
		}
		else if ( state === 'hidden' ) {
			layer.classList.add( 'drill-layer--hidden' );
		}
	}
}
