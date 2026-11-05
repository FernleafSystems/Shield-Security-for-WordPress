import { ScanItemAnalysisModal } from "../scans/ScanItemAnalysisModal";

/**
 * @typedef {object} ScanResultsRowActionOptions
 * @property {JQuery<Element>|null} [$tableElement]
 * @property {any} [datatable]
 * @property {any} [scanResultsAction]
 * @property {any} [renderItemAnalysis]
 * @property {((action: string, rids?: string[], launcher?: HTMLElement|null) => void)|null} [onAction]
 * @property {string} [namespace]
 */

/**
 * The table's own controls, above it: select all (or deselect all, once everything is selected), what results
 * to display, and reload. None of them changes a result.
 */
export function buildScanResultsToolbarButtons( {
	onReload = null,
	displayFilters = null,
} = {} ) {
	/** @type {any[]} */
	const buttons = [
		{
			text: 'Select all',
			name: 'all-select',
			className: 'select-all action btn-outline-secondary',
			attr: {
				'data-scan-results-select-all': '1',
			},
			action: ( e, dt ) => {
				if ( isEverythingSelected( dt ) ) {
					dt.rows().deselect();
				}
				else {
					dt.rows().select();
				}
				syncScanResultsSelectionButtons( dt );
			}
		},
	];

	if ( displayFilters && typeof displayFilters.onToggle === 'function' ) {
		buttons.push( {
			extend: 'collection',
			text: 'Display results',
			name: 'display-results',
			className: 'action display-results btn-outline-secondary',
			autoClose: false,
			attr: {
				'data-scan-results-display-collection': '1',
			},
			buttons: [
				{
					text: 'Show Ignored Results',
					name: 'display-filter-ignored',
					className: 'scan-results-display-filter btn-outline-secondary',
					attr: {
						'data-scan-results-display-filter': '1',
						'data-scan-results-display-option': 'include_ignored',
					},
					action: ( e, dt ) => displayFilters.onToggle( 'include_ignored', dt ),
				},
				{
					text: 'Show Repaired Results',
					name: 'display-filter-repaired',
					className: 'scan-results-display-filter btn-outline-secondary',
					attr: {
						'data-scan-results-display-filter': '1',
						'data-scan-results-display-option': 'include_repaired',
					},
					action: ( e, dt ) => displayFilters.onToggle( 'include_repaired', dt ),
				},
				{
					text: 'Show Deleted Results',
					name: 'display-filter-deleted',
					className: 'scan-results-display-filter btn-outline-secondary',
					attr: {
						'data-scan-results-display-filter': '1',
						'data-scan-results-display-option': 'include_deleted',
					},
					action: ( e, dt ) => displayFilters.onToggle( 'include_deleted', dt ),
				},
			],
		} );
	}

	if ( typeof onReload === 'function' ) {
		buttons.push( {
			text: 'Reload table',
			name: 'table-reload',
			className: 'action table-refresh btn-outline-secondary',
			action: () => onReload(),
		} );
	}

	return buttons;
}

/**
 * What changes results, in a column beside the table: what to do with the selected results (only once there are
 * some), then what applies to all of them, then the scan's own tools.
 */
