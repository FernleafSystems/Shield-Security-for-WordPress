import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

/**
 * The setup guide on Shield admin pages: a bar along the bottom that opens into a panel over the page.
 * Step forms are the welcome wizard's own forms and save through the same wizard action. Skipping a
 * step, minimising the guide and closing it for good are stored for the site. When settings are saved, in a step or
 * elsewhere on the page, the guide is fetched again so its steps reflect them.
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
		// Whether a step was saved on this page, as a page drawn from those settings is then out of date.
		this.hasSavedStep = false;

		// Dims the page behind the opened guide, and minimises the guide when clicked.
		this.backdrop = document.createElement( 'div' );
		this.backdrop.className = 'shield-setup-guide-backdrop';
		this.backdrop.hidden = true;
		this.backdrop.addEventListener( 'click', () => this.minimise() );
		this.root.before( this.backdrop );

		this.placeSkipLinks();
		this.syncScrollPadding();
		this.fillShellToViewport();
		window.addEventListener( 'resize', () => this.fillShellToViewport() );

		// Any link to the guide (the sidebar's, Configure's "Setup guide") opens it here rather than leaving
		// the page. Once the guide has been closed, those links go where they point again.
		document.addEventListener( 'click', ( evt ) => {
			const opener = evt.target instanceof Element ? evt.target.closest( '[data-setup-guide-open="1"]' ) : null;
			if ( opener !== null && this.root.isConnected && !evt.metaKey && !evt.ctrlKey && !evt.shiftKey ) {
				evt.preventDefault();
				this.openNow();
			}
		} );

		// Escape minimises the opened guide, unless it's closing the guide's own menu or a dialog over the guide.
		document.addEventListener( 'keydown', ( evt ) => {
			if ( evt.key === 'Escape' && this.isExpanded() && !this.isBusy
				&& document.querySelector( '.shield-setup-guide .dropdown-menu.show, dialog[open], .modal.show' ) === null ) {
				this.minimise();
			}
		} );

		if ( this.root.dataset.openNow === '1' ) {
			// Asked for by link: open it now, and tidy the request off the address.
			this.openNow();
			const url = new URL( window.location.href );
			url.searchParams.delete( 'setup_guide' );
			url.searchParams.delete( 'setup_step' );
			window.history.replaceState( window.history.state, '', url.toString() );
		}
		else if ( this.root.dataset.autoOpen === '1' ) {
			// A new install that hasn't minimised the guide yet.
			this.setExpanded( true );
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
			// The minimised bar opens the guide from anywhere on it.
			const opensFromBar = !this.isExpanded() && target.closest( '[data-setup-guide-bar="1"]' ) !== null;
			const step = target.closest( '[data-setup-guide-step], [data-setup-guide-goto]' );
			const skip = target.closest( '[data-setup-guide-skip]' );
			if ( opensFromBar ) {
				this.openNow();
			}
			else if ( target.closest( '[data-setup-guide-minimise="1"]' ) ) {
				this.minimise();
			}
			else if ( target.closest( '[data-setup-guide-dismiss="1"]' ) ) {
				this.sendUpdate( { op: 'dismiss' } ).then( ( resp ) => resp?.success && this.leave() );
			}
			else if ( step ) {
				this.showStep( step.dataset.setupGuideStep || step.dataset.setupGuideGoto || '' );
			}
			else if ( skip ) {
				const slug = skip.closest( '[data-setup-guide-pane]' ).dataset.setupGuidePane;
				this.whileBusy( this.sendUpdate( { op: 'skip', step_slug: slug } ) )
				.then( ( resp ) => resp?.success && this.settleStep( slug, false ) );
			}
			else if ( target.closest( '[data-setup-guide-onward="1"]' ) ) {
				// From a step that's already set up: on to the next step to do, or to the summary.
				this.showNextStep();
			}
			else if ( target.closest( '[data-setup-guide-close="1"]' ) ) {
				// Every step has been decided, so the guide won't be back on the next page either.
				this.leave();
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

	isExpanded() {
		return this.root.classList.contains( 'is-expanded' );
	}

	/**
	 * Back to the bar. The first time is stored for the site, as from then on the guide no longer opens by itself.
	 */
	minimise() {
		const hadFocus = this.root.contains( document.activeElement );
		this.setExpanded( false );
		if ( hadFocus ) {
			// What had focus is hidden with the panel, so focus goes to the bar's way back in.
			this.root.querySelector( '[data-setup-guide-toggle="1"]' )?.focus();
		}
		const stored = this.root.dataset.minimised === '1'
			? Promise.resolve()
			: this.sendUpdate( { op: 'minimise' } ).then( ( resp ) => {
				if ( resp?.success ) {
					this.root.dataset.minimised = '1';
				}
			} );
		stored.then( () => this.reloadIfOutOfDate() );
	}

	/**
	 * The guide is finished with on this page, whether closed for good or with every step decided.
	 */
	leave() {
		this.root.remove();
		this.backdrop.remove();
		// The sidebar link goes with it, with the divider above it.
		this.sidebarItem?.closest( '.sidebar-guidance' )?.remove();
		this.syncScrollPadding();
		this.fillShellToViewport();
		this.reloadIfOutOfDate();
	}

	reloadIfOutOfDate() {
		if ( this.hasSavedStep && this.root.dataset.reloadOnClose === '1' ) {
			window.location.reload();
		}
	}

	/**
	 * Settings were saved, in a step or elsewhere on the page, and any of them may be one a step looks at: fetch the guide
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
				const wasExpanded = this.isExpanded();

				fresh.querySelectorAll( '[data-setup-guide-pane-version]' ).forEach( ( freshPane ) => {
					const pane = this.root.querySelector( `[data-setup-guide-pane="${freshPane.dataset.setupGuidePane}"]` );
					if ( pane?.dataset.setupGuidePaneVersion === freshPane.dataset.setupGuidePaneVersion ) {
						freshPane.replaceWith( pane );
					}
				} );
				this.root.replaceChildren( ...fresh.childNodes );
				this.placeSkipLinks();
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
		( this.root.querySelector( '[data-setup-guide-step][aria-current="step"]' )
			|| this.root.querySelector( '[data-setup-guide-close="1"]' ) )?.focus();
	}

	setExpanded( isExpanded ) {
		this.root.querySelector( '[data-setup-guide-toggle="1"]' )?.setAttribute( 'aria-expanded', String( isExpanded ) );
		const sheet = this.root.querySelector( '.shield-setup-guide__sheet' );
		if ( sheet ) {
			sheet.hidden = !isExpanded;
		}
		this.root.classList.toggle( 'is-expanded', isExpanded );
		this.backdrop.hidden = !isExpanded;
		this.syncScrollPadding();
		if ( isExpanded && this.root.querySelector( '[data-setup-guide-pane="all_done"]:not([hidden])' ) ) {
			this.fillSummary();
		}
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
	 * Scrolling and keyboard focus keep content clear of the minimised bar rather than behind it.
	 */
	syncScrollPadding() {
		const bar = this.root.querySelector( '[data-setup-guide-bar="1"]' );
		document.documentElement.style.scrollPaddingBottom = this.root.isConnected && bar instanceof HTMLElement
			? `${bar.offsetHeight + 16}px` : '';
	}

	/**
	 * Each step's "skip" link is moved beside the step's main button, so that skipping is offered right
	 * where saving is, rather than under the step where it can be missed.
	 */
	placeSkipLinks() {
		this.root.querySelectorAll( '.shield-setup-guide__skip > [data-setup-guide-skip]' ).forEach( ( link ) => {
			const holder = link.parentElement;
			const submit = link.closest( '[data-setup-guide-pane]' )
			?.querySelector( '.shield-setup-guide__step-body form.merlin-form:not([hidden]) [type="submit"]' );
			if ( submit instanceof HTMLElement ) {
				const row = submit.parentElement;
				row.classList.add( 'shield-setup-guide__actions' );
				[ ...row.querySelectorAll( '[type="submit"]' ) ].pop().after( link );
				holder.remove();
			}
		} );
	}

	showStep( slug ) {
		this.root.querySelectorAll( '[data-setup-guide-step]' ).forEach(
			( step ) => step.setAttribute( 'aria-current', step.dataset.setupGuideStep === slug ? 'step' : 'false' )
		);
		this.root.querySelector( '[data-setup-guide-summary-nav="1"]' )
		?.setAttribute( 'aria-current', slug === 'all_done' ? 'step' : 'false' );
		this.root.querySelectorAll( '[data-setup-guide-pane]' ).forEach( ( pane ) => {
			pane.hidden = pane.dataset.setupGuidePane !== slug;
		} );
		if ( slug === 'all_done' ) {
			this.fillSummary();
		}
	}

	/**
	 * A step was saved or skipped: mark it so, and move on to the next step to do, or to the summary.
	 * A step that was saved stays done when it's skipped afterwards.
	 */
	settleStep( slug, wasSaved ) {
		const step = this.root.querySelector( `[data-setup-guide-step="${slug}"]` );
		if ( step && ( wasSaved || !step.classList.contains( 'is-done' ) ) ) {
			step.classList.toggle( 'is-done', wasSaved );
			step.classList.toggle( 'is-skipped', !wasSaved );
			const hint = step.querySelector( '[data-setup-guide-step-hint="1"]' );
			if ( hint ) {
				hint.textContent = wasSaved ? this.root.dataset.doneLabel : this.root.dataset.skippedLabel;
			}
		}

		this.showNextStep();
	}

	showNextStep() {
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
		const skippedCount = steps.filter( ( s ) => s.classList.contains( 'is-skipped' ) ).length;
		const total = steps.length;
		const count = this.root.querySelector( '[data-setup-guide-count="1"]' );
		if ( count ) {
			count.textContent = this.root.dataset.progressTemplate
			.replace( '%1$s', String( doneCount ) )
			.replace( '%2$s', String( total ) )
				+ ( skippedCount > 0 ? `, ${this.root.dataset.skippedTemplate.replace( '%s', String( skippedCount ) )}` : '' );
		}
		const sidebarCount = this.sidebarItem?.querySelector( '[data-setup-guide-open-count="1"]' );
		if ( sidebarCount ) {
			sidebarCount.textContent = `${doneCount + skippedCount}/${total}`;
		}
		const fill = this.root.querySelector( '.shield-setup-guide__progress-fill' );
		if ( fill instanceof HTMLElement ) {
			fill.style.width = `${Math.round( 100*( doneCount + skippedCount )/total )}%`;
		}

		const next = steps.find( ( s ) => !s.classList.contains( 'is-done' ) && !s.classList.contains( 'is-skipped' ) );
		// The summary is there to open once no step is left to do.
		this.root.querySelector( '[data-setup-guide-summary-nav="1"]' )?.toggleAttribute( 'disabled', next !== undefined );
		const nextLabel = this.root.querySelector( '[data-setup-guide-next="1"]' );
		if ( nextLabel ) {
			const nextName = next?.querySelector( '.shield-setup-guide__step-name' )?.textContent || '';
			nextLabel.textContent = nextName.length > 0
				? this.root.dataset.nextTemplate.replace( '%s', nextName )
				: '';
		}
		return next;
	}

	/**
	 * The summary is one table of the steps as they stand, in the guide's order: each step's name behind the
	 * same mark it has in the step list, how it was left (as the step list says it), and a way back to it.
	 */
	fillSummary() {
		const body = this.root.querySelector( '[data-setup-guide-summary="1"]' );
		if ( !body ) {
			return;
		}
		body.replaceChildren( ...[ ...this.root.querySelectorAll( '[data-setup-guide-step]' ) ].map( ( step ) => {
			const name = document.createElement( 'span' );
			name.textContent = step.querySelector( '.shield-setup-guide__step-name' )?.textContent || '';
			const step_ = document.createElement( 'div' );
			step_.className = 'shield-setup-guide__summary-step';
			const mark = step.querySelector( '.shield-setup-guide__check' );
			step_.append( ...( mark ? [ mark.cloneNode( true ) ] : [] ), name );
			const stepCell = document.createElement( 'td' );
			stepCell.append( step_ );

			const status = document.createElement( 'span' );
			status.className = 'shield-setup-guide__summary-status';
			status.textContent = step.querySelector( '[data-setup-guide-step-hint="1"]' )?.textContent || '';
			const statusCell = document.createElement( 'td' );
			statusCell.append( status );

			const back = document.createElement( 'button' );
			back.type = 'button';
			back.className = 'shield-setup-guide__link';
			back.dataset.setupGuideGoto = step.dataset.setupGuideStep;
			back.textContent = this.root.dataset.goBackLabel;
			const backCell = document.createElement( 'td' );
			backCell.className = 'shield-setup-guide__summary-back';
			backCell.append( back );

			// The row is in the step's state, so its mark and status are drawn as the step list draws them.
			const row = document.createElement( 'tr' );
			[ 'is-done', 'is-already', 'is-skipped' ].forEach(
				( state ) => row.classList.toggle( state, step.classList.contains( state ) )
			);
			row.append( stepCell, statusCell, backCell );
			return row;
		} ) );
	}

	submitStep( form ) {
		const action = JSON.parse( this.root.dataset.merlinAction );
		const slug = String( form.querySelector( '[name="step_slug"]' )?.value || '' );
		this.whileBusy( ( new AjaxService() ).send( ObjectOps.Merge( action, { form_params: Forms.Serialize( form ) } ), false ) )
		.then( ( resp ) => {
			if ( resp?.success ) {
				if ( resp.data?.page_reload ) {
					// The page is loaded again with the guide open, on whichever steps there now are.
					const url = new URL( window.location.href );
					url.searchParams.set( 'setup_guide', 'open' );
					window.location.assign( url.toString() );
					return;
				}
				this.hasSavedStep = true;
				this.settleStep( slug, true );
				// What was saved may be what another step shows, such as each protection level compared with the
				// site's settings, so every step is drawn again from the settings as they now are.
				this.refresh();
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
