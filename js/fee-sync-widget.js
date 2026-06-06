(function () {
	'use strict';

	var OWNER_TYPE_MAP = { lead: 'L', deal: 'D' };
	var pollIntervalMs = 5000;
	var pollTimer = null;
	var lastProductHash = null;
	var isSyncing = false;

	function callMethod(method, params) {
		return new Promise(function (resolve, reject) {
			BX24.callMethod(method, params || {}, function (result) {
				if (result.error()) {
					reject(result.error());
				} else {
					resolve(result.data());
				}
			});
		});
	}

	function hashProductRows(rows) {
		return JSON.stringify(rows.map(function (r) {
			return {
				id: r.id,
				name: r.productName,
				price: r.price,
				qty: r.quantity,
				productId: r.productId
			};
		}));
	}

	function logAction(message, type) {
		var el = document.getElementById('sync-log');
		if (!el) return;
		var entry = document.createElement('div');
		entry.className = 'sync-log-entry sync-log-' + (type || 'info');
		entry.textContent = new Date().toLocaleTimeString() + ' — ' + message;
		el.insertBefore(entry, el.firstChild);
		while (el.children.length > 50) {
			el.removeChild(el.lastChild);
		}
	}

	function setStatus(text, type) {
		var el = document.getElementById('sync-status');
		if (!el) return;
		el.textContent = text;
		el.className = 'alert alert-' + (type || 'info');
	}

	function formatAction(action) {
		switch (action.action) {
			case 'created':
				return 'Created SPA #' + action.spaId + ' for "' + action.product + '"';
			case 'updated':
				return 'Updated SPA #' + action.spaId + ' for "' + action.product + '"';
			case 'deleted':
				return 'Deleted SPA #' + action.spaId + ' ("' + action.product + '")';
			case 'linked':
				return 'Linked field ' + action.field + ': [' + (action.ids || []).join(', ') + ']';
			case 'unlinked':
				return 'Unlinked SPA #' + action.spaId;
			default:
				return JSON.stringify(action);
		}
	}

	function syncEntity(entityType, entityId) {
		if (isSyncing) return Promise.resolve();
		isSyncing = true;
		setStatus('Synchronizing...', 'info');

		return fetch('api/sync.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				action: 'sync',
				entityType: entityType,
				entityId: entityId
			})
		})
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (result.actions) {
					result.actions.forEach(function (action) {
						var type = action.action === 'deleted' ? 'warning' : 'success';
						if (action.action === 'linked') type = 'info';
						logAction(formatAction(action), type);
					});
				}
				if (result.errors && result.errors.length > 0) {
					setStatus('Sync completed with ' + result.errors.length + ' warning(s)', 'warning');
					result.errors.forEach(function (e) { logAction(e, 'warning'); });
				} else if (result.success) {
					setStatus('Synchronized ' + (result.actions || []).length + ' action(s)', 'success');
				} else {
					setStatus('Sync failed', 'danger');
				}
				return result;
			})
			.catch(function (err) {
				var msg = err.message || String(err);
				setStatus('Sync failed: ' + msg, 'danger');
				logAction('Error: ' + msg, 'danger');
				return { success: false, actions: [], errors: [msg] };
			})
			.then(function (result) {
				isSyncing = false;
				return result;
			});
	}

	function refreshProductHash(entityType, entityId) {
		var ownerType = OWNER_TYPE_MAP[entityType];
		return getProductRows(ownerType, entityId).then(function (rows) {
			lastProductHash = hashProductRows(rows);
			return rows;
		});
	}

	function getProductRows(ownerType, entityId) {
		return callMethod('crm.item.productrow.list', {
			filter: { '=ownerType': ownerType, '=ownerId': entityId }
		}).then(function (data) { return data.productRows || []; });
	}

	// Deprecated polling mechanism removed in favor of real-time event handling via BX24 push events.
	// The following functions are retained as placeholders for potential future use but are currently no-ops.
	function startPolling(entityType, entityId) {
		// No polling needed; real-time updates will be handled via BX24 events.
	}

	function stopPolling() {
		// No polling timer to clear.
	}

	window.FeeSyncWidget = {
		init: function (entityType, entityId) {
			BX24.init(function () {
				BX24.fitWindow();

				document.getElementById('btn-sync').addEventListener('click', function () {
					syncEntity(entityType, entityId);
				});

				logAction('Widget initialized for ' + entityType + ' #' + entityId, 'info');

				refreshProductHash(entityType, entityId)
					.then(function () {
						return syncEntity(entityType, entityId);
					})
					.then(function () {
						// Register real-time listener for product row changes.
						BX24.addCustomEvent('onPullEvent', function (event) {
							// Expecting event structure: {module: 'crm', command: 'productrow', data: {ownerId: <entityId>, ...}}
							if (event && event.module === 'crm' && event.command === 'productrow') {
								var data = event.data || {};
								if (data.ownerId == entityId) {
									// Product rows for this entity changed – trigger sync.
									logAction('Push event detected, syncing...', 'info');
									syncEntity(entityType, entityId);
								}
							}
						});
					});
			});
		}
	};
})();
