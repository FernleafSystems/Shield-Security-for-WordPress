<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Utilities\URL;

trait InvestigateRenderContracts {

	protected function buildLookupRouteContract( string $subNav ) :array {
		return [
			'page'    => self::con()->plugin_urls->rootAdminPageSlug(),
			'nav'     => PluginNavs::NAV_ACTIVITY,
			'nav_sub' => $subNav,
		];
	}

	protected function buildFullLogHrefWithSearch( string $nav, string $subNav, string $search ) :string {
		return URL::Build(
			self::con()->plugin_urls->adminTopNav( $nav, $subNav ),
			[
				'search' => $search,
			]
		);
	}

	protected function buildTableContainerContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref
	) :array {
		return $this->normalizeInvestigationTableContract( [
			'title'           => $title,
			'status'          => $status,
			'table_type'      => $tableType,
			'subject_type'    => $subjectType,
			'subject_id'      => $subjectId,
			'datatables_init' => $datatablesInit,
			'table_action'    => $tableAction,
			'full_log_href'   => $fullLogHref,
		] );
	}

	protected function withEmptyStateTableContract( array $table, int $count, string $emptyText, string $emptyStatus = 'info' ) :array {
		if ( $count > 0 ) {
			$table[ 'is_empty' ] = false;
			return $this->normalizeInvestigationTableContract( $table );
		}

		$table[ 'is_empty' ] = true;
		$table[ 'empty_status' ] = $emptyStatus;
		$table[ 'empty_text' ] = $emptyText;
		unset( $table[ 'datatables_init' ], $table[ 'table_action' ], $table[ 'table_type' ], $table[ 'subject_type' ], $table[ 'subject_id' ] );
		return $this->normalizeInvestigationTableContract( $table );
	}

	protected function normalizeInvestigationTableContract( array $table ) :array {
		$table[ 'title' ] = (string)( $table[ 'title' ] ?? '' );
		$table[ 'status' ] = (string)( $table[ 'status' ] ?? 'info' );
		$table[ 'full_log_text' ] = (string)( $table[ 'full_log_text' ] ?? __( 'Full Log', 'wp-simple-firewall' ) );
		$table[ 'full_log_button_class' ] = (string)( $table[ 'full_log_button_class' ] ?? 'btn btn-outline-secondary btn-sm' );
		$table[ 'is_flat' ] = (bool)( $table[ 'is_flat' ] ?? false );
		$table[ 'is_empty' ] = (bool)( $table[ 'is_empty' ] ?? false );
		$table[ 'empty_status' ] = (string)( $table[ 'empty_status' ] ?? 'info' );
		$table[ 'empty_text' ] = (string)( $table[ 'empty_text' ] ?? '' );
		return $table;
	}
}
