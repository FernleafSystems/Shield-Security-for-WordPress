import $ from 'jquery';
import { BaseService } from "./BaseService";

export class Dialog extends BaseService {

	static Show( $dialog, options ) {
		$dialog.dialog( $.extend( {
			classes: {
				'ui-dialog': 'shield_dialog'
			}
		}, options ) );
	};
}