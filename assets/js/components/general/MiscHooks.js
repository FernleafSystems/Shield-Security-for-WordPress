import BigPicture from "bigpicture";
import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Modal } from 'bootstrap';
import { ShieldOverlay } from "../ui/ShieldOverlay";

export class MiscHooks extends BaseComponent {
	init() {
		shieldEventsHandler_Main.add_Click( '.option-video', ( targetEl ) => {
			BigPicture( {
				el: targetEl,
				vimeoSrc: targetEl.dataset[ 'vimeoid' ],
			} );
		} );

		this.showShield20IntroVideo();
	}

	showShield20IntroVideo() {
		if ( this._base_data.flags.show_video ) {
			( new AjaxService() )
			.send( this._base_data.ajax.render_intro_video_modal )
			.then( ( resp ) => {
				if ( resp.success ) {
					let modalContainer = document.getElementById( 'ShieldIntroVideoModal' ) || false;
					if ( !modalContainer ) {
						modalContainer = document.getElementById( 'ShieldModalContainer' ).cloneNode( true );
						modalContainer.id = 'ShieldIntroVideoModal';
						modalContainer.querySelector( '.modal-dialog' ).classList.add( 'modal-dialog-centered' );

						modalContainer.dataset[ 'bsBackdrop' ] = 'static';
						modalContainer.dataset[ 'bsKeyboard' ] = 'false';

						shieldEventsHandler_Main.addHandler(
							'hidden.bs.modal',
							'#ShieldIntroVideoModal',
							() => ( new AjaxService() )
							.send( this._base_data.ajax.set_flag_shield_intro_video_closed )
							.finally( () => modalContainer.remove() )
						);
					}
					modalContainer.querySelector( '.modal-content' ).innerHTML = resp.data.html;
					const myModal = new Modal( modalContainer, {} );
					myModal.show();
				}
				else {
					alert( resp.data.message );
				}
			} )
			.catch( ( error ) => {
				console.log( error );
			} )
			.finally( () => ShieldOverlay.Hide() );
		}
	}
}