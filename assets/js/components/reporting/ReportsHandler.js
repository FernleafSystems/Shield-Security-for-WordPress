import { BaseComponent } from "../BaseComponent";

export class ReportsHandler extends BaseComponent {

	init() {
		this.changeReportSection = document.getElementById( 'ChangeTrackingReport' ) || false;
		this.exec();
	}

	canRun() {
		return this.changeReportSection;
	}

	run() {
		shieldEventsHandler_Reports.add_Click( 'input[name=change_report_type]', ( targetEl ) => {
			this.changeReportSection.querySelectorAll( 'div.detailed' ).forEach( ( elem ) => {
				if ( targetEl.value === 'detailed' ) {
					elem.classList.remove( 'd-none' )
				}
				else if ( !elem.classList.contains( 'd-none') ) {
					elem.classList.add( 'd-none' )
				}
			} );
		}, false );
	}
}