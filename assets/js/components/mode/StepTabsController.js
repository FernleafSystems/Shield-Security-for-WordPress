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
		const mode = String( shell.dataset.mode || '' ).trim();
		const homeHref = String( shell.dataset.operatorHomeHref || '' ).trim();
		if ( mode === 'dashboard' ) {
			const dashboardStep = this.buildHomeStep( rootStep, null, true, true );
			return {
				steps: [ dashboardStep ],
				currentStep: dashboardStep,
			};
		}

		const drillShell = this.getTopLevelDrillShell( shell );
		if ( drillShell !== null ) {
			return this.buildDrillPath( rootStep, drillShell, homeHref );
		}

		if ( shell.dataset.modeInteractive === '1' ) {
			return this.buildModePanelPath( rootStep, shell, homeHref );
		}

		const rootVisual = this.buildVisualStep( rootStep, null, true );
		return {
			steps: [ this.buildHomeStep( rootStep, homeHref, false ), rootVisual ],
			currentStep: rootVisual,
		};
	}

	buildDrillPath( rootStep, drillShell, homeHref ) {
		const layers = getLayersForShell( drillShell );
		const activeIndex = getActiveLayerIndex( layers );
		const rootVisual = this.buildVisualStep(
			rootStep,
			activeIndex > 0 ? { kind: 'drill', value: 0 } : null,
			activeIndex <= 0
		);
		const steps = [
			this.buildHomeStep( rootStep, homeHref, false ),
			rootVisual,
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

	buildModePanelPath( rootStep, shell, homeHref ) {
		const activeTile = this.getActiveTopLevelTile( shell );
		const rootVisual = this.buildVisualStep( rootStep, activeTile === null ? null : { kind: 'panel-close', value: '' }, activeTile === null );
		if ( activeTile === null ) {
			return {
				steps: [ this.buildHomeStep( rootStep, homeHref, false ), rootVisual ],
				currentStep: rootVisual,
			};
		}

		const tileStep = this.readStepData( activeTile.dataset.modeTileStep );
		const panelVisual = this.buildVisualStep( tileStep, null, true );

		return {
			steps: [ this.buildHomeStep( rootStep, homeHref, false ), rootVisual, panelVisual ],
			currentStep: panelVisual,
		};
	}

	renderTabs( shell, steps ) {
		const listEl = shell.querySelector( '[data-operator-step-tabs-list="1"]' );
		if ( !( listEl instanceof HTMLElement ) ) {
			return;
		}

		listEl.replaceChildren();

		steps.forEach( ( step ) => listEl.appendChild( this.buildTabElement( step ) ) );
	}

	renderContextRail( shell, step ) {
		const rail = shell.querySelector( '[data-operator-context-rail="1"]' );
		const railBody = shell.querySelector( '[data-operator-context-rail-body="1"]' );
		if ( !( rail instanceof HTMLElement ) || !( railBody instanceof HTMLElement ) ) {
			return;
		}

		rail.dataset.contextMode = this.normalizeContextMode( shell );

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

	buildTabElement( step ) {
		const el = step.target?.kind === 'href'
			? document.createElement( 'a' )
			: document.createElement( 'button' );

		if ( el instanceof HTMLButtonElement ) {
			el.type = 'button';
		}
		else {
			el.href = String( step.target?.value || '#' );
		}

		el.className = 'operator-step-tabs__tab';
		el.dataset.operatorStepTab = '1';
		el.dataset.colorKey = step.colorKey;

		if ( step.isCurrent ) {
			el.classList.add( 'is-active' );
			el.setAttribute( 'aria-current', 'step' );
			if ( el instanceof HTMLButtonElement ) {
				el.disabled = true;
			}
		}
		else if ( step.target?.kind === 'drill' ) {
			el.dataset.stepTabDrillIndex = String( step.target.value );
		}
		else if ( step.target?.kind === 'panel-close' ) {
			el.dataset.stepTabClosePanel = '1';
		}

		if ( step.tabIconClass.length > 0 ) {
			const icon = document.createElement( 'i' );
			icon.className = `operator-step-tabs__tab-icon ${step.tabIconClass}`;
			icon.setAttribute( 'aria-hidden', 'true' );
			el.appendChild( icon );
		}

		const label = document.createElement( 'span' );
		label.className = `operator-step-tabs__tab-label${step.showLabel ? '' : ' screen-reader-text'}`;
		label.textContent = step.label;
		el.appendChild( label );

		return el;
	}

	buildVisualStep( stepData, target, isCurrent ) {
		return {
			label: stepData.breadcrumb_label || stepData.title,
			title: stepData.title || stepData.breadcrumb_label,
			summary: stepData.summary,
			focus: stepData.focus,
			nextStep: stepData.next_step,
			iconClass: stepData.icon_class,
			tabIconClass: '',
			badge: stepData.badge,
			badgeStatus: stepData.badge_status,
			colorKey: stepData.color_key,
			target,
			isCurrent,
			showLabel: true,
		};
	}

	buildHomeStep( rootStep, homeHref, isCurrent, showLabel = false ) {
		const homeLabel = 'Dashboard';

		return {
			label: homeLabel,
			title: homeLabel,
			summary: isCurrent ? rootStep.summary : '',
			focus: isCurrent ? rootStep.focus : '',
			nextStep: isCurrent ? rootStep.next_step : '',
			iconClass: isCurrent ? rootStep.icon_class : '',
			tabIconClass: 'bi bi-house-fill',
			badge: '',
			badgeStatus: 'neutral',
			colorKey: 'home',
			target: isCurrent ? null : { kind: 'href', value: homeHref },
			isCurrent,
			showLabel,
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

	normalizeContextMode( shell ) {
		const mode = String( shell.dataset.mode || '' ).trim();
		return mode === 'reports' ? 'review' : mode;
	}
}
