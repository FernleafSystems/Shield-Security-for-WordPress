import { Popover } from "bootstrap";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

const META_BUTTON_SELECTOR = 'td.meta > button[data-toggle="popover"]';

export function bindActivityLogMetaPopover( rootEl, tableAction ) {
	if ( !( rootEl instanceof HTMLElement ) || tableAction === null || typeof tableAction !== 'object' ) {
		return;
	}

	if ( rootEl.dataset.shieldActivityMetaPopoverBound === '1' ) {
		return;
	}

	rootEl.dataset.shieldActivityMetaPopoverBound = '1';
	new Popover( rootEl, {
		selector: META_BUTTON_SELECTOR,
		trigger: 'click',
		sanitize: false,
		html: true,
		animation: true,
		customClass: 'audit-meta',
		placement: 'left',
		container: resolvePopoverContainer( rootEl ),
		title: 'Request Meta Info',
		content: ( element ) => {
			const reqData = ObjectOps.ObjClone( tableAction );
			reqData.sub_action = 'get_request_meta';
			reqData.rid = element.dataset.rid || '';
			reqData.apto_wrap_response = 1;

			( new AjaxService() )
			.send( reqData, false )
			.then( ( resp ) => {
				Popover.getInstance( element )?.setContent( {
					'.popover-body': resp?.data?.html || '',
				} );
			} );

			return 'Loading Meta Info...';
		},
	} );
}

function resolvePopoverContainer( rootEl ) {
	return rootEl.closest( '.offcanvas' ) || document.body;
}
