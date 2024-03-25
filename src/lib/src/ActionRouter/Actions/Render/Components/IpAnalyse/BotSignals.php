<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalNames;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Services\Services;

class BotSignals extends Base {

	public const SLUG = 'ipanalyse_botsignals';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_botsignals.twig';

	protected function getRenderData() :array {
		$signals = [];
		$scores = ( new CalculateVisitorBotScores() )
			->setIP( $this->action_data[ 'ip' ] )
			->scores();
		try {
			$record = ( new BotSignalsRecord() )
				->setIP( $this->action_data[ 'ip' ] )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$record = null;
		}

		if ( !empty( $record ) ) {
			$carbon = Services::Request()->carbon();
			foreach ( $scores as $scoreKey => $scoreValue ) {
				$column = $scoreKey.'_at';
				if ( $scoreValue !== 0 ) {
					if ( empty( $record ) || empty( $record->{$column} ) ) {
						if ( \in_array( $scoreKey, [ 'known', 'created' ] ) ) {
							$signals[ $scoreKey ] = __( 'N/A', 'wp-simple-firewall' );
						}
						else {
							$signals[ $scoreKey ] = __( 'Never Recorded', 'wp-simple-firewall' );
						}
					}
					else {
						$signals[ $scoreKey ] = sprintf( '%s (%s)',
							$carbon->setTimestamp( $record->{$column} )->diffForHumans(),
							Services::WpGeneral()->getTimeStringForDisplay( $record->{$column} )
						);
					}
				}
			}
		}

		return [
			'strings' => [
				'title'            => __( 'Bot Signals', 'wp-simple-firewall' ),
				'signal'           => __( 'Signal', 'wp-simple-firewall' ),
				'score'            => __( 'Score', 'wp-simple-firewall' ),
				'total_score'      => __( 'Total Reputation Score', 'wp-simple-firewall' ),
				'when'             => __( 'When', 'wp-simple-firewall' ),
				'bot_probability'  => __( 'Bad Bot Probability', 'wp-simple-firewall' ),
				'botsignal_delete' => __( 'Delete All Bot Signals', 'wp-simple-firewall' ),
				'signal_names'     => ( new BotSignalNames() )->getBotSignalNames(),
				'no_signals'       => __( 'There are no bot signals for this IP address.', 'wp-simple-firewall' ),
			],
			'ajax'    => [
				'has_signals' => !empty( $signals ),
			],
			'flags'   => [
				'has_signals' => !empty( $signals ),
			],
			'vars'    => [
				'signals'       => $signals,
				'total_signals' => \count( $signals ),
				'scores'        => $scores,
				'total_score'   => \array_sum( $scores ),
				'minimum'       => \array_sum( $scores ),
				'probability'   => 100 - (int)\max( 0, \min( 100, \array_sum( $scores ) ) )
			],
		];
	}
}