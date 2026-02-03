export class Navigation {

	/**
	 * Handles page redirect or reload based on response data.
	 * Checks for redirect_url first, then falls back to reload.
	 * @param {Object} resp - The AJAX response object
	 * @param {string|null} defaultUrl - Optional fallback URL if no redirect_url in response
	 */
	static RedirectOrReload( resp, defaultUrl ) {
		if ( resp && resp.data && resp.data.redirect_url ) {
			window.location.href = resp.data.redirect_url;
		}
		else if ( typeof defaultUrl === 'string' && defaultUrl.length > 0 ) {
			window.location.href = defaultUrl;
		}
		else {
			location.reload();
		}
	}
}
