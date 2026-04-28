import Sortable from 'sortablejs';
import { ShieldTableBase } from "./ShieldTableBase";
import { ObjectOps } from "../../util/ObjectOps";
import { confirmDialog, resolveDialogConfirmLabel, resolveDialogLauncher } from "../ui/ShieldDialog";

/**
 * Rows-Reorder extension for Datatables is terrible. When using server-side there's no way to gather
 * the current row order, so we have to use Sortable to bridge the gap and scrap DT reorder extension.
 */
export class ShieldTableSecurityRules extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SecurityRulesManager';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brptip';
		// cfg.rowReorder = {
		// 	dataSrc: 'rid', /** The column that selects to drag **/
		// 	/** https://datatables.net/reference/option/rowReorder.update **/
		// 	update: false, /** whether to automatically reload the table after ordering **/
		// };
		cfg.language.emptyTable = this._base_data.strings.no_rules_yet;
		return cfg;
	}

	bindEvents() {
		super.bindEvents();

		this.$el.on(
			'click',
			'input[type=checkbox].active-switch',
			( evt ) => {
				evt.preventDefault();
				const target = evt.currentTarget;
				confirmSecurityRuleAction( target, target.dataset.sub_action !== 'activate' ).then( ( confirmed ) => {
					if ( confirmed ) {
						this.bulkTableAction.call( this, target.dataset.sub_action, [ target.dataset.rid ] );
					}
				} );
				return false;
			}
		);

		this.$el.on(
			'click',
			'button',
			( evt ) => {
				evt.preventDefault();
				const target = evt.currentTarget;
				confirmSecurityRuleAction( target, true ).then( ( confirmed ) => {
					if ( confirmed ) {
						this.bulkTableAction.call( this, target.dataset.sub_action, [ target.dataset.rid ] );
					}
				} );
				return false;
			}
		);

		[ 'xhr', 'draw', 'select', 'deselect' ].forEach( ( event ) => {
			this.$table.on( event, () => this.rowSelectionChanged() );
		} );
	}

	getButtons() {
		let buttons = super.getButtons();
		buttons.push(
			{
				text: 'Deactivate All',
				name: 'deactivate_all',
				className: 'deactivate-all action btn-outline-warning mb-2',
				action: async ( e, dt, node ) => {
					if ( await confirmSecurityRuleAction( resolveDialogLauncher( e, node ), true ) ) {
						dt.rows().select();
						this.bulkTableAction.call( this, 'deactivate_all' );
					}
				}
			},
			{
				text: 'Create New Rule',
				name: 'create_new',
				className: 'create-new action btn-outline-success mb-2',
				action: ( e, dt, node, config ) => {
					/* https://stackoverflow.com/questions/2914/how-can-i-detect-if-a-browser-is-blocking-a-popup/27725432#27725432 */
					window.location = this._base_data.hrefs.create_new;
				}
			},
		);
		return buttons;
	}

	datatablesAjaxRequest( data, callback, settings ) {
		return super.datatablesAjaxRequest( data, callback, settings )
					.finally( () => this.attachSortable() );
	}

	attachSortable() {
		const group = document.querySelector( '#ShieldTable-SecurityRulesManager tbody' );
		if ( group ) {
			/** https://github.com/SortableJS/Sortable **/
			Sortable.create( group, {
				animation: 600,
				easing: 'cubic-bezier(1, 0, 0, 1)',
				handle: '.drag',

				ghostClass: 'list-group-item-info',
				chosenClass: 'list-group-item-info',
				dragClass: 'list-group-item-info',

				onEnd: ( evt ) => {
					if ( evt.newIndex !== evt.oldIndex ) {
						const items = [];
						group.querySelectorAll( 'td.drag > div' )
							 .forEach( ( item ) => {
								 if ( item instanceof HTMLElement ) {
								 	items.push( item.dataset.rid );
								 }
							 } );
						this.action( {
							sub_action: 'reorder',
							rids: items,
						} );
					}
				},
			} );
		}
	}

	action( params = {} ) {
		return this.sendTableActionRequest(
			this.$table,
			ObjectOps.Merge( this._base_data.ajax.table_action, params ),
			'Communications error with site.',
			{ reloadTableOnSuccess: true }
		);
	}
}

function confirmSecurityRuleAction( launcher, danger = false ) {
	return confirmDialog( {
		message: shieldStrings.string( 'are_you_sure' ),
		confirmLabel: resolveDialogConfirmLabel( launcher ),
		danger,
		launcher,
	} );
}
