<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ScanResultsDisplayOptions {

	public const DISPLAY_CONTEXT = 'actions_queue';

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function activeOnly() :array {
		return [
			'include_ignored'  => false,
			'include_repaired' => false,
			'include_deleted'  => false,
			'ignored_only'     => false,
		];
	}

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function ignoredOnly() :array {
		return [
			'include_ignored'  => true,
			'include_repaired' => false,
			'include_deleted'  => false,
			'ignored_only'     => true,
		];
	}

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function activeAndIgnored() :array {
		return [
			'include_ignored'  => true,
			'include_repaired' => false,
			'include_deleted'  => false,
			'ignored_only'     => false,
		];
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function normalize( ?array $options ) :array {
		$normalized = [
			'include_ignored'  => $this->toBool( $options[ 'include_ignored' ] ?? false ),
			'include_repaired' => $this->toBool( $options[ 'include_repaired' ] ?? false ),
			'include_deleted'  => $this->toBool( $options[ 'include_deleted' ] ?? false ),
			'ignored_only'     => $this->toBool( $options[ 'ignored_only' ] ?? false ),
		];

		if ( $normalized[ 'ignored_only' ] ) {
			$normalized[ 'include_ignored' ] = true;
		}

		return $normalized;
	}

	/**
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildDisplayContextActionData() :array {
		return $this->mergeIntoActionData( [], $this->activeOnly() );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildExplicitActionData( array $options ) :array {
		return $this->mergeIntoActionData( [], $options );
	}

	/**
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildForcedIgnoredActionData() :array {
		return $this->buildExplicitActionData( $this->ignoredOnly() );
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }&array<string,mixed>
	 */
	public function mergeIntoActionData( array $actionData, ?array $options = null ) :array {
		return \array_merge(
			$actionData,
			[
				'display_context'         => self::DISPLAY_CONTEXT,
				'results_display_options' => $this->normalize( $options ?? $this->activeOnly() ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }|null
	 */
	public function explicitOptionsFromActionData( array $actionData ) :?array {
		return \is_array( $actionData[ 'results_display_options' ] ?? null )
			? $this->normalize( $actionData[ 'results_display_options' ] )
			: null;
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function currentOptionsFromActionData( array $actionData ) :array {
		return $this->explicitOptionsFromActionData( $actionData ) ?? $this->activeOnly();
	}

	/**
	 * @param mixed $value
	 */
	private function toBool( $value ) :bool {
		if ( \is_bool( $value ) ) {
			return $value;
		}

		if ( \is_string( $value ) || \is_int( $value ) || \is_float( $value ) ) {
			return \filter_var( $value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE ) ?? false;
		}

		return false;
	}
}
