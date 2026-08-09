(function (window, document) {
	'use strict';

	function ApiError(status, body) {
		var error = body && body.error ? body.error : {};

		this.name = 'ApiError';
		this.status = status;
		this.code = error.code || 'REQUEST_FAILED';
		this.message = error.message || '通信に失敗しました。';
		this.fields = error.fields || {};
		this.requestId = body && body.request_id ? body.request_id : '';
	}

	ApiError.prototype = Object.create(Error.prototype);
	ApiError.prototype.constructor = ApiError;

	function parseBody(response) {
		return response.text().then(function (text) {
			if (text === '') {
				return null;
			}

			try {
				return JSON.parse(text);
			}
			catch (error) {
				throw new ApiError(response.status, null);
			}
		});
	}

	function request(url, options) {
		options = options || {};

		var fetchOptions = {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		};

		if (options.body) {
			fetchOptions.body = options.body;
		}

		return window.fetch(url, fetchOptions)
			.then(function (response) {
				return parseBody(response).then(function (body) {
					if ( ! response.ok) {
						throw new ApiError(response.status, body);
					}

					if ( ! body || typeof body !== 'object'
						|| ! Object.prototype.hasOwnProperty.call(body, 'data')) {
						throw new ApiError(response.status, null);
					}

					return body;
				});
			});
	}

	function queryUrl(url, parameters) {
		var parts = [];

		Object.keys(parameters || {}).forEach(function (name) {
			var value = parameters[name];

			if (value !== null && typeof value !== 'undefined' && value !== '') {
				parts.push(
					encodeURIComponent(name) + '=' + encodeURIComponent(String(value))
				);
			}
		});

		if (parts.length === 0) {
			return url;
		}

		return url + (url.indexOf('?') === -1 ? '?' : '&') + parts.join('&');
	}

	function formData(values, form) {
		var data = form ? new window.FormData(form) : new window.FormData();

		Object.keys(values || {}).forEach(function (name) {
			data.delete(name);
			data.append(
				name,
				values[name] === null || typeof values[name] === 'undefined'
					? ''
					: String(values[name])
			);
		});

		return data;
	}

	function allPages(url, parameters) {
		var rows = [];
		var nextPage = 1;

		function loadPage() {
			var pageParameters = copy(parameters || {});
			pageParameters.page = nextPage;

			return get(url, pageParameters).then(function (body) {
				var pagination = body.meta && body.meta.pagination
					? body.meta.pagination
					: null;

				if ( ! Array.isArray(body.data) || ! pagination) {
					throw new ApiError(500, null);
				}

				rows = rows.concat(body.data);

				if (nextPage < Number(pagination.total_pages)) {
					nextPage += 1;
					return loadPage();
				}

				return rows;
			});
		}

		return loadPage();
	}

	function copy(source) {
		var target = {};

		Object.keys(source).forEach(function (key) {
			target[key] = source[key];
		});

		return target;
	}

	function message(error, fallback) {
		var result = error instanceof ApiError && error.message
			? error.message
			: fallback;

		if (error instanceof ApiError
			&& error.status >= 500
			&& error.requestId !== '') {
			result += ' 追跡ID: ' + error.requestId;
		}

		return result;
	}

	window.InventoryApi = {
		ApiError: ApiError,
		get: function (url, parameters) {
			return request(queryUrl(url, parameters || {}));
		},
		post: function (url, values, form) {
			return request(url, {
				method: 'POST',
				body: formData(values, form)
			});
		},
		allPages: allPages,
		message: message,
		isUnauthorized: function (error) {
			return error instanceof ApiError && error.status === 401;
		}
	};
}(window, document));
