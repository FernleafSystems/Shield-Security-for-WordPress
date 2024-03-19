<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class ConfigOptGet extends ConfigBase {

	protected function cmdParts() :array {
		return [ 'opt-get' ];
	}

	protected function cmdShortDescription() :string {
		return 'View the value of any configuration option.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'key',
				'optional'    => false,
				'options'     => $this->getOptionsForWpCli(),
				'description' => 'The option key to get.',
			],
		];
	}

	public function runCmd() :void {
		$opts = self::con()->opts;

		$optKey = $this->execCmdArgs[ 'key' ];
		if ( !$opts->optExists( $optKey ) ) {
			\WP_CLI::log( __( 'Not a valid option key.', 'wp-simple-firewall' ) );
		}
		else {
			$value = $opts->optGet( $optKey );
			if ( !\is_numeric( $value ) && empty( $value ) ) {
				\WP_CLI::log( __( 'No value set.', 'wp-simple-firewall' ) );
			}
			else {
				$explain = '';

				if ( \is_array( $value ) ) {
					$value = sprintf( '[ %s ]', \implode( ', ', $value ) );
				}
				if ( $opts->optType( $optKey ) === 'checkbox' ) {
					$explain = sprintf( 'Note: %s', __( '"Y" = Turned On; "N" = Turned Off' ) );
				}

				\WP_CLI::log( sprintf( __( 'Current value: %s', 'wp-simple-firewall' ), $value ) );
				if ( !empty( $explain ) ) {
					\WP_CLI::log( $explain );
				}
			}
		}
	}
}