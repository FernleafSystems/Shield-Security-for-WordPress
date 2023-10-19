import { BaseService } from "./BaseService";

export class ReportsHandler extends BaseService {

	init() {
		this.changeReportSection = document.getElementById( 'ChangeTrackingReport' ) || false;
		this.exec();
	}

	canRun() {
		return this.changeReportSection;
	}

	run() {
		this.changeReportSection.querySelectorAll( 'input[name=change_report_type]' ).forEach( ( input ) => {

			input.addEventListener( 'click', ( evt ) => {
				this.changeReportSection.querySelectorAll( 'div.detailed' ).forEach( ( elem ) => {
					if ( input.value === 'detailed' ) {
						elem.classList.remove( 'd-none' )
					}
					else if ( !elem.classList.contains( 'd-none') ) {
						elem.classList.add( 'd-none' )
					}
				} );
			}, false );

		} );
	}
}