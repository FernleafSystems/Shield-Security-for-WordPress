import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import {
	getActiveLayerIndex,
	getLayersForShell,
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

	initializeCurrentRoot() {
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
			return;
		}

		if ( tab.dataset.stepTabInvestigateReset === '1' ) {
			const investigateCtrl = shieldAppMain?.components?.investigate_landing || null;
			if ( investigateCtrl !== null && typeof investigateCtrl.resetCurrentSelection === 'function' ) {
				evt.preventDefault();
				investigateCtrl.resetCurrentSelection( shell ).finally();
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
		const homeLabel = this.getHomeLabel( shell );
		if ( mode === 'dashboard' ) {
			const dashboardStep = this.buildHomeStep( rootStep, homeLabel, null, true, true );
			return {
				steps: [ dashboardStep ],
				currentStep: dashboardStep,
			};
		}

		const drillShell = this.getTopLevelDrillShell( shell );
		if ( drillShell !== null ) {
			return this.buildDrillPath( shell, rootStep, drillShell, homeHref, homeLabel );
		}

		if ( shell.dataset.modeInteractive === '1' ) {
			return this.buildModePanelPath( rootStep, shell, homeHref, homeLabel );
		}

		const rootVisual = this.buildVisualStep( rootStep, null, true );
		return {
			steps: [ this.buildHomeStep( rootStep, homeLabel, homeHref, false ), rootVisual ],
			currentStep: rootVisual,
		};
	}

	buildDrillPath( shell, rootStep, drillShell, homeHref, homeLabel ) {
		const layers = getLayersForShell( drillShell );
		const activeIndex = getActiveLayerIndex( layers );
		const rootVisual = this.buildVisualStep(
			rootStep,
			activeIndex > 0 ? { kind: 'drill', value: 0 } : null,
			activeIndex <= 0
		);
		const steps = [
			this.buildHomeStep( rootStep, homeLabel, homeHref, false ),
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

		const currentStep = this.extendInvestigatePath( shell, steps );
		return { steps, currentStep };
	}

	buildModePanelPath( rootStep, shell, homeHref, homeLabel ) {
		const activeTile = this.getActiveTopLevelTile( shell );
		const rootVisual = this.buildVisualStep( rootStep, activeTile === null ? null : { kind: 'panel-close', value: '' }, activeTile === null );
		if ( activeTile === null ) {
			return {
				steps: [ this.buildHomeStep( rootStep, homeLabel, homeHref, false ), rootVisual ],
				currentStep: rootVisual,
			};
		}

		const tileStep = this.readStepData( activeTile.dataset.modeTileStep );
		const panelVisual = this.buildVisualStep( tileStep, null, true );

		return {
			steps: [ this.buildHomeStep( rootStep, homeLabel, homeHref, false ), rootVisual, panelVisual ],
			currentStep: panelVisual,
		};
	}

	extendInvestigatePath( shell, steps ) {
		const genericStep = steps[ steps.length - 1 ] || null;
		if ( String( shell.dataset.mode || '' ).trim() !== 'investigate' || genericStep === null ) {
			return genericStep;
		}

		const resolvedStep = this.buildInvestigateResolvedStep( shell, genericStep );
		if ( resolvedStep === null ) {
			return genericStep;
		}

		genericStep.isCurrent = false;
		genericStep.target = { kind: 'investigate-reset', value: '' };
		steps.push( resolvedStep );
		return resolvedStep;
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

		if ( step.actions.length > 0 ) {
			const section = document.createElement( 'div' );
			section.className = 'operator-context-rail__section';
			const sectionLabel = document.createElement( 'div' );
			sectionLabel.className = 'operator-context-rail__section-label';
			sectionLabel.textContent = labels.actions;
			const actionsWrap = document.createElement( 'div' );
			actionsWrap.className = 'operator-context-rail__actions';

			step.actions.forEach( ( action ) => actionsWrap.appendChild( this.buildContextActionElement( action ) ) );

			section.appendChild( sectionLabel );
			section.appendChild( actionsWrap );
			fragment.appendChild( section );
		}

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
		else if ( step.target?.kind === 'investigate-reset' ) {
			el.dataset.stepTabInvestigateReset = '1';
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
		const breadcrumbLabel = this.readText( stepData?.breadcrumb_label );
		const title = this.readText( stepData?.title );

		return {
			label: breadcrumbLabel || title,
			title: title || breadcrumbLabel,
			summary: this.readText( stepData?.summary ),
			focus: this.readText( stepData?.focus ),
			nextStep: this.readText( stepData?.next_step ),
			iconClass: this.readText( stepData?.icon_class ),
			tabIconClass: '',
			badge: this.readText( stepData?.badge ),
			badgeStatus: this.readText( stepData?.badge_status ) || 'neutral',
			colorKey: this.readText( stepData?.color_key ) || 'neutral',
			actions: Array.isArray( stepData?.actions ) ? stepData.actions : [],
			target,
			isCurrent,
			showLabel: true,
		};
	}

	buildContextActionElement( action ) {
		const kind = this.readText( action?.kind ) || 'href';
		const type = this.readText( action?.type ) || 'navigate';
		const el = kind === 'ajax'
			? document.createElement( 'button' )
			: document.createElement( 'a' );

		if ( el instanceof HTMLButtonElement ) {
			el.type = 'button';
			el.dataset.operatorContextActionAjax = '1';
			el.dataset.operatorContextActionJson = this.readText( action?.ajax_action_json );
			const confirmText = this.readText( action?.confirm_text );
			if ( confirmText.length > 0 ) {
				el.dataset.operatorContextActionConfirm = confirmText;
			}
		}
		else {
			el.href = this.readText( action?.href ) || '#';
		}

		el.className = `shield-action-chip shield-action-chip--${type} operator-context-rail__action`;

		const iconClass = this.readText( action?.icon_class );
		if ( iconClass.length > 0 ) {
			const icon = document.createElement( 'i' );
			icon.className = iconClass;
			icon.setAttribute( 'aria-hidden', 'true' );
			el.appendChild( icon );
		}

		el.appendChild( document.createTextNode( this.readText( action?.label ) ) );

		return el;
	}

	buildHomeStep( rootStep, homeLabel, homeHref, isCurrent, showLabel = false ) {
		return {
			label: homeLabel,
			title: homeLabel,
			summary: isCurrent ? this.readText( rootStep?.summary ) : '',
			focus: isCurrent ? this.readText( rootStep?.focus ) : '',
			nextStep: isCurrent ? this.readText( rootStep?.next_step ) : '',
			iconClass: isCurrent ? this.readText( rootStep?.icon_class ) : '',
			tabIconClass: 'bi bi-house-fill',
			badge: '',
			badgeStatus: 'neutral',
			colorKey: 'home',
			target: isCurrent ? null : { kind: 'href', value: homeHref },
			isCurrent,
			showLabel,
		};
	}

	buildInvestigateResolvedStep( shell, genericStep ) {
		const panel = this.getTopLevelInvestigatePanel( shell );
		if ( !( panel instanceof HTMLElement ) || panel.dataset.investigatePanelLoaded !== '1' ) {
			return null;
		}

		const subjectKey = String( panel.dataset.investigatePanelSubject || '' ).trim();
		const selection = this.getInvestigateSelection( shell, subjectKey );
		if ( selection === null || selection.lookupKey.length < 1 ) {
			return null;
		}

		const resolvedLabel = this.readInvestigateResolvedLabel( panel );
		if ( resolvedLabel.length < 1 || resolvedLabel === genericStep.label || resolvedLabel === genericStep.title ) {
			return null;
		}

		return {
			...genericStep,
			label: resolvedLabel,
			title: resolvedLabel,
			target: null,
			isCurrent: true,
		};
	}

	readStepData( rawValue ) {
		return parseJsonAttribute( rawValue, {} );
	}

	readText( value ) {
		return String( value ?? '' ).trim();
	}

	readTopLevelDescendants( shell, selector ) {
		return Array.from( shell.querySelectorAll( selector ) )
			.filter( ( element ) => element.closest( '[data-mode-shell="1"]' ) === shell );
	}

	getTopLevelDrillShell( shell ) {
		return this.readTopLevelDescendants( shell, '[data-drill-shell="1"]' )[ 0 ] || null;
	}

	getTopLevelInvestigatePanel( shell ) {
		return this.readTopLevelDescendants( shell, '[data-investigate-panel="1"]' )[ 0 ] || null;
	}

	getActiveTopLevelTile( shell ) {
		return this.readTopLevelDescendants( shell, '[data-mode-tile="1"].is-active' )[ 0 ] || null;
	}

	getInvestigateSelection( shell, subjectKey ) {
		if ( subjectKey.length < 1 ) {
			return null;
		}

		const subjectTile = this.readTopLevelDescendants(
			shell,
			'[data-investigate-subject][data-investigate-lookup-key]'
		).find( ( item ) => String( item.dataset.investigateSubject || '' ).trim() === subjectKey ) || null;
		if ( !( subjectTile instanceof HTMLElement ) ) {
			return null;
		}

		return {
			lookupKey: String( subjectTile.dataset.investigateLookupKey || '' ).trim(),
		};
	}

	readInvestigateResolvedLabel( panel ) {
		const subjectHeader = panel.querySelector( '[data-investigate-subject-header="1"]' );
		return subjectHeader instanceof HTMLElement
			? String( subjectHeader.dataset.investigateBreadcrumbLabel || '' ).trim()
			: '';
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
			actions: shell.dataset.operatorContextActions,
		};
	}

	normalizeContextMode( shell ) {
		const mode = String( shell.dataset.mode || '' ).trim();
		return mode === 'reports' ? 'review' : mode;
	}

	getHomeLabel( shell ) {
		return String( shell.dataset.operatorHomeLabel || '' ).trim();
	}
}
