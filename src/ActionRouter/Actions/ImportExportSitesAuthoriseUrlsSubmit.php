<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;

class ImportExportSitesAuthoriseUrlsSubmit extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_sites_authorise_urls_submit';

	protected function exec() {
		$activeClientCountBefore = 0;
		try {
			$form = $this->action_data[ 'form_data' ] ?? [];
			if ( empty( $form ) || !\is_array( $form ) ) {
				throw new \RuntimeException( __( 'No data. Please retry', 'wp-simple-firewall' ) );
			}
			if ( ( $form[ 'confirm' ] ?? '' ) !== 'Y' ) {
				throw new \RuntimeException( __( 'Please check the box to confirm this action', 'wp-simple-firewall' ) );
			}

			$activeClientCountBefore = ( new SiteRepository() )->countActiveRows();
			$result = ( new ImportExportController() )->authoriseUrlsForSyncSites(
				\preg_split( '#\R#', (string)( $form[ 'urls' ] ?? '' ) ) ?: []
			);

			foreach ( $result[ 'authorised_urls' ] as $url ) {
				self::con()->comps->events->fireEvent(
					'whitelist_site_added',
					[ 'audit_params' => [ 'site' => $url ] ]
				);
			}

			$message = $result[ 'authorised_count' ] > 0
				? sprintf(
					_n(
						'%s URL authorised.',
						'%s URLs authorised.',
						$result[ 'authorised_count' ],
						'wp-simple-firewall'
					),
					$result[ 'authorised_count' ]
				)
				: __( 'No new URLs were authorised.', 'wp-simple-firewall' );
			if ( $result[ 'already_authorised_count' ] > 0 ) {
				$message .= ' '.sprintf(
					_n(
						'%s URL was already authorised.',
						'%s URLs were already authorised.',
						$result[ 'already_authorised_count' ],
						'wp-simple-firewall'
					),
					$result[ 'already_authorised_count' ]
				);
			}
			$success = true;
		}
		catch ( \Throwable $e ) {
			$result = [
				'authorised_urls'          => [],
				'already_authorised_urls'  => [],
				'authorised_count'         => 0,
				'already_authorised_count' => 0,
				'total_count'              => 0,
			];
			$message = $e->getMessage();
			$success = false;
		}

		$this->response()->setPayload( \array_merge( [
			'page_reload' => $success && $result[ 'authorised_count' ] > 0 && $activeClientCountBefore === 0,
			'message'     => $message,
		], $result ) )->setPayloadSuccess( $success );
	}
}
