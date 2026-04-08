<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionsQueueScanResultsOptions {

	use PluginControllerConsumer;

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
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function normalize( ?array $options ) :array {
		return [
			'include_ignored'  => !empty( $options[ 'include_ignored' ] ),
			'include_repaired' => !empty( $options[ 'include_repaired' ] ),
			'include_deleted'  => !empty( $options[ 'include_deleted' ] ),
			'ignored_only'     => !empty( $options[ 'ignored_only' ] ),
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
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
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
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildForcedIgnoredActionData() :array {
		return $this->buildExplicitActionData( $this->forcedIgnoredOptions() );
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   display_context:string,
	 *   subject_type:string,
	 *   subject_id:string,
	 *   results_display_options?:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildSubjectActionData( string $subjectType, string $subjectId, ?array $options = null ) :array {
		return \array_merge(
			$options === null
				? $this->buildDisplayContextActionData()
				: $this->buildExplicitActionData( $options ),
			[
				'subject_type' => $subjectType,
				'subject_id'   => $subjectId,
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
		return $this->explicitOptionsFromActionData( $actionData ) ?? $this->storedOptions();
	}

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function forcedIgnoredOptions() :array {
		return \array_merge(
			$this->storedOptions(),
			[
				'include_ignored' => true,
				'ignored_only'    => true,
			]
		);
	}

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }
	 */
	public function storedOptions() :array {
		try {
			$stored = self::con()->opts->optGet( 'scan_results_table_display' );
			return $this->normalize( [
				'include_ignored'  => \is_array( $stored ) && \in_array( 'include_ignored', $stored, true ),
				'include_repaired' => \is_array( $stored ) && \in_array( 'include_repaired', $stored, true ),
				'include_deleted'  => \is_array( $stored ) && \in_array( 'include_deleted', $stored, true ),
			] );
		}
		catch ( \Throwable $e ) {
			return $this->activeOnly();
		}
	}
}
