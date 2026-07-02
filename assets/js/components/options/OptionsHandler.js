import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OptionsFormSubmit } from "./OptionsFormSubmit";
import { AjaxService } from "../services/AjaxService";
import { Tooltip } from 'bootstrap';

export class OptionsHandler extends BaseAutoExecComponent {

	run() {
		new OptionsFormSubmit( this._base_data );
		this.initializeProfileSearchInputs();

		shieldEventsHandler_Main.add_Click(
			'form.options_form_for .toggle-importexport-inclusion > input[type=checkbox]',
			( targetEl ) => {
				const form = targetEl.closest( 'form.options_form_for' );
				if ( !form ) {
					return;
				}
				const actionKey = form.dataset.transferAction;
				if ( !actionKey || !( actionKey in this._base_data.ajax ) ) {
					return;
				}
				( new AjaxService() )
				.bg(
					ObjectOps.Merge( this._base_data.ajax[ actionKey ], {
						key: targetEl.dataset.key,
						status: targetEl.checked ? 'include' : 'exclude'
					} )
				)
				.then( respJSON => {
					shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
					return respJSON;
				} )
				.finally();
			},
			false
		);

		shieldEventsHandler_Main.add_Click(
			'form.import-export-profile-options-form [data-import-export-profile-sync-toggle]',
			( targetEl ) => {
				this.toggleProfileOptionSync( targetEl );
			},
			false
		);

		shieldEventsHandler_Main.add_Click(
			'form.import-export-profile-options-form [data-import-export-profile-group-sync-toggle]',
			( targetEl ) => {
				this.toggleProfileGroupSync( targetEl );
			},
			false
		);

		shieldEventsHandler_Main.add_Click(
			'form.options_form_for .option-description-expander',
			( targetEl ) => {
				const descriptionTarget = ( targetEl.getAttribute( 'aria-controls' ) || '' ).trim();
				const toToggle = descriptionTarget.length > 0
					? document.getElementById( descriptionTarget )
					: null;
				if ( toToggle ) {
					const isHidden = toToggle.classList.toggle( 'hidden' );
					targetEl.setAttribute( 'aria-expanded', isHidden ? 'false' : 'true' );
					toToggle.setAttribute( 'aria-hidden', isHidden ? 'true' : 'false' );

					const item = targetEl.closest( '.shield-option-item' );
					if ( item ) {
						item.classList.toggle( 'shield-option-item-expanded', !isHidden );
					}

					const tip = Tooltip.getInstance( targetEl );
					if ( tip ) {
						tip.hide();
					}
				}
			},
			false
		);

		shieldEventsHandler_Main.add_Change(
			'form.options_form_for .form-switch .form-check-input',
			( targetEl ) => {
				const stateLabel = targetEl.closest( '.form-switch' )?.querySelector( '.shield-option-switch-state' );
				if ( stateLabel ) {
					stateLabel.textContent = targetEl.checked ? 'Enabled' : 'Disabled';
					stateLabel.classList.toggle( 'on', targetEl.checked );
				}
			},
			false
		);
	}

	initializeProfileSearchInputs() {
		document
		.querySelectorAll( 'form.import-export-profile-options-form [data-import-export-profile-search]' )
		.forEach( ( input ) => {
			if ( input.dataset.profileSearchReady === '1' ) {
				return;
			}
			input.dataset.profileSearchReady = '1';
			input.addEventListener( 'input', () => this.filterProfileOptions( input ) );
		} );
	}

	toggleProfileOptionSync( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-profile-sync-toggle]' ) : null;
		if ( !( button instanceof HTMLButtonElement ) || button.disabled ) {
			return;
		}

		const form = button.closest( 'form.import-export-profile-options-form' );
		const row = button.closest( '[data-import-export-profile-option]' );
		const actionKey = form?.dataset.transferAction;
		const status = button.dataset.status;
		const key = button.dataset.key;
		if ( !form || !row || !actionKey || !( actionKey in this._base_data.ajax ) || !key || !status ) {
			return;
		}

