<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveCount,
	ScanResultsScopeResolver
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type ScanResultsDisplayNotice array{
 *   is_visible:bool,
 *   mode:'none'|'hidden_ignored'|'including_ignored'|'showing_ignored',
 *   status:string,
 *   text:string,
 *   ignored_count:int
 * }
 * @phpstan-type ActionsQueueScopeState array{
 *   scope:array{type:string,file:string},
 *   active_count:int,
 *   ignored_count:int,
 *   current_count:int,
 *   display_notice:ScanResultsDisplayNotice
 * }
 */
class ActionsQueueScanResultScopeStateBuilder {

	use PluginControllerConsumer;

	public const NOTICE_CONTEXT_ACTIONS_QUEUE = 'actions_queue';

	public const MODE_NONE = 'none';
	public const MODE_HIDDEN_IGNORED = 'hidden_ignored';
	public const MODE_INCLUDING_IGNORED = 'including_ignored';
	public const MODE_SHOWING_IGNORED = 'showing_ignored';

	private ScanResultsScopeResolver $scopeResolver;
	private ScanResultsDisplayOptions $displayOptions;

	public function __construct(
		?ScanResultsScopeResolver $scopeResolver = null,
		?ScanResultsDisplayOptions $displayOptions = null
	) {
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
		$this->displayOptions = $displayOptions ?? new ScanResultsDisplayOptions();
	}

	/**
	 * @param array<string,mixed>|null $currentOptions
	 * @return ActionsQueueScopeState
	 * @throws \InvalidArgumentException
	 */
	public function buildForActionScope(
		string $type,
		string $file,
		?array $currentOptions = null,
		bool $displayNotice = false
	) :array {
		$scope = $this->scopeResolver->normalizeActionScope( $type, $file );
		$options = $this->displayOptions->normalize( $currentOptions ?? $this->displayOptions->activeOnly() );
		$scopeWheres = $this->scopeResolver->wheresForActionScope( $scope[ 'type' ], $scope[ 'file' ] );

		$counter = ( new RetrieveCount() )
			->setScanController( self::con()->comps->scans->AFS() )
			->addWheres( $scopeWheres );
		$activeCount = $counter->countForResultsDisplay( $this->displayOptions->activeOnly() );
		$ignoredCount = $counter->countForResultsDisplay( $this->displayOptions->ignoredOnly() );
		$currentCount = $counter->countForResultsDisplay( $options );

		return [
			'scope'          => $scope,
			'active_count'   => $activeCount,
			'ignored_count'  => $ignoredCount,
			'current_count'  => $currentCount,
			'display_notice' => $this->buildDisplayNotice( $options, $ignoredCount, $displayNotice ),
		];
	}

	/**
	 * @return ScanResultsDisplayNotice
	 */
	public function hiddenDisplayNotice() :array {
		return [
			'is_visible'    => false,
			'mode'          => self::MODE_NONE,
			'status'        => 'info',
			'text'          => '',
			'ignored_count' => 0,
		];
	}

	/**
	 * @param array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * } $options
	 * @return ScanResultsDisplayNotice
	 */
	private function buildDisplayNotice( array $options, int $ignoredCount, bool $isEnabled ) :array {
		if ( !$isEnabled || $ignoredCount < 1 ) {
			return $this->hiddenDisplayNotice();
		}

		if ( $options[ 'ignored_only' ] ) {
			return $this->visibleDisplayNotice(
				self::MODE_SHOWING_IGNORED,
				\_n(
					'Showing %s ignored result for this scope. Ignored results are hidden from active Actions Queue work.',
					'Showing %s ignored results for this scope. Ignored results are hidden from active Actions Queue work.',
					$ignoredCount,
					'wp-simple-firewall'
				),
				$ignoredCount
			);
		}

		if ( $options[ 'include_ignored' ] ) {
			return $this->visibleDisplayNotice(
				self::MODE_INCLUDING_IGNORED,
				\_n(
					'This view includes %s ignored result.',
					'This view includes %s ignored results.',
					$ignoredCount,
					'wp-simple-firewall'
				),
				$ignoredCount
			);
		}

		return $this->visibleDisplayNotice(
			self::MODE_HIDDEN_IGNORED,
			\_n(
				'There is %s ignored result hidden from this active work view. Use Display Results to show ignored results.',
				'There are %s ignored results hidden from this active work view. Use Display Results to show ignored results.',
				$ignoredCount,
				'wp-simple-firewall'
			),
			$ignoredCount
		);
	}

	/**
	 * @phpstan-param self::MODE_HIDDEN_IGNORED|self::MODE_INCLUDING_IGNORED|self::MODE_SHOWING_IGNORED $mode
	 * @return ScanResultsDisplayNotice
	 */
	private function visibleDisplayNotice( string $mode, string $text, int $ignoredCount ) :array {
		return [
			'is_visible'    => true,
			'mode'          => $mode,
			'status'        => 'info',
			'text'          => \sprintf( $text, \number_format_i18n( $ignoredCount ) ),
			'ignored_count' => $ignoredCount,
		];
	}
}
