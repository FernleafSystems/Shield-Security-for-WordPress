import { ShieldTableBase } from "./ShieldTableBase";
import { Popover } from "bootstrap";
import { AjaxService } from "../services/AjaxService";
import { PageQueryParam } from "../../util/PageQueryParam";

export class ShieldTableActivityLog extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-ActivityLog';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.select = {
			style: 'api'
		};

		const search = PageQueryParam.Retrieve( 'search' );
		if ( typeof search === 'string' && search.length > 0 ) {
			cfg.search = { search };
		}
		return cfg;
	}

	run() {
		super.run();

		new Popover( 'body', {
			selector: 'td.meta > button[data-toggle="popover"]',
			trigger: 'click',
			sanitize: false,
			html: true,
			animation: true,
			customClass: 'audit-meta',
			placement: 'left',
			container: 'body',
			title: 'Request Meta Info',
			content: ( element ) => {
				let reqData = this._base_data.ajax.table_action;
				reqData.sub_action = 'get_request_meta';
				reqData.rid = element.dataset.rid;
				reqData.apto_wrap_response = 1;

				( new AjaxService() )
				.send( reqData, false )
				.then( ( resp ) => {
					Popover.getInstance( element ).setContent( {
						'.popover-body': resp.data.html
					} );
				} )
				.finally();

				return 'Loading Meta Info...';
			},
		} );
	}
}
