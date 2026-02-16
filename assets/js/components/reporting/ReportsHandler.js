import { BaseComponent } from "../BaseComponent";

export class ReportsHandler extends BaseComponent {

	init() {
		this.changeReportToggles = document.querySelectorAll( 'input[name=change_report_type]' );
		this.exec();
		this.syncInitialState();
	}

	canRun() {
		return this.changeReportToggles.length > 0;
	}

	run() {
		shieldEventsHandler_Reports.add_Change( 'input[name=change_report_type]', ( targetEl ) => {
			this.toggleDetails( targetEl );
		}, false );
	}

	syncInitialState() {
		this.changeReportToggles.forEach( ( inputEl ) => {
			if ( inputEl.checked ) {
				this.toggleDetails( inputEl );
			}
		} );
	}

	toggleDetails( inputEl ) {
		const reportSection = inputEl.closest( '.report-section' ) || document;
		const showDetailed = inputEl.value === 'detailed';

		reportSection.querySelectorAll( 'div.detailed' ).forEach( ( elem ) => {
			if ( showDetailed ) {
				elem.classList.remove( 'd-none' );
			}
			else if ( !elem.classList.contains( 'd-none' ) ) {
				elem.classList.add( 'd-none' );
			}
		} );
	}
}
