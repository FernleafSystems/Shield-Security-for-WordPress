import { BaseService } from "./BaseService";

export class ReportsHandler extends BaseService {

	init() {
		const changeReportSection = document.getElementById( 'ChangeTrackingReport' );
		changeReportSection.querySelectorAll( 'input[name=change_report_type]' ).forEach( ( input ) => {
			const value = this.value;
			if ( this.value === 'detailed' ) {
				changeReportSection.querySelectorAll( 'div.detailed.d-none' ).forEach( ( elem ) => {
					value === 'detailed' ? elem.classList.remove( 'd-none' ) : elem.classList.add( 'd-none' );
				} );
			}
		} );
	}
}