import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

/**
 * The setup guide bar along the bottom of Shield admin pages. Step forms are the welcome wizard's
 * own forms and save through the same wizard action. "Hide until next visit" lasts for this
 * browser session; "Don't show again" is stored for the user.
 */
export class SetupGuideBar extends BaseAutoExecComponent {

	static HIDDEN_KEY = 'shield-setup-guide-hidden';

	canRun() {
		this.root = document.getElementById( 'ShieldSetupGuide' );
		return this.root instanceof HTMLElement;
	}

	run() {
		this.reopen = document.querySelector( '[data-setup-guide-reopen="1"]' );
		this.sidebarItem = document.querySelector( '[data-setup-guide-open="1"]' );
		this.setHidden( this.readSessionHidden() );
		this.fillShellToViewport();
		window.addEventListener( 'resize', () => this.fillShellToViewport() );

		// The sidebar entry brings the guide back, open, whether it was hidden or only collapsed.
		this.sidebarItem?.addEventListener( 'click', () => {
			this.writeSessionHidden( false );
			this.setHidden( false );
			this.setExpanded( true );
			this.root.querySelector( '[data-setup-guide-toggle="1"]' )?.focus();
		} );

		this.root.addEventListener( 'click', ( evt ) => {
			const target = evt.target instanceof Element ? evt.target : null;
			if ( target === null ) {
				return;
			}
			// The collapsed bar opens the guide from anywhere on it, apart from its own links.
			const onBar = target.closest( '[data-setup-guide-bar="1"]' ) !== null
				&& target.closest( 'button:not([data-setup-guide-toggle]), a' ) === null;
			const toggle = target.closest( '[data-setup-guide-toggle="1"]' )
				|| ( onBar ? this.root.querySelector( '[data-setup-guide-toggle="1"]' ) : null );
			const step = target.closest( '[data-setup-guide-step]' );
			const skip = target.closest( '[data-setup-guide-skip]' );
			const complete = target.closest( '[data-setup-guide-complete]' );
			if ( toggle ) {
				this.setExpanded( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
			}
			else if ( step ) {
				this.showStep( step.dataset.setupGuideStep || '' );
			}
			else if ( skip || complete ) {
				const slug = ( skip || complete ).dataset.setupGuideSkip || ( skip || complete ).dataset.setupGuideComplete || '';
				this.sendUpdate( { op: 'skip', step_slug: slug } ).then( () => this.completeStep( slug ) );
			}
			else if ( target.closest( '[data-setup-guide-hide="1"]' ) ) {
				this.writeSessionHidden( true );
				this.setHidden( true );
			}
			else if ( target.closest( '[data-setup-guide-dismiss="1"]' ) ) {
				this.sendUpdate( { op: 'dismiss' } ).then( () => {
					this.root.remove();
					this.reopen?.remove();
					this.sidebarItem?.closest( '.sidebar-setup-footer' )?.remove();
					document.body.classList.remove( 'has-shield-setup-guide' );
				} );
			}
			else {
				// The protection level step picks a profile by clicking its card.
				const card = target.closest( '.profile-card:not(.profile-card-current)' );
				if ( card ) {
					this.selectProfile( card );
				}
			}
		} );

		// Capture phase: the wizard's own submit handler only runs on the wizard page.
		this.root.addEventListener( 'submit', ( evt ) => {
			const form = evt.target;
			if ( form instanceof HTMLFormElement && form.matches( 'form.merlin-form' ) ) {
				evt.preventDefault();
				evt.stopPropagation();
				this.submitStep( form );
			}
		}, true );

		this.reopen?.addEventListener( 'click', () => {
			this.writeSessionHidden( false );
			this.setHidden( false );
			this.setExpanded( true );
		} );
	}

	setExpanded( isExpanded ) {
		const toggle = this.root.querySelector( '[data-setup-guide-toggle="1"]' );
		if ( toggle instanceof HTMLElement ) {
			toggle.setAttribute( 'aria-expanded', String( isExpanded ) );
			const label = toggle.querySelector( '[data-setup-guide-toggle-label="1"]' );
			if ( label ) {
				label.textContent = ( isExpanded ? toggle.dataset.labelOpen : toggle.dataset.labelClosed ) || label.textContent;
			}
		}
		const sheet = this.root.querySelector( '.shield-setup-guide__sheet' );
		if ( sheet ) {
			sheet.hidden = !isExpanded;
		}
		this.root.classList.toggle( 'is-expanded', isExpanded );
		this.syncScrollPadding();
	}

	setHidden( isHidden ) {
		this.root.hidden = isHidden;
		if ( this.reopen ) {
			this.reopen.hidden = !isHidden;
		}
		document.body.classList.toggle( 'has-shield-setup-guide', !isHidden );
		this.syncScrollPadding();
		this.fillShellToViewport();
	}

	/**
	 * On short pages the Shield sidebar would stop part way down, leaving a gap above the fixed bar;
	 * the admin shell is stretched to at least the bottom of the window instead.
	 */
	fillShellToViewport() {
		const shell = document.getElementById( 'PageMain-Shield' );
		if ( shell instanceof HTMLElement ) {
			const top = shell.getBoundingClientRect().top + window.scrollY;
			shell.style.minHeight = this.root.hidden ? '' : `${Math.max( 0, window.innerHeight - top )}px`;
		}
	}

	/**
	 * Scrolling and keyboard focus keep content clear of the fixed bar rather than behind it.
	 */
	syncScrollPadding() {
		document.documentElement.style.scrollPaddingBottom = this.root.hidden ? '' : `${this.root.offsetHeight + 16}px`;
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
			hint.textContent = this.root.dataset.doneLabel || hint.textContent;
		}

		const steps = [ ...this.root.querySelectorAll( '[data-setup-guide-step]' ) ];
		const doneCount = steps.filter( ( s ) => s.classList.contains( 'is-done' ) ).length;
		const total = steps.length;
		const label = String( this.root.dataset.progressTemplate || '%1$s / %2$s' )
		.replace( '%1$s', String( doneCount ) )
		.replace( '%2$s', String( total ) );
		const count = this.root.querySelector( '[data-setup-guide-count="1"]' );
		if ( count ) {
			count.textContent = label;
		}
		[ this.reopen?.querySelector( '[data-setup-guide-reopen-count="1"]' ), this.sidebarItem?.querySelector( '[data-setup-guide-open-count="1"]' ) ]
		.forEach( ( countEl ) => {
			if ( countEl ) {
				countEl.textContent = `${doneCount}/${total}`;
			}
		} );
		const fill = this.root.querySelector( '.shield-setup-guide__progress-fill' );
		if ( fill instanceof HTMLElement ) {
			fill.style.width = `${Math.round( 100*doneCount/total )}%`;
		}

		const next = steps.find( ( s ) => !s.classList.contains( 'is-done' ) );
		const nextLabel = this.root.querySelector( '[data-setup-guide-next="1"]' );
		if ( nextLabel ) {
			const nextName = next?.querySelector( '.shield-setup-guide__step-name' )?.textContent || '';
			nextLabel.textContent = nextName.length > 0
				? String( this.root.dataset.nextTemplate || '%s' ).replace( '%s', nextName )
				: '';
		}
		this.showStep( next ? next.dataset.setupGuideStep || '' : 'all_done' );
	}

