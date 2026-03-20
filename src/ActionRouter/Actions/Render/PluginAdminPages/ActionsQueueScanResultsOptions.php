<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ActionsQueueScanResultsOptions {

	public const DISPLAY_CONTEXT = 'actions_queue';

	/**
	 * @return array{include_ignored:bool,ignored_only:bool}
	 */
	public function activeOnly() :array {
		return [
			'include_ignored' => false,
			'ignored_only'    => false,
		];
	}

	/**
	 * @return array{include_ignored:bool,ignored_only:bool}
	 */
	public function ignoredOnly() :array {
		return [
			'include_ignored' => true,
			'ignored_only'    => true,
		];
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{include_ignored:bool,ignored_only:bool}
	 */
	public function normalize( ?array $options ) :array {
		return [
			'include_ignored' => !empty( $options[ 'include_ignored' ] ),
			'ignored_only'    => !empty( $options[ 'ignored_only' ] ),
		];
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{include_ignored:bool,ignored_only:bool}
	 * }
	 */
	public function buildActionData( ?array $options = null ) :array {
		return [
			'display_context'        => self::DISPLAY_CONTEXT,
			'results_display_options' => $this->normalize( $options ),
		];
	}

	/**
	 * @param array<string,mixed> $actionData
	 */
	public function hasExplicitActionOptions( array $actionData ) :bool {
		return \is_array( $actionData[ 'results_display_options' ] ?? null )
			|| \array_key_exists( 'include_ignored', $actionData )
			|| \array_key_exists( 'ignored_only', $actionData );
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array{include_ignored:bool,ignored_only:bool}
	 */
	public function fromActionData( array $actionData ) :array {
		$options = \is_array( $actionData[ 'results_display_options' ] ?? null )
			? $actionData[ 'results_display_options' ]
			: [
				'include_ignored' => $actionData[ 'include_ignored' ] ?? false,
				'ignored_only'    => $actionData[ 'ignored_only' ] ?? false,
			];

		return $this->normalize( $options );
	}
}
