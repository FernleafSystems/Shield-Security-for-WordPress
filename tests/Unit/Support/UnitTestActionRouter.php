<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestActionRouter {

	public function __construct(
		private ?RenderCapture $capture = null,
		private ?\Closure $renderer = null
	) {
	}

	public function render( string $action, array $actionData = [] ) :string {
		$this->capture?->record( $action, $actionData );

		if ( $this->renderer instanceof \Closure ) {
			return (string)( $this->renderer )( $action, $actionData );
		}

		return '';
	}
}
