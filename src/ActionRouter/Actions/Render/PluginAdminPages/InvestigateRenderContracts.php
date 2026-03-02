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
		$table[ 'title' ] = $this->normalizeContractString( $table, 'title', '' );
		$table[ 'status' ] = $this->normalizeContractString( $table, 'status', 'info' );
		$table[ 'full_log_text' ] = $this->normalizeContractString(
			$table,
			'full_log_text',
			__( 'Full Log', 'wp-simple-firewall' )
		);
		$table[ 'full_log_button_class' ] = $this->normalizeContractString(
			$table,
			'full_log_button_class',
			'btn btn-outline-secondary btn-sm'
		);
		$table[ 'is_flat' ] = $this->normalizeContractBool( $table, 'is_flat', false );
		$table[ 'is_empty' ] = $this->normalizeContractBool( $table, 'is_empty', false );
		$table[ 'empty_status' ] = $this->normalizeContractString( $table, 'empty_status', 'info' );
		$table[ 'empty_text' ] = $this->normalizeContractString( $table, 'empty_text', '' );
		return $table;
	}

	private function normalizeContractString( array $table, string $key, string $default ) :string {
		$value = $table[ $key ] ?? $default;
		return \is_string( $value ) ? $value : $default;
	}

	private function normalizeContractBool( array $table, string $key, bool $default ) :bool {
		$value = $table[ $key ] ?? $default;
		return \is_bool( $value ) ? $value : $default;
	}
}
