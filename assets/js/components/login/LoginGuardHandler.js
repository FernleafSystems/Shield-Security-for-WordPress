import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";

export class LoginGuardHandler extends BaseComponent {

	init() {
		if ( this._base_data.flags.gasp ) {
			this._base_data.form_selectors.forEach( ( selector, idx ) => {
				const elem = document.querySelector( selector );
				if ( elem ) {
					this.insertPlaceHolder_Gasp( elem );
				}
			} );
			document.querySelectorAll( 'p.shield_gasp_placeholder' ).forEach( ( p, idx ) => {
				this.processPlaceHolder_Gasp( p );
			} );
			document.querySelectorAll( 'form' ).forEach( ( DOMForm, idx ) => {
				this.cleanDuplicates( DOMForm );
			} );
		}
	}

	cleanDuplicates( DOMForm ) {
		let placeHolders = DOMForm.querySelectorAll( 'p.shield_gasp_placeholder' );
		if ( placeHolders.length > 1 ) {
			placeHolders.forEach( ( DOM_P, idx ) => {
				if ( idx > 0 ) {
					DOM_P.remove();
				}
			} );
		}
	};

	insertPlaceHolder_Gasp( form ) {
		if ( form.querySelector( 'p.shield_gasp_placeholder' ) === null ) {
			let the_p = document.createElement( 'p' );
			the_p.classList.add( 'shield_gasp_placeholder' );
			the_p.innerHTML = this._base_data.strings.loading + '&hellip;';

			let inserted = false;
			form.querySelectorAll( ':scope > *' ).forEach( ( maybeNode ) => {
				if ( !inserted ) {
					const submit = maybeNode.querySelector( '[type="submit"]' );
					if ( submit ) {
						inserted = true;
						form.insertBefore( the_p, maybeNode );
					}
				}
			} )

			if ( !inserted ) {
				form.appendChild( the_p );
			}
		}
	};

	processPlaceHolder_Gasp( shiep ) {
		let shieThe_lab = document.createElement( "label" );
		let shieThe_txt = document.createTextNode( ' ' + this._base_data.strings.label );
		let shieThe_cb = document.createElement( "input" );

		shiep.style.display = "inherit";

		let PH = $( shiep );
		if ( [ 'p', 'P' ].includes( PH.parent()[ 0 ].nodeName ) ) {
			/** prevent nested paragraphs */
			$( shiep ).insertBefore( PH.parent() )
		}

		let parentForm = PH.closest( 'form' );
		if ( parentForm.length > 0 ) {
			parentForm[ 0 ].onsubmit = () => {
				if ( !shieThe_cb.checked ) {
					alert( this._base_data.strings.alert );
					shiep.style.display = "inherit";
				}
				return shieThe_cb.checked;
			};

			let shishoney = document.createElement( "input" );
			shishoney.type = "hidden";
			shishoney.name = "icwp_wpsf_login_email";
			parentForm[ 0 ].appendChild( shishoney );
		}

		shiep.innerHTML = '';

		shieThe_cb.type = "checkbox";
		shieThe_cb.name = this._base_data.cbname;
		shieThe_cb.id = '_' + shieThe_cb.name;

		shiep.appendChild( shieThe_lab );
		shieThe_lab.appendChild( shieThe_cb );
		shieThe_lab.appendChild( shieThe_txt );
	};

}