export function buildScanResultsActionButtons( {
	onBulkAction = null,
	onIgnoreAll = null,
	malwareRefresh = null,
	assetActions = null,
} = {} ) {
	/** @type {any[]} */
	const buttons = [];

	if ( typeof onBulkAction === 'function' ) {
		buttons.push(
			{
				text: 'Repair or delete',
				name: 'selected-repair',
				className: 'action selected-action repair btn-outline-danger',
				action: async ( e, dt, node ) => {
					const launcher = shieldServices.dialog().resolveLauncher( e, node );
					if ( dt.rows( { selected: true } ).count() > 20 ) {
						await shieldServices.dialog().message( {
							title: shieldStrings.string( 'dialog_alert_title' ),
							message: shieldStrings.string( 'scan_repair_limit_exceeded' ),
							confirmLabel: shieldStrings.string( 'close' ),
							launcher,
						} );
					}
					else if ( await confirmScanResultsBulkAction( {
						message: shieldStrings.string( 'absolutely_sure' ),
						danger: true,
						launcher,
					} ) ) {
						onBulkAction( 'repair-delete' );
					}
				}
			},
			{
				text: 'Ignore',
				name: 'selected-ignore',
				className: 'action selected-action ignore btn-outline-secondary',
				action: async ( e, dt, node ) => {
					const confirmed = await confirmScanResultsBulkAction( {
						message: shieldStrings.string( 'are_you_sure' ),
						launcher: shieldServices.dialog().resolveLauncher( e, node ),
					} );

					if ( confirmed ) {
						onBulkAction( 'ignore' );
					}
				}
			},
			{
				text: 'Unignore',
				name: 'selected-unignore',
				className: 'action selected-action unignore btn-outline-secondary',
				action: async ( e, dt, node ) => {
					const confirmed = await confirmScanResultsBulkAction( {
						message: shieldStrings.string( 'are_you_sure' ),
						launcher: shieldServices.dialog().resolveLauncher( e, node ),
					} );

					if ( confirmed ) {
						onBulkAction( 'unignore' );
					}
				}
			},
			{
				text: 'Clear selection',
				name: 'selection-clear',
				className: 'action selection-clear btn-link',
				action: ( e, dt ) => {
					dt.rows().deselect();
					syncScanResultsSelectionButtons( dt );
				}
			}
		);
	}

	if ( typeof onIgnoreAll === 'function' ) {
		buttons.push( {
			text: 'Ignore all results',
			name: 'all-ignore',
			className: 'action all-ignore btn-outline-secondary',
			attr: {
				'data-scan-results-group-start': 'all',
			},
			action: async ( e, dt, node ) => {
				const confirmed = await confirmScanResultsBulkAction( {
					message: 'Ignore every active result in this table?',
					danger: true,
					launcher: shieldServices.dialog().resolveLauncher( e, node ),
				} );
				if ( confirmed ) {
					onIgnoreAll( shieldServices.dialog().resolveLauncher( e, node ) );
				}
			}
		} );
	}

	if ( malwareRefresh !== null ) {
		buttons.push( {
			text: malwareRefresh.label,
			titleAttr: malwareRefresh.description,
			name: 'refresh-malware-assessments',
			className: 'action btn-outline-secondary',
			attr: {
				'data-scan-results-group-start': 'tools',
			},
			action: ( e, dt, node ) => malwareRefresh.onRefresh( shieldServices.dialog().resolveLauncher( e, node ) ),
		} );
	}

	// What acts on the whole plugin or theme, such as reinstalling it, under its own heading.
	( assetActions?.actions || [] ).forEach( ( assetAction, index ) => {
		buttons.push( {
			text: `<i class="${assetAction.icon_class || ''}" aria-hidden="true"></i> ${assetAction.label}`,
			name: `asset-action-${index}`,
			className: 'action btn-outline-secondary',
			attr: index === 0 ? { 'data-scan-results-group-start': 'asset' } : {},
			action: ( e, dt, node ) => assetActions.onAction( assetAction, shieldServices.dialog().resolveLauncher( e, node ) ),
		} );
	} );

	return buttons;
}

/**
 * @param {any} dt
 * @return {boolean}
 */
function isEverythingSelected( dt ) {
	const total = dt.rows().count();
	return total > 0 && dt.rows( { selected: true } ).count() >= total;
}

function confirmScanResultsBulkAction( {
	message = '',
	danger = false,
	launcher = null,
} = {} ) {
	return shieldServices.dialog().confirm( {
		title: shieldStrings.string( 'dialog_confirm_title' ),
		message,
		confirmLabel: shieldStrings.string( 'confirm' ),
		cancelLabel: shieldStrings.string( 'cancel' ),
		danger,
		launcher,
	} );
}

/**
 * @param {ScanResultsRowActionOptions} [options]
 */
