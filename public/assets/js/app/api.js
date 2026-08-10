(function (window) {
	'use strict';

	// HTTPエラーのステータスとJSON本文を保持する。
	function ApiError(status, body) {
		this.name = 'ApiError';
		this.status = status;
		this.body = body || null;
		this.message = body && body.error ? body.error.message : '';
	}
	ApiError.prototype = Object.create(Error.prototype);

	// 検索条件をURLクエリ文字列へ変換する。
	function query(parameters) {
		var values = [];
		Object.keys(parameters || {}).forEach(function (name) {
			var value = parameters[name];
			if (value !== '' && value !== null && value !== undefined) {
				values.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
			}
		});
		return values.length ? '?' + values.join('&') : '';
	}

	// 同一オリジンのGET APIを呼び出してJSONを返す。
	function get(url, parameters) {
		return window.fetch(url + query(parameters), {
			method: 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		}).then(function (response) {
			return response.json().catch(function () {
				throw new ApiError(response.status, null);
			}).then(function (body) {
				if ( ! response.ok) {
					throw new ApiError(response.status, body);
				}
				return body;
			});
		});
	}

	// ページングされたGET APIを最終ページまで取得する。
	function allPages(url) {
		var rows = [];
		// 指定ページを取得し、必要なら次ページへ進む。
		function load(page) {
			return get(url, { page: page }).then(function (body) {
				rows = rows.concat(Array.isArray(body.data) ? body.data : []);
				var pages = body.meta && body.meta.pagination
					? Number(body.meta.pagination.total_pages)
					: page;
				return page < pages ? load(page + 1) : rows;
			});
		}
		return load(1);
	}

	// 一覧画面から使用するGET専用APIを公開する。
	window.InventoryApi = {
		ApiError: ApiError,
		get: get,
		allPages: allPages,
		isUnauthorized: function (error) {
			return error instanceof ApiError && (error.status === 401 || error.status === 403);
		},
		message: function (error, fallback) {
			return error instanceof ApiError && error.message ? error.message : fallback;
		}
	};
}(window));
