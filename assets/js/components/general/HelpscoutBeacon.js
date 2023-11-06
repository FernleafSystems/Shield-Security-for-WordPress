import { BaseComponent } from "../BaseComponent";

export class HelpscoutBeacon extends BaseComponent {

	init() {
		this.exec();
	}

	canRun() {
		return this._base_data.visible;
	}

	run() {
		this.beaconInit();

		window.Beacon( 'init', this._base_data.beacon_id );
		Beacon( 'navigate', '/' );

		shieldEventsHandler_Main.add_Click( 'a.beacon-article', ( targetEl ) => {
			if ( targetEl.dataset[ 'beacon_article_id' ] ?? false ) {
				let format = '';
				if ( targetEl.dataset[ 'beacon_article_format' ] ?? false ) {
					format = targetEl.dataset[ 'beacon_article_format' ];
				}
				Beacon( 'article', String( targetEl.dataset[ 'beacon_article_id' ] ), { type: format } );
			}
		} );
	}

	beaconInit() {
		!function ( e, t, n ) {
			function a() {
				var e = t.getElementsByTagName( "script" )[ 0 ], n = t.createElement( "script" );
				n.type = "text/javascript", n.async = !0, n.src = "https://beacon-v2.helpscout.net", e.parentNode.insertBefore( n, e )
			}

			if ( e.Beacon = n = function ( t, n, a ) {
				e.Beacon.readyQueue.push( { method: t, options: n, data: a } )
			}, n.readyQueue = [], "complete" === t.readyState ) return a();
			e.attachEvent ? e.attachEvent( "onload", a ) : e.addEventListener( "load", a, !1 )
		}( window, document, window.Beacon || function () {
		} );
	};
}