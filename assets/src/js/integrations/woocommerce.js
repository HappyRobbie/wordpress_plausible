/**
 * Plausible Analytics
 *
 * WooCommerce integration JS.
 */
const {fetch: originalFetch} = window;

window.fetch = (...args) => {
	let [resource, config] = args;

	if (config === undefined || config.body === undefined) {
		return originalFetch(resource, config);
	}

	let data;

	try {
		data = JSON.parse(config.body);
	} catch (e) {
		return originalFetch(resource, config);
	}

	if (data === null || data.requests === undefined || !data.requests instanceof Array) {
		return originalFetch(resource, config);
	}

	data.requests.forEach(function (request) {
		if (!request.path.includes('cart/add-item')) {
			return;
		}

		request.body._wp_http_referer = window.location.href;
	});

	config.body = JSON.stringify(data);

	return originalFetch(resource, config);
};
