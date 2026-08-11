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
		const values = [];
		Object.keys(parameters || {}).forEach(function (name) {
			const value = parameters[name];
			if (value !== '' && value !== null && value !== undefined) {
				values.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
			}
		});
		return values.length ? '?' + values.join('&') : '';
	}

	// 同一オリジンのGET APIを呼び出してJSONを返す。
	async function get(url, parameters) {
		const response = await window.fetch(url + query(parameters), {
			method: 'GET',
			headers: { Accept: 'application/json' }
		});
		let body;

		try {
			body = await response.json();
		}
		catch (error) {
			throw new ApiError(response.status, null);
		}

		if ( ! response.ok) {
			throw new ApiError(response.status, body);
		}

		return body;
	}

	// 一覧画面から使用するGET専用APIを公開する。
	window.InventoryApi = {
		ApiError: ApiError,
		get: get,
		isUnauthorized: function (error) {
			return error instanceof ApiError && (error.status === 401 || error.status === 403);
		},
		message: function (error, fallback) {
			return error instanceof ApiError && error.message ? error.message : fallback;
		}
	};
}(window));
