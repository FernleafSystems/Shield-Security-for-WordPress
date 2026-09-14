import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

export class DashboardTaskGuide extends BaseAutoExecComponent {
	canRun() {
		return document.querySelector( '[data-dashboard-task-guide="1"]' ) !== null;
	}

	run() {
		const root = document.querySelector( '[data-dashboard-task-guide="1"]' );
		this.graph = JSON.parse( root.dataset.dashboardTaskGuideGraph );
		this.nodesByKey = new Map( this.graph.nodes.map( node => [ node.key, node ] ) );
		this.handle = null;
		document.addEventListener( 'click', evt => {
			const launcher = evt.target instanceof Element ? evt.target.closest( '[data-dashboard-task-guide-launch="1"]' ) : null;
			if ( launcher instanceof HTMLButtonElement ) {
				evt.preventDefault();
				this.launch( launcher );
			}
		} );
	}

	launch( launcher ) {
		if ( this.handle !== null ) return;
		this.history = [];
		this.currentNodeKey = this.graph.initial_node_key;
		this.choices = document.createElement( 'div' );
		this.choices.className = 'dashboard-task-guide-modal__choices';
		const footer = document.createElement( 'div' );
		footer.className = 'shield-accessible-dialog__footer';
		this.back = document.createElement( 'button' );
		this.back.type = 'button';
		this.back.className = 'button';
		this.back.textContent = this.graph.strings.back_label;
		this.back.addEventListener( 'click', () => {
			this.currentNodeKey = this.history.pop();
			this.renderCurrentNode();
		} );
		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'button button-primary';
		close.textContent = this.graph.strings.close_label;
		close.addEventListener( 'click', () => this.handle.close() );
		footer.append( this.back, close );
		this.choices.addEventListener( 'click', evt => {
			const target = evt.target instanceof Element ? evt.target.closest( '[data-dashboard-task-guide-next-node]' ) : null;
			if ( target instanceof HTMLButtonElement ) {
				this.history.push( this.currentNodeKey );
				this.currentNodeKey = target.dataset.dashboardTaskGuideNextNode;
				this.renderCurrentNode();
			}
		} );
		this.renderCurrentNode();
		this.handle = shieldServices.dialog().content( {
			title: this.nodesByKey.get( this.currentNodeKey ).title,
			iconClass: 'bi bi-signpost-split',
			content: this.choices, footer, launcher,
		} );
		if ( this.handle !== null ) this.handle.closed.then( () => { this.handle = null; } );
	}

	renderCurrentNode() {
		const node = this.nodesByKey.get( this.currentNodeKey );
		this.choices.replaceChildren( ...node.choices.map( choice => this.buildChoice( choice ) ) );
		this.back.hidden = this.history.length === 0;
		if ( this.handle !== null ) this.handle.setTitle( node.title );
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

}
