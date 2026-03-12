import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import xml from 'highlight.js/lib/languages/xml';
import { AjaxService } from "../services/AjaxService";
import { BootstrapModals } from "../ui/BootstrapModals";
import { ObjectOps } from "../../util/ObjectOps";

hljs.registerLanguage( 'bash', bash );
hljs.registerLanguage( 'css', css );
hljs.registerLanguage( 'javascript', javascript );
hljs.registerLanguage( 'json', json );
hljs.registerLanguage( 'php', php );
hljs.registerLanguage( 'sql', sql );
hljs.registerLanguage( 'xml', xml );

let activeRequestToken = '';

export class ScanItemAnalysisModal {

	static show( renderAction, rid ) {
		if ( typeof rid !== 'string' || rid.length < 1 || !renderAction || typeof renderAction !== 'object' ) {
			return Promise.resolve();
		}

		const modal = document.getElementById( 'ShieldModalContainer' );
		const modalContent = modal?.querySelector( '.modal-content' );
		if ( modal === null || modalContent === null ) {
			return Promise.resolve();
		}

		const requestToken = ScanItemAnalysisModal.buildRequestToken();
		activeRequestToken = requestToken;

		modalContent.innerHTML = ScanItemAnalysisModal.buildLoadingMarkup();
		BootstrapModals.Show( modal );

		const reqData = ObjectOps.ObjClone( renderAction );
		reqData.rid = rid;

		return ( new AjaxService() )
		.send( reqData, false, true )
		.then( ( resp ) => {
			if ( activeRequestToken !== requestToken ) {
				return;
			}

			if ( resp?.success && typeof resp?.data?.html === 'string' ) {
				modalContent.innerHTML = resp.data.html;
				BootstrapModals.normalizeModalAccessibility( modal );
				ScanItemAnalysisModal.highlightModalCodeBlocks( modal );
				activeRequestToken = '';
				return;
			}

			activeRequestToken = '';
			BootstrapModals.Hide( modal );
			alert( ScanItemAnalysisModal.extractErrorMessage( resp ) );
		} )
		.catch( ( error ) => {
			if ( activeRequestToken !== requestToken ) {
				return;
			}

			activeRequestToken = '';
			BootstrapModals.Hide( modal );
			console.log( error );
			alert( 'Communications error with site.' );
		} );
	}

	static buildRequestToken() {
		return `${Date.now()}-${Math.random()}`;
	}

	static buildLoadingMarkup() {
		const loadingLabel = typeof shieldStrings?.string === 'function'
			? shieldStrings.string( 'loading' ) || 'Loading'
			: 'Loading';
		const spinner = ScanItemAnalysisModal.buildSpinnerMarkup();

		return `<div class="modal-header">
			<h5 class="modal-title">${ScanItemAnalysisModal.escapeHtml( loadingLabel )}</h5>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		</div>
		<div class="modal-body" aria-busy="true">
			${spinner}
		</div>`;
	}

	static buildSpinnerMarkup() {
		const spinner = document.getElementById( 'ShieldWaitSpinner' );
		if ( spinner instanceof HTMLElement ) {
			const clone = spinner.cloneNode( true );
			clone.id = '';
			clone.classList.remove( 'd-none' );
			return clone.outerHTML;
		}

		return '<div class="d-flex justify-content-center align-items-center"><div class="spinner-border text-success m-5" role="status"><span class="visually-hidden">Loading...</span></div></div>';
	}

	static extractErrorMessage( resp ) {
		const message = resp?.data?.message;
		return typeof message === 'string' && message.length > 0
			? message
			: 'Communications error with site.';
	}

	static highlightModalCodeBlocks( modal ) {
		const unknownLanguageBlocks = [];
		modal.querySelectorAll( 'pre.icwp-code-render code' ).forEach( ( el ) => {
			const languageClass = Array.from( el.classList ).find( ( cls ) => cls.startsWith( 'language-' ) ) || '';
			const language = languageClass ? languageClass.slice( 9 ) : '';
			if ( language.length > 0 && hljs.getLanguage( language ) ) {
				hljs.highlightElement( el );
			}
			else {
				unknownLanguageBlocks.push( el );
			}
		} );

		if ( unknownLanguageBlocks.length < 1 ) {
			return;
		}

		const detectedLanguage = hljs.highlightAuto(
			unknownLanguageBlocks.map( ( el ) => el.textContent || '' ).join( "\n" )
		).language || '';

		unknownLanguageBlocks.forEach( ( el ) => {
			if ( detectedLanguage.length > 0 && hljs.getLanguage( detectedLanguage ) ) {
				const highlighted = hljs.highlight( el.textContent || '', {
					language: detectedLanguage,
					ignoreIllegals: true,
				} );
				el.innerHTML = highlighted.value;
				el.classList.add( 'hljs', 'language-' + detectedLanguage );
			}
			else {
				const highlighted = hljs.highlightAuto( el.textContent || '' );
				el.innerHTML = highlighted.value;
				el.classList.add( 'hljs' );
				if ( highlighted.language ) {
					el.classList.add( 'language-' + highlighted.language );
				}
			}
		} );
	}

	static escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}
}
