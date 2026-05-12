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
}
