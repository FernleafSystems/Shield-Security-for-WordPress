import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import MicroModal from 'micromodal'

export class HackGuardPluginReinstall extends BaseComponent {

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

		document.querySelectorAll( 'table.wp-list-table.plugins > tbody  > tr' ).forEach( ( row ) => {
			let plugin = row.dataset.plugin ?? false;
			if ( plugin && this._base_data.vars.reinstallable.indexOf( plugin ) >= 0 ) {
				row.classList.add( 'reinstallable' );
			}
		} );

		shieldEventsHandler_Main.add_Click( 'tr.reinstallable .row-actions .shield-reinstall a', ( targetEl ) => this.promptReinstall( targetEl ) );
		shieldEventsHandler_Main.add_Click( 'tr.reinstallable .row-actions .activate a', ( targetEl ) => this.promptActivate( targetEl ) );

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

	promptReinstall( targetEl ) {
		MicroModal.show( this.dialogReinstall.id );

		let current = targetEl.closest( 'tr' );
		this.active_modal_id = this.dialogReinstall.id;
		this.doActivate = 0;
		this.activeFile = current.dataset[ 'plugin' ];

		const activateHref = current.querySelector( 'span.activate > a' );
		this.hrefActivate = activateHref ? activateHref.getAttribute( 'href' ) : '';
	};

	promptActivate( targetEl ) {
		MicroModal.show( this.dialogActivateReinstall.id );
		let current = targetEl.closest( 'tr' );
		this.active_modal_id = this.dialogReinstall.id;
		this.doActivate = 1;
		this.activeFile = current.dataset[ 'plugin' ];

		const activateHref = current.querySelector( 'span.activate > a' );
		this.hrefActivate = activateHref ? activateHref.getAttribute( 'href' ) : '';
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