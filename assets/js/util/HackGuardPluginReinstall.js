import $ from 'jquery';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import MicroModal from 'micromodal'

export class HackGuardPluginReinstall extends BaseService {

	init() {
		this.dialogActivateReinstall = document.getElementById( 'ShieldMicroModal-ActivateReinstall' ) || false;
		this.dialogReinstall = document.getElementById( 'ShieldMicroModal-Reinstall' ) || false;
		this.exec();
	}

	canRun() {
		return this.dialogActivateReinstall && this.dialogReinstall
			&& typeof this._base_data !== 'undefined' && !ObjectOps.IsEmpty( this._base_data );
	}

	run() {
		const self = this;

		$( 'table.wp-list-table.plugins > tbody  > tr' ).each( ( idx, element ) => {
			let $row = $( element );
			let plugin = element.dataset.plugin ?? false;
			if ( plugin && this._base_data.vars.reinstallable.indexOf( plugin ) >= 0 ) {
				$row.addClass( 'reinstallable' );
			}
		} );

		$( document ).on( 'click', 'tr.reinstallable .row-actions .shield-reinstall a', ( evt ) => this.promptReinstall( evt ) );
		$( document ).on( 'click', 'tr.reinstallable .row-actions .activate a', ( evt ) => this.promptActivate( evt ) );

		this.dialogReinstall.querySelector( '.reinstall' ).addEventListener( 'click', () => {
			self.#reinstall_plugin.call( self );
		}, false );

		this.dialogActivateReinstall.querySelector( '.reinstall' ).addEventListener( 'click', () => {
			self.#reinstall_plugin.call( self );
		}, false );

		this.dialogActivateReinstall.querySelector( '.activate' ).addEventListener( 'click', () => {
			window.location.assign( self.hrefActivate )
		}, false );
	}

	/*
	initDialogsJquery() {
		let commonSettings = {
			title: 'Re-Install Plugin',
			dialogClass: 'wp-dialog',
			autoOpen: false,
			draggable: false,
			width: 'auto',
			modal: true,
			resizable: false,
			closeOnEscape: true,
			position: {
				my: "center",
				at: "center",
				of: window
			},
			open: function () {
				// close dialog by clicking the overlay behind it
				$( '.ui-widget-overlay' ).on( 'click', function () {
					$( this ).dialog( 'close' );
				} )
			},
			create: function () {
				// style fix for WordPress admin
				$( '.ui-dialog-titlebar-close' ).addClass( 'ui-button' );
			}
		};

		commonSettings[ 'buttons' ] = [
			{
				text: this._base_data.strings.okay_reinstall,
				id: 'btnOkayReinstall',
				click: () => {
					this.dialogReinstall.dialog( 'close' );
					this.#reinstall_plugin( 1 );
				}
			},
			{
				text: this._base_data.strings.cancel,
				id: 'btnCancel',
				click: () => this.dialogReinstall.dialog( 'close' )
			}
		];
		this.dialogReinstall.dialog( commonSettings );

		commonSettings[ 'buttons' ] = [
			{
				text: this._base_data.strings.reinstall_first,
				id: 'btnReinstallFirst',
				click: () => {
					this.dialogActivateReinstall.dialog( 'close' );
					this.#reinstall_plugin( 1 );
				}
			},
			{
				text: this._base_data.strings.activate_only,
				id: 'btnActivateOnly',
				click: () => window.location.assign( this.hrefActivate )
			}
		];
		this.dialogActivateReinstall.dialog( commonSettings );
	}*/

	promptReinstall( evt ) {
		evt.preventDefault();

		MicroModal.show( this.dialogReinstall.id );

		let $current = $( evt.currentTarget ).closest( 'tr' );
		this.active_modal_id = this.dialogReinstall.id;
		this.doActivate = 0;
		this.activeFile = $current.data( 'plugin' );
		this.hrefActivate = $( 'span.activate > a', $current ).attr( 'href' )

		return false;
	};

	promptActivate( evt ) {
		evt.preventDefault();

		MicroModal.show( this.dialogActivateReinstall.id );
		let $current = $( evt.currentTarget ).closest( 'tr' );
		this.active_modal_id = this.dialogReinstall.id;
		this.doActivate = 1;
		this.activeFile = $current.data( 'plugin' );
		this.hrefActivate = $( 'span.activate > a', $current ).attr( 'href' )

		return false;
	};

	#reinstall_plugin() {
		let data = ObjectOps.ObjClone( this._base_data.ajax.plugin_reinstall );
		data[ 'file' ] = this.activeFile;
		data[ 'reinstall' ] = 1;

		( new AjaxService() )
		.send( data )
		.finally( () => {
			MicroModal.close( this.active_modal_id );
			( this.hrefActivate && this.doActivate ) ? window.location.assign( this.hrefActivate ) : window.location.reload();
		} );

		return false;
	};
}