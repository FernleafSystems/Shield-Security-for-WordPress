<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SectionRenderFallback {

	use PluginControllerConsumer;

	public const EMPTY_OUTPUT_DIAGNOSTIC = 'No render output was returned.';

	public function render( string $renderAction, array $actionData, string $sectionName ) :string {
		try {
			$payload = $this->renderPayload( $renderAction, $actionData );
			$output = (string)( $payload[ 'render_output' ] ?? $payload[ 'html' ] ?? '' );

			if ( !empty( $payload[ 'render_error' ] ) ) {
				return $this->renderFallback(
					$sectionName,
					$this->diagnosticFromRenderPayload( $payload, $output )
				);
			}

			if ( \trim( $output ) === '' ) {
				return $this->renderFallback( $sectionName, self::EMPTY_OUTPUT_DIAGNOSTIC );
			}

			return $output;
		}
		catch ( \Throwable $e ) {
			return $this->renderFallback( $sectionName, $e->getMessage() );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function renderPayload( string $renderAction, array $actionData ) :array {
		return self::con()->action_router->action(
			Render::class,
			[
				'render_action_slug' => $renderAction,
				'render_action_data' => $actionData,
			]
		)->payload();
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function diagnosticFromRenderPayload( array $payload, string $output ) :string {
		if ( $output !== '' ) {
			return $output;
		}

		foreach ( [ 'error', 'message', 'render_error_code' ] as $key ) {
			if ( \is_string( $payload[ $key ] ?? null ) && \trim( $payload[ $key ] ) !== '' ) {
				return (string)$payload[ $key ];
			}
		}

		return self::EMPTY_OUTPUT_DIAGNOSTIC;
	}

	public function renderFallback( string $sectionName, string $diagnostic = '' ) :string {
		$section = $this->escape( $sectionName );
		$diagnostic = $this->sanitizeDiagnostic( $diagnostic );

		return \sprintf(
			'<div class="alert alert-warning mb-0 shield-ipanalyse-section-fallback" role="alert"><strong>%s</strong><div>%s</div>%s</div>',
			$this->escape( $this->translate( 'This IP analysis section is unavailable.' ) ),
			\sprintf( '%s: %s', $this->escape( $this->translate( 'Section' ) ), $section ),
			$diagnostic === '' ? '' : \sprintf(
				'<div class="small mt-2"><span class="fw-semibold">%s:</span> %s</div>',
				$this->escape( $this->translate( 'Diagnostic' ) ),
				$diagnostic
			)
		);
	}

	private function sanitizeDiagnostic( string $diagnostic ) :string {
		$diagnostic = \trim( \strip_tags( $diagnostic ) );
		if ( \strlen( $diagnostic ) > 500 ) {
			$diagnostic = \substr( $diagnostic, 0, 497 ).'...';
		}
		return $this->escape( $diagnostic );
	}

	private function translate( string $text ) :string {
		return \function_exists( '__' ) ? __( $text, 'wp-simple-firewall' ) : $text;
	}

	private function escape( string $text ) :string {
		return \function_exists( 'esc_html' )
			? esc_html( $text )
			: \htmlspecialchars( $text, \ENT_QUOTES );
	}
}