		button.disabled = true;
		button.classList.add( 'is-syncing' );
		button.setAttribute( 'aria-busy', 'true' );
		( new AjaxService() )
		.bg(
			ObjectOps.Merge( this._base_data.ajax[ actionKey ], {
				key: key,
				status: status
			} )
		)
		.then( respJSON => {
			shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
			if ( respJSON.success ) {
				this.updateProfileOptionRow( row, status === 'include' );
				const group = row.closest( '[data-import-export-profile-group]' );
				if ( group ) {
					this.refreshProfileGroupState( group );
				}
			}
			return respJSON;
		} )
		.finally( () => {
			button.disabled = false;
			button.classList.remove( 'is-syncing' );
			button.removeAttribute( 'aria-busy' );
		} );
	}

	toggleProfileGroupSync( targetEl ) {
		const button = targetEl instanceof Element ? targetEl.closest( '[data-import-export-profile-group-sync-toggle]' ) : null;
		if ( !( button instanceof HTMLButtonElement ) || button.disabled ) {
			return;
		}

		const form = button.closest( 'form.import-export-profile-options-form' );
		const group = button.closest( '[data-import-export-profile-group]' );
		const actionKey = form?.dataset.transferGroupAction;
		const keys = button.dataset.keys;
		const status = button.dataset.status;
		if ( !form || !group || !actionKey || !( actionKey in this._base_data.ajax ) || !keys || !status ) {
			return;
		}

		button.disabled = true;
		button.classList.add( 'is-syncing' );
		button.setAttribute( 'aria-busy', 'true' );
		( new AjaxService() )
		.bg(
			ObjectOps.Merge( this._base_data.ajax[ actionKey ], {
				keys: keys,
				status: status
			} )
		)
		.then( respJSON => {
			shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
			if ( respJSON.success ) {
				group.querySelectorAll( '[data-import-export-profile-option]' ).forEach( ( row ) => {
					this.updateProfileOptionRow( row, status === 'include' );
				} );
				this.refreshProfileGroupState( group );
			}
			return respJSON;
		} )
		.finally( () => {
			button.disabled = false;
			button.classList.remove( 'is-syncing' );
			button.removeAttribute( 'aria-busy' );
		} );
	}

	updateProfileOptionRow( row, isIncluded ) {
		row.dataset.syncIncluded = isIncluded ? '1' : '0';
		row.classList.toggle( 'is-sync-excluded', !isIncluded );
		const button = row.querySelector( '[data-import-export-profile-sync-toggle]' );
		if ( button instanceof HTMLButtonElement ) {
			this.updateProfileSyncButton( button, isIncluded );
		}
	}

	refreshProfileGroupState( group ) {
		const rows = Array.from( group.querySelectorAll( '[data-import-export-profile-option]' ) );
		const includedCount = rows.filter( ( row ) => row.dataset.syncIncluded === '1' ).length;
		const optionCount = rows.length;
		const isIncluded = optionCount > 0 && includedCount === optionCount;
		const form = group.closest( 'form.import-export-profile-options-form' );
		const includedLabel = form?.dataset.includedLabel || 'included';

		group.dataset.includedCount = String( includedCount );
		group.dataset.optionCount = String( optionCount );

		const countLabel = group.querySelector( '[data-import-export-profile-group-count]' );
		if ( countLabel instanceof HTMLElement ) {
			countLabel.textContent = `${ includedCount }/${ optionCount } ${ includedLabel }`;
		}

		const button = group.querySelector( '[data-import-export-profile-group-sync-toggle]' );
		if ( button instanceof HTMLButtonElement ) {
			this.updateProfileSyncButton( button, isIncluded );
		}
	}

	updateProfileSyncButton( button, isIncluded ) {
		button.dataset.syncIncluded = isIncluded ? '1' : '0';
		button.dataset.status = isIncluded ? 'exclude' : 'include';
		button.classList.toggle( 'is-included', isIncluded );
		button.classList.toggle( 'is-excluded', !isIncluded );

		const label = isIncluded ? button.dataset.excludeLabel : button.dataset.includeLabel;
		if ( label && label.length > 0 ) {
			button.setAttribute( 'aria-label', label );
			button.dataset.bsTitle = label;
			const tip = Tooltip.getInstance( button );
			if ( tip ) {
				tip.dispose();
				new Tooltip( button );
			}
		}
	}

	filterProfileOptions( input ) {
		const form = input.closest( 'form.import-export-profile-options-form' );
		if ( !( form instanceof HTMLFormElement ) ) {
			return;
		}

		const query = String( input.value || '' ).trim().toLowerCase();
		let visibleGroups = 0;
		form.querySelectorAll( '[data-import-export-profile-group]' ).forEach( ( group ) => {
			let groupHasMatch = false;
			group.querySelectorAll( '[data-import-export-profile-section]' ).forEach( ( section ) => {
				let sectionHasMatch = false;
				section.querySelectorAll( '[data-import-export-profile-option]' ).forEach( ( row ) => {
					const searchText = String( row.dataset.searchText || '' );
					const rowMatches = query.length < 1 || searchText.includes( query );
					row.hidden = !rowMatches;
					sectionHasMatch = sectionHasMatch || rowMatches;
				} );
				section.hidden = !sectionHasMatch;
				groupHasMatch = groupHasMatch || sectionHasMatch;
			} );
			group.hidden = !groupHasMatch;
			if ( groupHasMatch ) {
				visibleGroups++;
			}
		} );

		const emptyState = form.querySelector( '[data-import-export-profile-empty]' );
		if ( emptyState instanceof HTMLElement ) {
			emptyState.hidden = query.length < 1 || visibleGroups > 0;
		}
	}
}
