import Sortable from 'sortablejs';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

export class RulesManager extends BaseAutoExecComponent {

	init() {
		this.containerID = 'RulesManagerContainer';
		this.rules_manager_container = document.getElementById( this.containerID ) || false;
		super.init();
	}

	canRun() {
		return this.rules_manager_container;
	}

	run() {
		this.renderManager();
		const baseSelector = '#' + this.containerID + ' ';
		shieldEventsHandler_Main.add_Click( '#RulesManagerDisableAll', ( button ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.action( button.dataset );
			}
		} );
		shieldEventsHandler_Main.add_Click( baseSelector + ' button', ( button ) => {
			if ( button.dataset.action !== 'delete' || confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.action( button.dataset );
			}
		} );
		shieldEventsHandler_Main.add_Click( baseSelector + ' input[type=checkbox].active-switch', ( button ) => {
			this.action( button.dataset );
		} );
	}

	action( params = {} ) {
		( new AjaxService() )
		.send( ObjectOps.Merge( this._base_data.ajax.rules_manager_action, {
			manager_action: params
		} ) )
		.finally( () => this.renderManager() );
	}

	renderManager() {
		( new AjaxService() )
		.send( this._base_data.ajax.render_rules_manager )
		.then( ( respJSON ) => {
			this.rules_manager_container.innerHTML = respJSON.data.html;
		} )
		.finally( () => this.postRender() );
	}

	postRender() {
		const group = document.querySelector( '#RulesManager .list-group' );
		if ( group ) {
			/** https://github.com/SortableJS/Sortable **/
			Sortable.create( group, {
				animation: 150,
				easing: 'cubic-bezier(1, 0, 0, 1)',

				handle: '.drag-handle',

				ghostClass: 'list-group-item-info',
				chosenClass: 'list-group-item-info',
				dragClass: 'list-group-item-info',

				onEnd: ( evt ) => {
					if ( evt.newIndex !== evt.oldIndex ) {
						const items = [];
						group.querySelectorAll( '.list-group-item' )
							 .forEach( ( item ) => {
								 items.push( item.dataset.rule_id );
							 } );
						this.action( {
							action: 'reorder',
							order: items,
						} );
					}
				},
			} );
		}
	}
}