export function bindScanResultsRowActions( {
	$tableElement,
	datatable,
	scanResultsAction = null,
	renderItemAnalysis = null,
	onAction = null,
	namespace = 'shieldScanResults'
} = {} ) {
	if ( !$tableElement || typeof $tableElement.off !== 'function' ) {
		return;
	}

	$tableElement.off( `.${namespace}` );

	if ( scanResultsAction !== null && typeof onAction === 'function' ) {
		$tableElement.on(
			`click.${namespace}`,
			'td.actions [data-scan-result-action="delete"], td.actions [data-scan-result-action="ignore"], td.actions [data-scan-result-action="unignore"], td.actions [data-scan-result-action="repair"]',
			async ( evt ) => {
				evt.preventDefault();
				evt.stopPropagation();

				const target = evt.currentTarget;
				const action = target.dataset.scanResultAction;

				if ( action === 'delete' ) {
					const dialog = shieldServices.dialog();
					const confirmed = await dialog.confirm( {
						title: shieldStrings.string( 'dialog_confirm_title' ),
						message: shieldStrings.string( 'are_you_sure' ),
						confirmLabel: dialog.resolveConfirmLabel( target ),
						cancelLabel: shieldStrings.string( 'cancel' ),
						danger: true,
						launcher: target,
					} );

					if ( !confirmed ) {
						return false;
					}
				}

				if ( action === 'repair' ) {
					datatable?.rows?.().deselect?.();
				}
				onAction( action, [ target.dataset.rid ], target );
				return false;
			}
		);
	}

	if ( renderItemAnalysis !== null ) {
		$tableElement.on(
			`click.${namespace}`,
			'[data-scan-result-action="view"]',
			( evt ) => {
				evt.preventDefault();
				ScanItemAnalysisModal.show( renderItemAnalysis, evt.currentTarget.dataset.rid );
				return false;
			}
		);
	}
}

export function bindScanResultsSelectionButtons( datatable, namespace = 'shieldScanResultsButtons' ) {
	if ( datatable === null || typeof datatable.on !== 'function' ) {
		return;
	}

	datatable.off( `.${namespace}` );
	[ 'xhr', 'draw', 'select', 'deselect' ].forEach( ( eventName ) => {
		datatable.on( `${eventName}.${namespace}`, () => syncScanResultsSelectionButtons( datatable ) );
	} );
	syncScanResultsSelectionButtons( datatable );
}

export function syncScanResultsSelectionButtons( datatable ) {
	const selectionState = getScanResultsSelectionState( datatable );
	[
		[ 'selected-ignore:name', selectionState.hasActiveSelections ],
		[ 'selected-unignore:name', selectionState.hasIgnoredSelections ],
		[ 'selected-repair:name', selectionState.hasSelections ],
		[ 'selection-clear:name', selectionState.hasSelections ],
	].forEach( ( [ selector, isEnabled ] ) => {
		const button = datatable.button( selector );
		if ( button.length > 0 || button.node?.()?.length > 0 ) {
			button[ isEnabled ? 'enable' : 'disable' ]();
		}
	} );

	// One button selects all, or deselects all once everything is selected; its name says which it will do.
	const toggle = datatable.button( 'all-select:name' );
	if ( toggle.node?.()?.length > 0 ) {
		toggle.text( isEverythingSelected( datatable ) ? 'Deselect all' : 'Select all' );
		toggle.enable( datatable.rows().count() > 0 );
	}
}

export function getScanResultsSelectionState( datatable ) {
	const state = {
		hasSelections: false,
		hasActiveSelections: false,
		hasIgnoredSelections: false,
	};

	datatable.rows( { selected: true } ).every( function () {
		state.hasSelections = true;
		if ( Boolean( this.data()?.is_ignored ) ) {
			state.hasIgnoredSelections = true;
		}
		else {
			state.hasActiveSelections = true;
		}
	} );

	return state;
}

export function syncScanResultsDisplayButtons( datatable, resultsDisplayOptions = {} ) {
	const displayOptions = normalizeResultsDisplayOptions( resultsDisplayOptions );

	[
		[ 'display-filter-ignored:name', displayOptions.include_ignored ],
		[ 'display-filter-repaired:name', displayOptions.include_repaired ],
		[ 'display-filter-deleted:name', displayOptions.include_deleted ],
		[ 'display-results:name', displayOptions.include_ignored || displayOptions.include_repaired || displayOptions.include_deleted ],
	].forEach( ( [ selector, isActive ] ) => {
		const button = datatable.button( selector );
		if ( button && typeof button.active === 'function' ) {
			button.active( Boolean( isActive ) );
		}
	} );
}

export function normalizeResultsDisplayOptions( options = {} ) {
	const normalized = {
		include_ignored: Boolean( options?.include_ignored ),
		include_repaired: Boolean( options?.include_repaired ),
		include_deleted: Boolean( options?.include_deleted ),
		ignored_only: Boolean( options?.ignored_only ),
	};

	if ( normalized.ignored_only ) {
		normalized.include_ignored = true;
	}

	return normalized;
}
