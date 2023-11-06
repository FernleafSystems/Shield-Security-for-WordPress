import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";

export class Dialog extends BaseComponent {

	static Show( $dialog, options ) {
		$dialog.dialog( $.extend( {
			classes: {
				'ui-dialog': 'shield_dialog'
			}
		}, options ) );
	};
}