<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestActionRouter {

	private ?RenderCapture $capture;

	private ?\Closure $renderer;

	public function __construct(
		?RenderCapture $capture = null,
		?\Closure $renderer = null
	) {
		$this->capture = $capture;
		$this->renderer = $renderer;
	}

	public function render( string $action, array $actionData = [] ) :string {
		if ( $this->capture instanceof RenderCapture ) {
			$this->capture->record( $action, $actionData );
		}

		if ( $this->renderer instanceof \Closure ) {
			return (string)( $this->renderer )( $action, $actionData );
		}

		return '';
	}

	public function action( string $action, array $actionData = [] ) {
		unset( $action );

		$renderAction = (string)( $actionData[ 'render_action_slug' ] ?? '' );
		$renderActionData = \is_array( $actionData[ 'render_action_data' ] ?? null )
			? $actionData[ 'render_action_data' ]
			: [];
		$output = $this->render( $renderAction, $renderActionData );

		return new class( $output ) {
			private string $output;

			public function __construct( string $output ) {
				$this->output = $output;
			}

			public function payload() :array {
				return [
					'render_output'     => $this->output,
					'html'              => $this->output,
					'render_error'      => false,
					'render_error_code' => '',
				];
			}
		};
	}
}
