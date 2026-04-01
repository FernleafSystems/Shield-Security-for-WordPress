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
	 * @return array{display_context:string}
	 */
	public function buildDisplayContextActionData() :array {
		return [
			'display_context' => self::DISPLAY_CONTEXT,
		];
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{include_ignored:bool,ignored_only:bool}
	 * }
	 */
	public function buildExplicitActionData( array $options ) :array {
		return \array_merge(
			$this->buildDisplayContextActionData(),
			[
				'results_display_options' => $this->normalize( $options ),
			]
		);
	}

	/**
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{include_ignored:bool,ignored_only:bool}
	 * }
	 */
	public function buildIgnoredOnlyActionData() :array {
		return $this->buildExplicitActionData( $this->ignoredOnly() );
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array{include_ignored:bool,ignored_only:bool}|null
	 */
	public function explicitOptionsFromActionData( array $actionData ) :?array {
		return \is_array( $actionData[ 'results_display_options' ] ?? null )
			? $this->normalize( $actionData[ 'results_display_options' ] )
			: null;
	}
}
