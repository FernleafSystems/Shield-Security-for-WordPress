<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class Malai extends Base {

	public const SLUG = 'scanitemanalysis_malai';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_malai.twig';

	protected function getRenderData() :array {
		$item = $this->getScanItem();
		$pathFull = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;

		if ( !Services::WpFs()->isAccessibleFile( $pathFull ) ) {
			throw new ActionException( "This file doesn't appear to be available on this site any longer." );
		}
		if ( !\in_array( \strtolower( Paths::Ext( $pathFull ) ), [ 'php', 'php7', 'phtml', 'phtm', 'ico' ] ) ) {
			throw new ActionException(
				sprintf( __( "The file type/extension (%s) isn't supported by the MAL{ai} engine.", 'wp-simple-firewall' ), Paths::Ext( $pathFull ) )
			);
		}
		if ( $item->is_mal ) {
			throw new ActionException( sprintf(
				__( 'Please see the "%s" tab for more information as this file has already been classified as "potential malware" in the scan.', 'wp-simple-firewall' ),
				__( 'Info', 'wp-simple-firewall' )
			) );
		}

		return [
			'flags'   => [
				'can_malai' => self::con()->caps->canScanMalwareMalai(),
			],
			'vars'    => [
				'form' => [
					'rid' => $item->VO->scanresult_id,
				]
			],
			'strings' => [
				'title'           => sprintf( __( '%s Lookup', 'wp-simple-firewall' ), 'MAL{ai}' ),
				'subtitle'        => sprintf( __( '%s is our exclusive AI-powered Malware Scanning and Detection engine.', 'wp-simple-firewall' ), 'MAL{ai}' ),
				'introduction'    => [
					__( "It can assess file contents in order to give an estimation as to whether a file is malware, or clean of malicious code.", 'wp-simple-firewall' ),
					__( "It is always learning based on new file and malware samples it receives and will continue to improve over time.", 'wp-simple-firewall' ),
					__( 'In the case that the assessment is a "prediction", you should always review the contents yourself to determine whether the file is safe.', 'wp-simple-firewall' ),
					__( 'When the file assessment is "known", then the file has been reviewed and confirmed to be either "known malware" or "known clean".', 'wp-simple-firewall' ),
				],
				'important'       => [
					__( "Always ensure that there is NO sensitive or private data within the file when submitting it to MAL{ai} for assessment.", 'wp-simple-firewall' ),
					__( "This is YOUR responsibility - don't submit the file unless you're absolutely sure.", 'wp-simple-firewall' ),
					sprintf( __( 'It is important to understand that the result of this query is an estimation to help you assess the file, not a conclusion.', 'wp-simple-firewall' ), 'MAL{ai}' ),
					__( "As always, your use and reliance of this service is done so at your own risk.", 'wp-simple-firewall' ),
				],
				'i_accept'        => __( 'I have read the above information & warnings, and fully accept any and all implications.', 'wp-simple-firewall' ),
				'run_malai_query' => __( 'Run MAL{ai} Query', 'wp-simple-firewall' ),
				'cant_run_malai'  => __( "Sorry, you don't have access to run MAL{ai} queries, please upgrade your plan.", 'wp-simple-firewall' ),
			]
		];
	}
}