export class Login2faOtpSegments {

	constructor( targetInput, options = {} ) {
		this.targetInput = targetInput;
		this.group = options.group || null;
		this.fallbackWrap = options.fallbackWrap || null;
		this.enhancedElements = options.enhancedElements || [];
		this.normalize = options.normalize || ( ( value ) => value );
		this.inputs = this.group ? Array.from( this.group.querySelectorAll( '[data-otp]' ) ) : [];

		if ( this.canRun() ) {
			this.run();
		}
	}

	canRun() {
		return this.targetInput instanceof HTMLInputElement && this.group instanceof HTMLElement && this.inputs.length > 0;
	}

	run() {
		this.group.hidden = false;
		this.enhancedElements.forEach( ( element ) => {
			element.hidden = false;
		} );

		if ( this.fallbackWrap instanceof HTMLElement ) {
			this.fallbackWrap.hidden = true;
			this.fallbackWrap.setAttribute( 'aria-hidden', 'true' );
		}

		this.targetInput.setAttribute( 'tabindex', '-1' );
		this.targetInput.setAttribute( 'aria-hidden', 'true' );

		this.fillFromTarget();

		this.inputs.forEach( ( input, index ) => {
			input.addEventListener( 'input', () => this.handleInput( input, index ) );
			input.addEventListener( 'keydown', ( event ) => this.handleKeydown( event, input, index ) );
			input.addEventListener( 'focus', () => input.select() );
			input.addEventListener( 'paste', ( event ) => this.handlePaste( event, index ) );
		} );
	}

	handleInput( input, index ) {
		const chars = this.getChars( input.value, 1 );
		input.value = chars[ chars.length - 1 ] || '';
		this.syncFilledState( input );
		this.writeTarget();

		if ( input.value !== '' && this.inputs[ index + 1 ] instanceof HTMLInputElement ) {
			this.focusInput( this.inputs[ index + 1 ] );
		}
	}

	handleKeydown( event, input, index ) {
		if ( event.key === 'Backspace' && input.value === '' && this.inputs[ index - 1 ] instanceof HTMLInputElement ) {
			event.preventDefault();
			this.focusInput( this.inputs[ index - 1 ] );
		}
		else if ( event.key === 'ArrowLeft' && this.inputs[ index - 1 ] instanceof HTMLInputElement ) {
			event.preventDefault();
			this.focusInput( this.inputs[ index - 1 ] );
		}
		else if ( event.key === 'ArrowRight' && this.inputs[ index + 1 ] instanceof HTMLInputElement ) {
			event.preventDefault();
			this.focusInput( this.inputs[ index + 1 ] );
		}
	}

	handlePaste( event, startIndex ) {
		event.preventDefault();

		const clipboardData = event.clipboardData || window.clipboardData;
		const chars = this.getChars(
			clipboardData ? clipboardData.getData( 'text' ) : '',
			this.inputs.length - startIndex
		);

		chars.forEach( ( char, offset ) => {
			const input = this.inputs[ startIndex + offset ];
			if ( input instanceof HTMLInputElement ) {
				input.value = char;
				this.syncFilledState( input );
			}
		} );

		this.writeTarget();

		const focusIndex = Math.min( startIndex + chars.length, this.inputs.length - 1 );
		if ( this.inputs[ focusIndex ] instanceof HTMLInputElement ) {
			this.focusInput( this.inputs[ focusIndex ] );
		}
	}

	fillFromTarget() {
		this.inputs.forEach( ( input, index ) => {
			input.value = this.getChars( this.targetInput.value )[ index ] || '';
			this.syncFilledState( input );
		} );
	}

	writeTarget() {
		this.targetInput.value = this.inputs.map( ( input ) => input.value ).join( '' );
	}

	getChars( value, limit = this.inputs.length ) {
		return this.normalize( String( value || '' ) )
			.slice( 0, limit )
			.split( '' );
	}

	syncFilledState( input ) {
		input.classList.toggle( 'filled', input.value !== '' );
	}

	focusInput( input ) {
		input.focus();
		input.select();
	}
}
