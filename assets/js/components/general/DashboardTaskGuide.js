import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { BootstrapModals } from "../ui/BootstrapModals";
import { announceWithin, focusElement } from "../ui/ShieldA11y";

export class DashboardTaskGuide extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-dashboard-task-guide="1"]' ) !== null;
	}

	run() {
		this.root = document.querySelector( '[data-dashboard-task-guide="1"]' );
		this.modal = document.getElementById( 'ShieldModalContainer' );
		this.content = this.modal?.querySelector( '.modal-content' ) || null;
		this.dialog = this.modal?.querySelector( '.modal-dialog' ) || null;
		if ( this.root === null || this.modal === null || this.content === null || this.dialog === null ) {
			return;
		}

		this.graph = JSON.parse( this.root.dataset.dashboardTaskGuideGraph );
		this.nodesByKey = new Map( this.graph.nodes.map( ( node ) => [ node.key, node ] ) );
		this.root.addEventListener( 'click', ( evt ) => this.handleLauncherClick( evt ) );
		this.modal.addEventListener( 'click', ( evt ) => this.handleModalClick( evt ) );
		this.modal.addEventListener( 'hidden.bs.modal', () => this.resetModalState() );
		if ( this.shouldLaunchFromSidebar() ) {
			this.launch();
		}
	}

	handleLauncherClick( evt ) {
		const launcher = evt.target instanceof Element
			? evt.target.closest( '[data-dashboard-task-guide-launch="1"]' )
			: null;
		if ( !( launcher instanceof HTMLButtonElement ) || !this.root.contains( launcher ) ) {
			return;
		}

		evt.preventDefault();
		this.launch();
	}

	launch() {
		this.history = [];
		this.currentNodeKey = this.graph.initial_node_key;
		this.renderCurrentNode();
		this.modal.classList.add( 'shield-modal--dashboard-task-guide' );
		this.dialog.classList.add( 'modal-dialog-centered' );
		this.modal.addEventListener( 'shown.bs.modal', () => this.focusCurrentChoice(), { once: true } );
		if ( !BootstrapModals.Show( this.modal ) ) {
			this.resetModalState();
		}
	}

	handleModalClick( evt ) {
		const target = evt.target instanceof Element ? evt.target : null;
		if ( target === null ) {
			return;
		}

		const nextNodeChoice = target.closest( '[data-dashboard-task-guide-next-node]' );
		if ( nextNodeChoice instanceof HTMLButtonElement && this.modal.contains( nextNodeChoice ) ) {
			this.history.push( this.currentNodeKey );
			this.currentNodeKey = nextNodeChoice.dataset.dashboardTaskGuideNextNode;
			this.renderCurrentNode();
			this.focusCurrentChoice();
			return;
		}

		const back = target.closest( '[data-dashboard-task-guide-back="1"]' );
		if ( back instanceof HTMLButtonElement && this.modal.contains( back ) && this.history.length > 0 ) {
			this.currentNodeKey = this.history.pop();
			this.renderCurrentNode();
			this.focusCurrentChoice();
		}
	}

	renderCurrentNode() {
		const node = this.nodesByKey.get( this.currentNodeKey );
		const header = document.createElement( 'div' );
		header.className = 'modal-header';

		const title = document.createElement( 'h2' );
		title.className = 'modal-title';
		title.id = 'ShieldModalContainerLabel';
		title.textContent = node.title;
		header.appendChild( title );

		const closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'btn-close';
		closeButton.dataset.bsDismiss = 'modal';
		closeButton.setAttribute( 'aria-label', this.graph.strings.close_label );
		header.appendChild( closeButton );

		const body = document.createElement( 'div' );
		body.className = 'modal-body';

		const choices = document.createElement( 'div' );
		choices.className = 'dashboard-task-guide-modal__choices';
		node.choices.forEach( ( choice ) => choices.appendChild( this.buildChoice( choice ) ) );
		body.appendChild( choices );

		const footer = document.createElement( 'div' );
		footer.className = 'modal-footer';
		if ( this.history.length > 0 ) {
			const backButton = document.createElement( 'button' );
			backButton.type = 'button';
			backButton.className = 'btn btn-outline-secondary';
			backButton.dataset.dashboardTaskGuideBack = '1';
			backButton.textContent = this.graph.strings.back_label;
			footer.appendChild( backButton );
		}
		const footerCloseButton = document.createElement( 'button' );
		footerCloseButton.type = 'button';
		footerCloseButton.className = 'btn btn-secondary';
		footerCloseButton.dataset.bsDismiss = 'modal';
		footerCloseButton.textContent = this.graph.strings.close_label;
		footer.appendChild( footerCloseButton );

		this.content.replaceChildren( header, body, footer );
		announceWithin( this.modal, node.title, { allowRepeat: false } );
	}

	buildChoice( choice ) {
		const target = choice.target.type === 'node'
			? document.createElement( 'button' )
			: document.createElement( 'a' );
		target.className = 'dashboard-task-guide-modal__choice';
		if ( target instanceof HTMLButtonElement ) {
			target.type = 'button';
			target.dataset.dashboardTaskGuideNextNode = choice.target.node_key;
		}
		else {
			target.href = choice.target.href;
			target.dataset.dashboardTaskGuideLeaf = '1';
		}

		const icon = document.createElement( 'span' );
		icon.className = 'dashboard-task-guide-modal__choice-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		const iconElement = document.createElement( 'i' );
		iconElement.className = choice.icon_class;
		icon.appendChild( iconElement );
		target.appendChild( icon );

		const label = document.createElement( 'span' );
		label.className = 'dashboard-task-guide-modal__choice-label';
		label.textContent = choice.label;
		target.appendChild( label );

		return target;
	}

	focusCurrentChoice() {
		focusElement( this.modal.querySelector( '[data-dashboard-task-guide-next-node], [data-dashboard-task-guide-leaf="1"]' ) );
	}

	resetModalState() {
		this.modal.classList.remove( 'shield-modal--dashboard-task-guide' );
		this.dialog.classList.remove( 'modal-dialog-centered' );
		this.history = [];
		this.currentNodeKey = '';
	}

	shouldLaunchFromSidebar() {
		const url = new URL( window.location.href );
		if ( url.searchParams.get( 'task_guide' ) !== '1' ) {
			return false;
		}

		url.searchParams.delete( 'task_guide' );
		window.history.replaceState( window.history.state, '', url );
		return true;
	}
}
