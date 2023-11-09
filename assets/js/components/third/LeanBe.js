import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

export class LeanBe extends BaseAutoExecComponent {

	canRun() {
		return typeof this._base_data.vars.widget_key !== 'undefined';
	}

	run() {
		window.SGBFWidgetLoader = window.SGBFWidgetLoader || {
			ids: [], call: function ( w, d, s, l, id ) {
				w[ 'sgbf' ] = w[ 'sgbf' ] || function () {
					( w[ 'sgbf' ].q = w[ 'sgbf' ].q || [] ).push( arguments[ 0 ] );
				};
				var sgbf1 = d.createElement( s ), sgbf0 = d.getElementsByTagName( s )[ 0 ];
				if ( SGBFWidgetLoader && SGBFWidgetLoader.ids && SGBFWidgetLoader.ids.length > 0 ) {
					SGBFWidgetLoader.ids.push( id );
					return;
				}
				SGBFWidgetLoader.ids.push( id );
				sgbf1.onload = function () {
					var app = new SGBFLoader();
					app.run();
				};
				sgbf1.async = true;
				sgbf1.src = l;
				sgbf0.parentNode.insertBefore( sgbf1, sgbf0 );
				return {};
			}
		};
		SGBFWidgetLoader.call( window, document, "script", "https://leanbe.ai/assets/api/SGBFWidget.min.js", this._base_data.vars.widget_key );
	};
}