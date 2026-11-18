import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

/**
 * The setup guide bar along the bottom of Shield admin pages. Step forms are the welcome wizard's
 * own forms and save through the same wizard action. "Don't show again" is stored for the site.
 * When settings are saved elsewhere on the page, the guide is fetched again so its steps reflect
 * them.
 */
export class SetupGuideBar extends BaseAutoExecComponent {

	canRun() {
		this.root = document.getElementById( 'ShieldSetupGuide' );
		return this.root instanceof HTMLElement;
	}

	run() {
		this.sidebarItem = document.querySelector( '.sidebar-setup-item[data-setup-guide-open="1"]' );
		this.isBusy = false;
		this.isRefreshWanted = false;
		this.syncScrollPadding();
		this.fillShellToViewport();
		window.addEventListener( 'resize', () => this.fillShellToViewport() );

		// Any link to the guide (the sidebar's, Configure's "Guided Setup") opens it here rather than leaving
		// the page. Once the guide has been closed or dismissed, those links go where they point again.
		document.addEventListener( 'click', ( evt ) => {
			const opener = evt.target instanceof Element ? evt.target.closest( '[data-setup-guide-open="1"]' ) : null;
			if ( opener !== null && this.root.isConnected && !evt.metaKey && !evt.ctrlKey && !evt.shiftKey ) {
				evt.preventDefault();
				this.openNow();
			}
		} );

		// Asked for by link: open it now, and tidy the request off the address.
		if ( this.root.dataset.openNow === '1' ) {
			this.openNow();
			const url = new URL( window.location.href );
			url.searchParams.delete( 'setup_guide' );
			url.searchParams.delete( 'setup_step' );
			window.history.replaceState( window.history.state, '', url.toString() );
		}

		this.root.addEventListener( 'click', ( evt ) => {
			const target = evt.target instanceof Element ? evt.target : null;
			if ( target === null ) {
				return;
			}
			// While a step is saving or the guide is refreshing, the step area is covered and nothing in it responds.
			if ( this.isBusy && target.closest( '.shield-setup-guide__sheet' ) ) {
				evt.preventDefault();
				return;
			}
			// The collapsed bar opens the guide from anywhere on it, apart from its own links.
			const onBar = target.closest( '[data-setup-guide-bar="1"]' ) !== null
				&& target.closest( 'button:not([data-setup-guide-toggle]), a' ) === null;
			const toggle = target.closest( '[data-setup-guide-toggle="1"]' )
				|| ( onBar ? this.root.querySelector( '[data-setup-guide-toggle="1"]' ) : null );
			const step = target.closest( '[data-setup-guide-step]' );
			// A step is skipped with the guide's own link under it, or with one the step's own wording offers.
			const skip = target.closest( '[data-setup-guide-skip], .skip-step' );
			if ( toggle ) {
				this.setExpanded( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
			}
			else if ( step ) {
				this.showStep( step.dataset.setupGuideStep || '' );
			}
			else if ( skip ) {
				const slug = skip.closest( '[data-setup-guide-pane]' ).dataset.setupGuidePane;
				this.whileBusy( this.sendUpdate( { op: 'skip', step_slug: slug } ) )
				.then( ( resp ) => resp?.success && this.completeStep( slug ) );
			}
			else if ( target.closest( '[data-setup-guide-dismiss="1"]' ) ) {
				this.sendUpdate( { op: 'dismiss' } ).then( ( resp ) => resp?.success && this.removeGuide() );
			}
			else if ( target.closest( '[data-setup-guide-close="1"]' ) ) {
				// Setup is complete, so the guide won't be back on the next page either.
				this.removeGuide();
			}
		} );

		// Capture phase: the wizard's own submit handler only runs on the wizard page.
		this.root.addEventListener( 'submit', ( evt ) => {
			const form = evt.target;
			if ( form instanceof HTMLFormElement && form.matches( 'form.merlin-form' ) ) {
				evt.preventDefault();
				evt.stopPropagation();
				if ( !this.isBusy ) {
					this.submitStep( form );
				}
			}
		}, true );

		document.addEventListener( 'shield:options-saved', () => this.refresh() );
	}

	removeGuide() {
		this.root.remove();
		this.sidebarItem?.closest( '.sidebar-setup-footer' )?.remove();
		this.syncScrollPadding();
		this.fillShellToViewport();
	}

	/**
	 * Settings were saved elsewhere on the page, and any of them may be one a step looks at: fetch the guide
	 * again. The guide does one thing at a time, so the refresh waits for a step that's saving, and nothing in
	 * the guide can be sent while it's refreshing: a step is only ever saved from what the site's settings are.
	 * The guide stays as it is when the answer arrives (open or closed, on the same step) unless the save
	 * completed the step showing, and a step that's no different keeps anything typed into it.
	 */
	refresh() {
		if ( !this.root.isConnected ) {
			return;
		}
		if ( this.isBusy ) {
			this.isRefreshWanted = true;
			return;
		}

		this.whileBusy(
			( new AjaxService() )
			.bg( ObjectOps.Merge( JSON.parse( this.root.dataset.refreshAction ), {
				with_every_form: this.root.dataset.openNow === '1' ? '1' : '0',
			} ) )
			.then( ( resp ) => {
				const fresh = resp?.success
					? ( new DOMParser() ).parseFromString( resp.data?.html || '', 'text/html' ).getElementById( 'ShieldSetupGuide' )
					: null;
				if ( fresh === null || !this.root.isConnected ) {
					return;
				}
				const shown = this.root.querySelector( '[data-setup-guide-step][aria-current="step"]' );
				const shownSlug = shown?.dataset.setupGuideStep || '';
				const shownWasDone = shown?.classList.contains( 'is-done' ) === true;
				const wasExpanded = this.root.classList.contains( 'is-expanded' );

				fresh.querySelectorAll( '[data-setup-guide-pane-version]' ).forEach( ( freshPane ) => {
					const pane = this.root.querySelector( `[data-setup-guide-pane="${freshPane.dataset.setupGuidePane}"]` );
					if ( pane?.dataset.setupGuidePaneVersion === freshPane.dataset.setupGuidePaneVersion ) {
						freshPane.replaceWith( pane );
					}
				} );
				this.root.replaceChildren( ...fresh.childNodes );
				this.setExpanded( wasExpanded );
				// A step kept from before is shown or hidden as it was, so the step to show is always set again:
				// the one that was showing, or the one the answer opens on when that step has just been completed.
				const step = shownSlug.length > 0 ? this.root.querySelector( `[data-setup-guide-step="${shownSlug}"]` ) : null;
				const opensOn = this.root.querySelector( '[data-setup-guide-step][aria-current="step"]' );
				this.showStep(
					step !== null && ( shownWasDone || !step.classList.contains( 'is-done' ) )
						? shownSlug
						: ( opensOn?.dataset.setupGuideStep || 'all_done' )
				);
				this.syncProgress();
			} )
			.catch( () => null )
		);
	}

	openNow() {
		this.setExpanded( true );
		this.root.querySelector( '[data-setup-guide-toggle="1"]' )?.focus();
	}

	setExpanded( isExpanded ) {
		const toggle = this.root.querySelector( '[data-setup-guide-toggle="1"]' );
		if ( toggle instanceof HTMLElement ) {
			toggle.setAttribute( 'aria-expanded', String( isExpanded ) );
			const label = toggle.querySelector( '[data-setup-guide-toggle-label="1"]' );
			if ( label ) {
				label.textContent = isExpanded ? toggle.dataset.labelOpen : toggle.dataset.labelClosed;
			}
		}
		const sheet = this.root.querySelector( '.shield-setup-guide__sheet' );
		if ( sheet ) {
			sheet.hidden = !isExpanded;
		}
		this.root.classList.toggle( 'is-expanded', isExpanded );
		this.syncScrollPadding();
	}

	/**
	 * On short pages the Shield sidebar would stop part way down, leaving a gap above the fixed bar;
	 * the admin shell is stretched to at least the bottom of the window instead, for as long as the guide is there.
	 */
	fillShellToViewport() {
		const shell = document.getElementById( 'PageMain-Shield' );
		if ( shell instanceof HTMLElement ) {
			const top = shell.getBoundingClientRect().top + window.scrollY;
			shell.style.minHeight = this.root.isConnected ? `${Math.max( 0, window.innerHeight - top )}px` : '';
		}
	}

	/**
	 * Scrolling and keyboard focus keep content clear of the fixed bar rather than behind it.
	 */
	syncScrollPadding() {
		document.documentElement.style.scrollPaddingBottom = this.root.isConnected ? `${this.root.offsetHeight + 16}px` : '';
	}

	showStep( slug ) {
		this.root.querySelectorAll( '[data-setup-guide-step]' ).forEach(
			( step ) => step.setAttribute( 'aria-current', step.dataset.setupGuideStep === slug ? 'step' : 'false' )
		);
		this.root.querySelectorAll( '[data-setup-guide-pane]' ).forEach( ( pane ) => {
			pane.hidden = pane.dataset.setupGuidePane !== slug;
		} );
	}

	completeStep( slug ) {
		const step = this.root.querySelector( `[data-setup-guide-step="${slug}"]` );
		step?.classList.add( 'is-done' );
		const hint = step?.querySelector( '[data-setup-guide-step-hint="1"]' );
		if ( hint ) {
			hint.textContent = this.root.dataset.doneLabel;
		}

		const next = this.syncProgress();
		this.showStep( next ? next.dataset.setupGuideStep || '' : 'all_done' );
	}

	/**
	 * Brings every count, the progress bar and the "Next" label into line with the steps as they now are.
	 * @returns {Element|undefined} the next step to do, if any
	 */
	syncProgress() {
		const steps = [ ...this.root.querySelectorAll( '[data-setup-guide-step]' ) ];
		const doneCount = steps.filter( ( s ) => s.classList.contains( 'is-done' ) ).length;
		const total = steps.length;
		const label = this.root.dataset.progressTemplate
		.replace( '%1$s', String( doneCount ) )
		.replace( '%2$s', String( total ) );
		const count = this.root.querySelector( '[data-setup-guide-count="1"]' );
		if ( count ) {
			count.textContent = label;
		}
		const sidebarCount = this.sidebarItem?.querySelector( '[data-setup-guide-open-count="1"]' );
		if ( sidebarCount ) {
			sidebarCount.textContent = `${doneCount}/${total}`;
		}
		const fill = this.root.querySelector( '.shield-setup-guide__progress-fill' );
		if ( fill instanceof HTMLElement ) {
			fill.style.width = `${Math.round( 100*doneCount/total )}%`;
		}

		const next = steps.find( ( s ) => !s.classList.contains( 'is-done' ) );
		const nextLabel = this.root.querySelector( '[data-setup-guide-next="1"]' );
		if ( nextLabel ) {
			const nextName = next?.querySelector( '.shield-setup-guide__step-name' )?.textContent || '';
			nextLabel.textContent = nextName.length > 0
				? this.root.dataset.nextTemplate.replace( '%s', nextName )
				: '';
		}
		return next;
	}

	submitStep( form ) {
		const action = JSON.parse( this.root.dataset.merlinAction );
		const slug = String( form.querySelector( '[name="step_slug"]' )?.value || '' );
		this.whileBusy( ( new AjaxService() ).send( ObjectOps.Merge( action, { form_params: Forms.Serialize( form ) } ), false ) )
		.then( ( resp ) => {
			if ( resp?.success ) {
				if ( resp.data?.page_reload ) {
					window.location.reload();
					return;
				}
				this.completeStep( slug );
			}
			else {
				shieldServices.dialog().message( {
					title: shieldStrings.string( 'request_failed' ),
					message: resp?.data?.message || '',
					launcher: form,
					showTitle: true,
				} );
			}
		} )
		.catch( () => null );
	}

	/**
	 * Fades the step area under a spinner until the request settles, so a click visibly does
	 * something and can't be repeated while it's under way. A refresh asked for meanwhile follows.
	 * @param {Promise} request
	 * @returns {Promise}
	 */
	whileBusy( request ) {
		this.setBusy( true );
		return request.finally( () => {
			this.setBusy( false );
			if ( this.isRefreshWanted ) {
				this.isRefreshWanted = false;
				this.refresh();
			}
		} );
	}

	setBusy( isBusy ) {
		this.isBusy = isBusy;
		this.root.querySelector( '.shield-setup-guide__sheet' )?.classList.toggle( 'is-busy', isBusy );
		this.root.querySelector( '.shield-setup-guide__panes' )?.setAttribute( 'aria-busy', String( isBusy ) );
	}

	sendUpdate( data ) {
		return ( new AjaxService() )
		.bg( ObjectOps.Merge( JSON.parse( this.root.dataset.updateAction ), data ) )
		.catch( () => null );
	}
}