	selectProfile( card ) {
		const form = card.closest( 'form' );
		const wasSelected = card.classList.contains( 'selected' );
		form?.querySelectorAll( '.profile-card' ).forEach( ( c ) => c.classList.remove( 'selected' ) );
		if ( !wasSelected ) {
			card.classList.add( 'selected' );
		}
		const input = form?.querySelector( '[name="security_profile"]' );
		if ( input instanceof HTMLInputElement ) {
			input.value = wasSelected ? '' : ( card.dataset.level || '' );
		}
	}

	submitStep( form ) {
		const action = this.parseAction( this.root.dataset.merlinAction );
		if ( action === null ) {
			return;
		}
		const slug = String( form.querySelector( '[name="step_slug"]' )?.value || '' );
		( new AjaxService() )
		.send( ObjectOps.Merge( action, { form_params: Forms.Serialize( form ) } ) )
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

	sendUpdate( data ) {
		const action = this.parseAction( this.root.dataset.updateAction );
		return action === null
			? Promise.resolve( null )
			: ( new AjaxService() ).bg( ObjectOps.Merge( action, data ) ).catch( () => null );
	}

	parseAction( json ) {
		try {
			const action = JSON.parse( json || '{}' );
			return ObjectOps.IsEmpty( action ) ? null : action;
		}
		catch {
			return null;
		}
	}

	readSessionHidden() {
		try {
			return window.sessionStorage.getItem( SetupGuideBar.HIDDEN_KEY ) === '1';
		}
		catch {
			return false;
		}
	}

	writeSessionHidden( isHidden ) {
		try {
			if ( isHidden ) {
				window.sessionStorage.setItem( SetupGuideBar.HIDDEN_KEY, '1' );
			}
			else {
				window.sessionStorage.removeItem( SetupGuideBar.HIDDEN_KEY );
			}
		}
		catch {
			return;
		}
	}
}
