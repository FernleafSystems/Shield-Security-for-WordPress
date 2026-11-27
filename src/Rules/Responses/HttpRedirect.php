<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class HttpRedirect extends Base {
	use Traits\IsTerminating;

	public function execResponse() :void {
		if ( !$this->canRedirect() ) {
			throw new \Exception( 'Redirection skipped to prevent infinite redirects.' );
		}
		\header( 'Cache-Control: no-store, no-cache' );
		$url = $this->p->redirect_url;
		if ( $this->p->isFilledFromRequest( 'redirect_url' ) ) {
			// A visitor controls a destination filled in from the request, so it may only go to a host WordPress allows.
			// One that isn't allowed is skipped, rather than sent to the admin area where the same rule could match again.
			$url = wp_validate_redirect( $url, '' );
			if ( $url === '' ) {
				throw new \Exception( 'Redirection skipped: the destination filled in from the request is not an allowed host.' );
			}
			wp_safe_redirect( $url, $this->p->status_code );
		}
		elseif ( \str_starts_with( $url, '/' ) ) {
			wp_safe_redirect( $url, $this->p->status_code );
		}
		else {
			wp_redirect( $url, $this->p->status_code ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- An off-site address an administrator typed into the rule; local paths and addresses filled in from the request use wp_safe_redirect() above.
		}
		die();
	}

	/**
	 * Prevents any sort of infinite redirects
	 */
	private function canRedirect() :bool {
		$req = $this->req;
		$to = (array)\wp_parse_url( $this->p->redirect_url );
		return !$this->req->wp_is_ajax
			   &&
			   ( $to[ 'host' ] ?? '' ) !== $req->host || \trim( $to[ 'path' ] ?? '', '/' ) !== \trim( $req->path, '/' );
	}

	public function getParamsDef() :array {
		$statusCodes = [
			301 => __( 'Permanent Redirect (301)', 'wp-simple-firewall' ),
			302 => __( 'Temporary Redirect (302)', 'wp-simple-firewall' ),
		];
		return [
			'redirect_url' => [
				'type'  => EnumParameters::TYPE_URL,
				'label' => __( 'Redirect Location', 'wp-simple-firewall' ),
			],
			'status_code'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $statusCodes ),
				'enum_labels' => $statusCodes,
				'default'     => 302,
				'label'       => __( 'Redirect Status', 'wp-simple-firewall' ),
			],
		];
	}
}
