import $ from 'jquery';
import { BaseService } from "./BaseService";

export class HelpscoutBeacon extends BaseService {

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

		$( document ).on( 'click', 'a.beacon-article', ( evt ) => {
			evt.preventDefault();
			let link = evt.currentTarget;
			if ( link.dataset[ 'beacon_article_id' ] ?? false ) {
				let format = '';
				if ( link.dataset[ 'beacon_article_format' ] ?? false ) {
					format = link.dataset[ 'beacon_article_format' ];
				}
				Beacon( 'article', String( link.dataset[ 'beacon_article_id' ] ), { type: format } );
			}
			return false;
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