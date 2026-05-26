<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Utility\AjaxRenderTargetPolicy
};

trait AjaxRenderPolicyAssertions {

	protected function assertAjaxRenderPayloadAllowedByPolicy( array $payload, string $context = '' ) :void {
		$this->assertSame( AjaxRender::SLUG, $payload[ ActionData::FIELD_EXECUTE ] ?? '', $context );
		$this->assertArrayHasKey( 'render_slug', $payload, $context );
		$this->assertTrue(
			( new AjaxRenderTargetPolicy() )->isAllowed( (string)$payload[ 'render_slug' ] ),
			$context === '' ? (string)$payload[ 'render_slug' ] : $context
		);
	}

	protected function assertAjaxRenderPayloadsAllowedByPolicy(
		array $payloadRoot,
		string $context = '',
		int $minimumPayloads = 1
	) :void {
		$payloads = $this->collectAjaxRenderPayloads( $payloadRoot );

		$this->assertGreaterThanOrEqual( $minimumPayloads, \count( $payloads ), $context );
		foreach ( $payloads as $index => $payload ) {
			$this->assertAjaxRenderPayloadAllowedByPolicy(
				$payload,
				$context === '' ? 'ajax_render payload '.$index : $context.' payload '.$index
			);
		}
	}

	private function collectAjaxRenderPayloads( array $candidate ) :array {
		$payloads = [];
		if ( ( $candidate[ ActionData::FIELD_EXECUTE ] ?? '' ) === AjaxRender::SLUG ) {
			$payloads[] = $candidate;
		}

		foreach ( $candidate as $value ) {
			if ( \is_array( $value ) ) {
				$payloads = \array_merge( $payloads, $this->collectAjaxRenderPayloads( $value ) );
			}
		}

		return $payloads;
	}
}
