<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ScansResultsViewBuilder {

	use PluginControllerConsumer;

	public function build() :array {
		$this->cleanScanResultsState();

		$summaryRows = $this->buildSummaryRows();
		$assessmentRows = empty( $summaryRows ) ? $this->buildAssessmentRows() : [];
		$wordpressPayload = $this->buildWordpressSectionPayload();
		$pluginsPayload = $this->buildPluginsSectionPayload();
		$themesPayload = $this->buildThemesSectionPayload();
		$malwarePayload = $this->buildMalwareSectionPayload();
		$fileLockerPayload = $this->buildFileLockerSectionPayload();
		$vulnerabilities = $this->buildVulnerabilities();

		return [
			'vars'    => [
				'tabs'            => $this->buildTabs( [
					[
						'key'   => 'summary',
						'label' => __( 'Summary', 'wp-simple-firewall' ),
						'count' => \count( $summaryRows ),
					],
					[
						'key'      => 'wordpress',
						'label'    => __( 'WordPress', 'wp-simple-firewall' ),
						'count'    => $this->extractSectionCount( $wordpressPayload ),
						'is_shown' => $this->isWordpressTabEnabled(),
					],
					[
						'key'      => 'plugins',
						'label'    => __( 'Plugins', 'wp-simple-firewall' ),
						'count'    => $this->extractSectionCount( $pluginsPayload ),
						'is_shown' => $this->extractSectionCount( $pluginsPayload ) > 0,
					],
					[
						'key'      => 'themes',
						'label'    => __( 'Themes', 'wp-simple-firewall' ),
						'count'    => $this->extractSectionCount( $themesPayload ),
						'is_shown' => $this->extractSectionCount( $themesPayload ) > 0,
					],
					[
						'key'      => 'vulnerabilities',
						'label'    => __( 'Vulnerabilities', 'wp-simple-firewall' ),
						'count'    => (int)( $vulnerabilities[ 'count' ] ?? 0 ),
						'is_shown' => (int)( $vulnerabilities[ 'count' ] ?? 0 ) > 0,
					],
					[
						'key'   => 'malware',
						'label' => __( 'Malware', 'wp-simple-firewall' ),
						'count' => $this->extractSectionCount( $malwarePayload ),
					],
					[
						'key'   => 'file_locker',
						'label' => __( 'File Locker', 'wp-simple-firewall' ),
						'count' => $this->extractSectionCount( $fileLockerPayload ),
					],
				] ),
				'summary_rows'    => $summaryRows,
				'assessment_rows' => $assessmentRows,
				'vulnerabilities' => $vulnerabilities,
			],
			'content' => [
				'section' => [
					'wordpress'  => (string)( $wordpressPayload[ 'render_output' ] ?? '' ),
					'plugins'    => (string)( $pluginsPayload[ 'render_output' ] ?? '' ),
					'themes'     => (string)( $themesPayload[ 'render_output' ] ?? '' ),
					'malware'    => (string)( $malwarePayload[ 'render_output' ] ?? '' ),
					'filelocker' => (string)( $fileLockerPayload[ 'render_output' ] ?? '' ),
				],
			],
		];
	}

	protected function cleanScanResultsState() :void {
		( new CleanQueue() )->execute();
		foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
			$scanCon->cleanStalesResults();
		}
	}

	protected function buildSummaryRows() :array {
		return ( new AttentionItemsProvider() )->buildScanItems();
	}

	protected function buildAssessmentRows() :array {
		return ( new ActionsQueueLandingAssessmentBuilder() )->build()[ 'scans' ] ?? [];
	}

	protected function buildWordpressSectionPayload() :array {
		return $this->actionPayload( Wordpress::class );
	}

	protected function buildPluginsSectionPayload() :array {
		return $this->actionPayload( Plugins::class );
	}

	protected function buildThemesSectionPayload() :array {
		return $this->actionPayload( Themes::class );
	}

	protected function buildMalwareSectionPayload() :array {
		return $this->actionPayload( Malware::class );
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->actionPayload( FileLocker::class );
	}

	protected function buildVulnerabilities() :array {
		return ( new ScansVulnerabilitiesBuilder() )->build();
	}

	protected function isWordpressTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpCore();
	}

	/**
	 * @param array<int,array{key:string,label:string,count:int,is_shown?:bool}> $definitions
	 * @return list<array<string,mixed>>
	 */
	protected function buildTabs( array $definitions ) :array {
		$tabs = [];
		foreach ( $definitions as $definition ) {
			if ( !( $definition[ 'is_shown' ] ?? true ) ) {
				continue;
			}

			$paneId = 'h-tabs-'.$definition[ 'key' ];
			$tabs[] = [
				'key'       => $definition[ 'key' ],
				'pane_id'   => $paneId,
				'nav_id'    => $paneId.'-tab',
				'label'     => $definition[ 'label' ],
				'count'     => (int)$definition[ 'count' ],
				'is_active' => empty( $tabs ),
				'target'    => '#'.$paneId,
				'controls'  => $paneId,
			];
		}
		return $tabs;
	}

	private function actionPayload( string $actionClass ) :array {
		return self::con()->action_router->action( $actionClass )->payload();
	}

	private function extractSectionCount( array $payload ) :int {
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];

		if ( isset( $vars[ 'count_items' ] ) ) {
			return (int)$vars[ 'count_items' ];
		}
		if ( isset( $renderData[ 'count' ] ) ) {
			return (int)$renderData[ 'count' ];
		}

		return (int)( $vars[ 'file_locks' ][ 'count_items' ] ?? 0 );
	}
}
