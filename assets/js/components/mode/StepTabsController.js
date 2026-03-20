import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import {
	getActiveLayerIndex,
	getLayersForShell,
	normalizeLayerHeaderData,
	parseJsonAttribute
} from "./DrillDownShared";

export class StepTabsController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		if ( !this.hasBoundHandlers ) {
			this.hasBoundHandlers = true;

			[
				'shield:drill-to',
				'shield:drill-back',
				'shield:drill-header-updated',
				'shield:mode-panel-opened',
				'shield:mode-panel-closed',
			].forEach( ( eventName ) => {
				document.addEventListener( eventName, ( evt ) => this.handleChromeStateChange( evt ) );
			} );

			shieldEventsHandler_Main.add_Click(
				'[data-operator-step-tab="1"]',
				( tab, evt ) => this.handleStepTabClick( tab, evt ),
				false
			);
		}

		this.renderAllShells();
	}

	renderAllShells() {
		document.querySelectorAll( '[data-mode-shell="1"][data-operator-chrome="1"]' )
			.forEach( ( shell ) => this.renderShell( shell ) );
	}

	handleChromeStateChange( evt ) {
		const shell = this.getEventOperatorShell( evt.target );
		if ( shell !== null ) {
			this.renderShell( shell );
		}
	}

	handleStepTabClick( tab, evt ) {
		if ( !( tab instanceof HTMLElement ) || tab.hasAttribute( 'disabled' ) ) {
			return;
		}

		const shell = this.getOperatorShellForElement( tab );
		if ( shell === null ) {
			return;
		}

		if ( tab.dataset.stepTabDrillIndex ) {
			const drillShell = this.getTopLevelDrillShell( shell );
			const drillCtrl = shieldAppMain?.components?.drill_down || null;
			if ( drillShell !== null && drillCtrl !== null ) {
				evt.preventDefault();
				drillCtrl.drillTo( drillShell, parseInt( tab.dataset.stepTabDrillIndex, 10 ) );
			}
			return;
		}

		if ( tab.dataset.stepTabClosePanel === '1' ) {
			const modePanelCtrl = shieldAppMain?.components?.mode_panel_state || null;
			if ( modePanelCtrl !== null ) {
				evt.preventDefault();
				modePanelCtrl.closePanel( shell, shell.dataset.modeActivePanel || '', 'breadcrumb' );
			}
		}
	}

	renderShell( shell ) {
		if ( !( shell instanceof HTMLElement ) ) {
			return;
		}

		const path = this.buildPath( shell );
		this.renderTabs( shell, path.steps );
		this.renderContextRail( shell, path.currentStep );
	}

	buildPath( shell ) {
		const rootStep = this.readStepData( shell.dataset.operatorRootStep );
		const drillShell = this.getTopLevelDrillShell( shell );
		if ( drillShell !== null ) {
			return this.buildDrillPath( rootStep, drillShell );
		}

		if ( shell.dataset.modeInteractive === '1' ) {
			return this.buildModePanelPath( rootStep, shell );
		}

		const rootVisual = this.buildVisualStep( rootStep, null, true );
		return {
			steps: [ rootVisual ],
			currentStep: rootVisual,
		};
	}

	buildDrillPath( rootStep, drillShell ) {
		const layers = getLayersForShell( drillShell );
		const activeIndex = getActiveLayerIndex( layers );
		const steps = [
			this.buildVisualStep( rootStep, activeIndex > 0 ? { kind: 'drill', value: 0 } : null, activeIndex <= 0 )
		];

		layers.forEach( ( layer ) => {
			const layerIndex = parseInt( layer.dataset.drillLayer || '-1', 10 );
			if ( layerIndex < 1 || layerIndex > activeIndex ) {
				return;
			}

			const header = this.readStepData( layer.dataset.drillLayerHeader );
			steps.push( this.buildVisualStep(
				header,
				layerIndex < activeIndex ? { kind: 'drill', value: layerIndex } : null,
				layerIndex === activeIndex
			) );
		} );

		return {
			steps,
			currentStep: steps[ steps.length - 1 ],
		};
	}

	buildModePanelPath( rootStep, shell ) {
		const activeTile = this.getActiveTopLevelTile( shell );
		if ( activeTile === null ) {
			const rootVisual = this.buildVisualStep( rootStep, null, true );
			return {
				steps: [ rootVisual ],
				currentStep: rootVisual,
			};
		}

		const tileStep = this.readStepData( activeTile.dataset.modeTileStep );
		const rootVisual = this.buildVisualStep( rootStep, { kind: 'panel-close', value: '' }, false );
		const panelVisual = this.buildVisualStep( tileStep, null, true );

		return {
			steps: [ rootVisual, panelVisual ],
			currentStep: panelVisual,
		};
	}

	renderTabs( shell, steps ) {
		const listEl = shell.querySelector( '[data-operator-step-tabs-list="1"]' );
		if ( !( listEl instanceof HTMLElement ) ) {
			return;
		}

		listEl.replaceChildren();

		steps.forEach( ( step, index ) => {
			if ( index > 0 ) {
				const separator = document.createElement( 'span' );
				separator.className = 'operator-step-tabs__separator';
				separator.setAttribute( 'aria-hidden', 'true' );
				separator.textContent = '>';
				listEl.appendChild( separator );
			}

			listEl.appendChild( this.buildTabButton( step ) );
		} );
	}

	renderContextRail( shell, step ) {
		const rail = shell.querySelector( '[data-operator-context-rail="1"]' );
		const railBody = shell.querySelector( '[data-operator-context-rail-body="1"]' );
		if ( !( rail instanceof HTMLElement ) || !( railBody instanceof HTMLElement ) ) {
			return;
		}

		this.replacePrefixedClass( rail, 'status-', step.colorKey );

		const fragment = document.createDocumentFragment();
		const header = document.createElement( 'div' );
		header.className = 'operator-context-rail__header';

		if ( step.iconClass.length > 0 ) {
			const iconWrap = document.createElement( 'span' );
			iconWrap.className = 'operator-context-rail__icon';
			iconWrap.setAttribute( 'aria-hidden', 'true' );
			const icon = document.createElement( 'i' );
			icon.className = step.iconClass;
			iconWrap.appendChild( icon );
			header.appendChild( iconWrap );
		}

		const labels = this.getRailLabels( shell );
		const titleWrap = document.createElement( 'div' );
		titleWrap.className = 'operator-context-rail__title-wrap';
		const eyebrow = document.createElement( 'div' );
		eyebrow.className = 'operator-context-rail__eyebrow';
		eyebrow.textContent = labels.title;
		titleWrap.appendChild( eyebrow );

		const titleRow = document.createElement( 'div' );
		titleRow.className = 'operator-context-rail__title-row';
		const title = document.createElement( 'h5' );
		title.className = 'operator-context-rail__title';
		title.textContent = step.title;
		titleRow.appendChild( title );

		if ( step.badge.length > 0 ) {
			const badge = document.createElement( 'span' );
			badge.className = `shield-badge badge-${step.badgeStatus}`;
			badge.textContent = step.badge;
			titleRow.appendChild( badge );
		}

		titleWrap.appendChild( titleRow );
		header.appendChild( titleWrap );
		fragment.appendChild( header );

		if ( step.summary.length > 0 ) {
			const summary = document.createElement( 'p' );
			summary.className = 'operator-context-rail__summary';
			summary.textContent = step.summary;
			fragment.appendChild( summary );
		}

		[
			[ labels.focus, step.focus ],
			[ labels.next, step.nextStep ],
		].forEach( ( [ label, text ] ) => {
			if ( text.length < 1 ) {
				return;
			}

			const section = document.createElement( 'div' );
			section.className = 'operator-context-rail__section';
			const sectionLabel = document.createElement( 'div' );
			sectionLabel.className = 'operator-context-rail__section-label';
			sectionLabel.textContent = label;
			const sectionText = document.createElement( 'div' );
			sectionText.className = 'operator-context-rail__section-text';
			sectionText.textContent = text;
			section.appendChild( sectionLabel );
			section.appendChild( sectionText );
			fragment.appendChild( section );
		} );

		railBody.replaceChildren( fragment );
	}

	buildTabButton( step ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = `operator-step-tabs__tab status-${step.colorKey}`;
		button.dataset.operatorStepTab = '1';

		if ( step.isCurrent ) {
			button.classList.add( 'is-active' );
			button.setAttribute( 'aria-current', 'step' );
			button.disabled = true;
		}
		else if ( step.target?.kind === 'drill' ) {
			button.dataset.stepTabDrillIndex = String( step.target.value );
		}
		else if ( step.target?.kind === 'panel-close' ) {
			button.dataset.stepTabClosePanel = '1';
		}

		const pip = document.createElement( 'span' );
		pip.className = `operator-step-tabs__tab-pip status-${step.colorKey}`;
		pip.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( pip );

		const label = document.createElement( 'span' );
		label.className = 'operator-step-tabs__tab-label';
		label.textContent = step.label;
		button.appendChild( label );

		if ( step.badge.length > 0 ) {
			const badge = document.createElement( 'span' );
			badge.className = `shield-badge badge-${step.badgeStatus}`;
			badge.textContent = step.badge;
			button.appendChild( badge );
		}

		return button;
	}

	buildVisualStep( stepData, target, isCurrent ) {
		return {
			label: stepData.breadcrumb_label || stepData.title,
			title: stepData.title || stepData.breadcrumb_label,
			summary: stepData.summary,
			focus: stepData.focus,
			nextStep: stepData.next_step,
			iconClass: stepData.icon_class,
			badge: stepData.badge,
			badgeStatus: stepData.badge_status,
			colorKey: stepData.color_key || stepData.badge_status,
			target,
			isCurrent,
		};
	}

	readStepData( rawValue ) {
		return normalizeLayerHeaderData( parseJsonAttribute( rawValue, {} ) );
	}

	readTopLevelDescendants( shell, selector ) {
		return Array.from( shell.querySelectorAll( selector ) )
			.filter( ( element ) => element.closest( '[data-mode-shell="1"]' ) === shell );
	}

	getTopLevelDrillShell( shell ) {
		return this.readTopLevelDescendants( shell, '[data-drill-shell="1"]' )[ 0 ] || null;
	}

	getActiveTopLevelTile( shell ) {
		return this.readTopLevelDescendants( shell, '[data-mode-tile="1"].is-active' )[ 0 ] || null;
	}

	getOperatorShellForElement( element ) {
		return element instanceof Element
			? element.closest( '[data-mode-shell="1"][data-operator-chrome="1"]' )
			: null;
	}

	getEventOperatorShell( element ) {
		if ( !( element instanceof Element ) ) {
			return null;
		}

		const modeShell = element.closest( '[data-mode-shell="1"]' );
		return modeShell instanceof HTMLElement && modeShell.dataset.operatorChrome === '1'
			? modeShell
			: null;
	}

	getRailLabels( shell ) {
		return {
			title: shell.dataset.operatorContextTitle,
			focus: shell.dataset.operatorContextFocus,
			next: shell.dataset.operatorContextNext,
		};
	}

	replacePrefixedClass( el, prefix, suffix ) {
		[ ...el.classList ]
			.filter( ( className ) => className.startsWith( prefix ) )
			.forEach( ( className ) => el.classList.remove( className ) );
		el.classList.add( `${prefix}${suffix}` );
	}
}
