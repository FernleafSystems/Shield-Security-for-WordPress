import { ScanItemAnalysisModal } from "../scans/ScanItemAnalysisModal";

/**
 * @typedef {object} ScanResultsRowActionOptions
 * @property {JQuery<Element>|null} [$tableElement]
 * @property {any} [datatable]
 * @property {any} [scanResultsAction]
 * @property {any} [renderItemAnalysis]
 * @property {((action: string, rids?: string[]) => void)|null} [onAction]
 * @property {string} [namespace]
 */

export function buildScanResultsButtons( { includeReload = false, onReload = null, onBulkAction = null } = {} ) {
	const buttons = [];

	if ( includeReload && typeof onReload === 'function' ) {
		buttons.push( {
			text: 'Reload Table',
			name: 'table-reload',
			className: 'action table-refresh btn-outline-secondary mb-2',
			action: () => onReload(),
		} );
	}

	if ( typeof onBulkAction !== 'function' ) {
		return buttons;
	}

	buttons.push(
		{
			text: 'De/Select All',
			name: 'all-select',
			className: 'select-all action btn-outline-secondary mb-2',
			action: ( e, dt ) => {
				if ( dt.rows( { selected: true } ).count() < dt.rows().count() ) {
					dt.rows().select();
				}
				else {
					dt.rows().deselect();
				}
				syncScanResultsSelectionButtons( dt );
			}
		},
		{
			text: 'Ignore Selected',
			name: 'selected-ignore',
			className: 'action selected-action ignore btn-outline-secondary mb-2',
			action: () => {
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					onBulkAction( 'ignore' );
				}
			}
		},
		{
			text: 'Delete/Repair Selected',
			name: 'selected-repair',
			className: 'action selected-action repair btn-outline-secondary mb-2',
			action: ( e, dt ) => {
				if ( dt.rows( { selected: true } ).count() > 20 ) {
					alert( "Sorry, this tool isn't designed for such large repairs. We recommend completely removing and reinstalling the item." );
				}
				else if ( confirm( shieldStrings.string( 'absolutely_sure' ) ) ) {
					onBulkAction( 'repair-delete' );
				}
			}
		}
	);

	return buttons;
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
			'td.actions > button.action.delete',
			( evt ) => {
				evt.preventDefault();
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					onAction( 'delete', [ evt.currentTarget.dataset.rid ] );
				}
				return false;
			}
		);

		$tableElement.on(
			`click.${namespace}`,
			'td.actions > button.action.ignore',
			( evt ) => {
				evt.preventDefault();
				onAction( 'ignore', [ evt.currentTarget.dataset.rid ] );
				return false;
			}
		);

		$tableElement.on(
			`click.${namespace}`,
			'td.actions > button.action.repair',
			( evt ) => {
				evt.preventDefault();
				datatable?.rows?.().deselect?.();
				onAction( 'repair', [ evt.currentTarget.dataset.rid ] );
				return false;
			}
		);
	}

	if ( renderItemAnalysis !== null ) {
		$tableElement.on(
			`click.${namespace}`,
			'.action.view-file',
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
	const hasSelections = datatable.rows( { selected: true } ).count() > 0;
	datatable.buttons( 'selected-ignore:name, selected-repair:name' )[ hasSelections ? 'enable' : 'disable' ]();
}
