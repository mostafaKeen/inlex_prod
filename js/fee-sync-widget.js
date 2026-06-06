(function () {
	'use strict';

	var OWNER_TYPE_MAP = { lead: 'L', deal: 'D' };
	var isSyncing = false;
	var productRows = [];
	var catalogProducts = [];
	var costTypePropCode = null;
	var entityType = '';
	var entityId = 0;

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

	function logAction(message, type) {
		var el = document.getElementById('sync-log');
		if (!el) return;
		var entry = document.createElement('div');
		entry.style.padding = '2px 0';
		entry.style.borderBottom = '1px solid #f2f2f2';
		entry.textContent = new Date().toLocaleTimeString() + ' — ' + message;
		el.insertBefore(entry, el.firstChild);
		while (el.children.length > 15) {
			el.removeChild(el.lastChild);
		}
	}

	function setStatus(text, type) {
		var el = document.getElementById('sync-status');
		if (!el) return;
		el.textContent = text;
		el.className = 'status-' + (type || 'info');
	}

	// Fetch CRM product rows
	function fetchProductRows() {
		var ownerType = OWNER_TYPE_MAP[entityType];
		return callMethod('crm.item.productrow.list', {
			filter: { '=ownerType': ownerType, '=ownerId': entityId }
		}).then(function (data) {
			productRows = data.productRows || [];
			return productRows;
		});
	}

	// Discover property code for "Type of Cost"
	function discoverProperties() {
		return callMethod('catalog.productProperty.list', {
			select: ['id', 'name', 'code']
		}).then(function (res) {
			var props = res.productProperties || [];
			for (var i = 0; i < props.length; i++) {
				if (props[i].name.toLowerCase().indexOf('type of cost') !== -1 || props[i].name.toLowerCase().indexOf('cost type') !== -1) {
					costTypePropCode = 'property' + props[i].id;
					break;
				}
			}
		}).catch(function () {
			// Fallback if properties cannot be listed
			costTypePropCode = null;
		});
	}

	// Fetch Catalog Products
	function fetchCatalogProducts() {
		if (!costTypePropCode) {
			return callMethod('catalog.product.list', {
				select: ['id', 'name']
			}).then(function (res) {
				catalogProducts = res.products || [];
			}).catch(function() {});
		}

		return callMethod('catalog.product.list', {
			select: ['id', 'name', costTypePropCode]
		}).then(function (res) {
			catalogProducts = res.products || [];
		}).catch(function () {
			// fallback without property select
			return callMethod('catalog.product.list', {
				select: ['id', 'name']
			}).then(function (res) {
				catalogProducts = res.products || [];
			});
		});
	}

	function getCostTypeForProduct(productId) {
		if (!productId) return 'Professional Cost';
		var prod = catalogProducts.find(function (p) { return p.id == productId; });
		if (prod && costTypePropCode && prod[costTypePropCode]) {
			var val = prod[costTypePropCode];
			if (typeof val === 'object' && val.value) return val.value;
			return val;
		}
		return 'Professional Cost';
	}

	// Render product table rows
	function renderTable() {
		var tbody = document.getElementById('product-rows-body');
		if (!tbody) return;
		tbody.innerHTML = '';

		if (productRows.length === 0) {
			var emptyRow = document.createElement('tr');
			emptyRow.innerHTML = '<td colspan="9" style="text-align: center; color: #a8adb2; padding: 20px;">No products added. Click "Add product" to start.</td>';
			tbody.appendChild(emptyRow);
			updateTotals();
			return;
		}

		productRows.forEach(function (row, idx) {
			var tr = document.createElement('tr');
			tr.dataset.index = idx;

			// Row headers & drag handle
			var dragTd = document.createElement('td');
			dragTd.className = 'row-number';
			dragTd.innerHTML = '<span class="drag-handle">☰</span> ' + (idx + 1) + '.';
			tr.appendChild(dragTd);

			// Product Name input or selector
			var nameTd = document.createElement('td');
			var nameInput = document.createElement('select');
			nameInput.className = 'input-bx';
			nameInput.innerHTML = '<option value="">-- Select Product --</option>';
			catalogProducts.forEach(function (p) {
				var selected = (row.productId == p.id || (!row.productId && row.productName === p.name)) ? 'selected' : '';
				nameInput.innerHTML += '<option value="' + p.id + '" ' + selected + '>' + p.name + '</option>';
			});
			// If not in catalog list but has name
			if (row.productName && !catalogProducts.some(function(p) { return p.id == row.productId; })) {
				nameInput.innerHTML += '<option value="custom" selected>' + row.productName + ' (Custom)</option>';
			}
			
			nameInput.addEventListener('change', function (e) {
				var val = e.target.value;
				if (val === 'custom') return;
				var prod = catalogProducts.find(function (p) { return p.id == val; });
				if (prod) {
					row.productId = prod.id;
					row.productName = prod.name;
					var costType = getCostTypeForProduct(prod.id);
					row.costType = costType;
					var costSelect = tr.querySelector('.cost-type-select');
					if (costSelect) costSelect.value = costType;
				} else {
					row.productId = 0;
					row.productName = '';
				}
				recalcRowAmount(tr, row);
				updateTotals();
			});
			nameTd.appendChild(nameInput);
			tr.appendChild(nameTd);

			// Image thumbnail placeholder
			var imgTd = document.createElement('td');
			imgTd.innerHTML = '<div class="img-placeholder"><svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/><path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/></svg></div>';
			tr.appendChild(imgTd);

			// Type of Cost Select
			var costTd = document.createElement('td');
			var costSelect = document.createElement('select');
			costSelect.className = 'input-bx select-bx cost-type-select';
			costSelect.innerHTML = '<option value="Government Cost">Government Cost</option>' +
				'<option value="Professional Cost">Professional Cost</option>';
			costSelect.value = row.costType || getCostTypeForProduct(row.productId);
			costSelect.addEventListener('change', function (e) {
				row.costType = e.target.value;
			});
			costTd.appendChild(costSelect);
			tr.appendChild(costTd);

			// Price input
			var priceTd = document.createElement('td');
			var priceWrapper = document.createElement('div');
			priceWrapper.className = 'input-bx-wrapper';
			var priceInput = document.createElement('input');
			priceInput.type = 'number';
			priceInput.step = '0.01';
			priceInput.className = 'input-bx input-bx-with-suffix';
			priceInput.value = parseFloat(row.price || 0).toFixed(2);
			priceInput.addEventListener('input', function (e) {
				row.price = parseFloat(e.target.value || 0);
				recalcRowAmount(tr, row);
				updateTotals();
			});
			priceWrapper.appendChild(priceInput);
			priceWrapper.innerHTML += '<span class="input-bx-suffix">Dh</span>';
			priceTd.appendChild(priceWrapper);
			tr.appendChild(priceTd);

			// Payments (Quantity) input
			var qtyTd = document.createElement('td');
			var qtyWrapper = document.createElement('div');
			qtyWrapper.className = 'input-bx-wrapper';
			var qtyInput = document.createElement('input');
			qtyInput.type = 'number';
			qtyInput.step = '1';
			qtyInput.className = 'input-bx input-bx-with-suffix';
			qtyInput.value = parseInt(row.quantity || 1);
			qtyInput.addEventListener('input', function (e) {
				row.quantity = parseInt(e.target.value || 1);
				recalcRowAmount(tr, row);
				updateTotals();
			});
			qtyWrapper.appendChild(qtyInput);
			qtyWrapper.innerHTML += '<span class="input-bx-suffix">m</span>';
			qtyTd.appendChild(qtyWrapper);
			tr.appendChild(qtyTd);

			// Tax dropdown
			var taxTd = document.createElement('td');
			var taxSelect = document.createElement('select');
			taxSelect.className = 'input-bx select-bx';
			taxSelect.innerHTML = '<option value="0">No Tax</option><option value="5">VAT 5%</option>';
			taxTd.appendChild(taxSelect);
			tr.appendChild(taxTd);

			// Calculated Amount
			var amtTd = document.createElement('td');
			var amtWrapper = document.createElement('div');
			amtWrapper.className = 'input-bx-wrapper';
			var amtInput = document.createElement('input');
			amtInput.type = 'text';
			amtInput.className = 'input-bx input-bx-with-suffix';
			amtInput.disabled = true;
			amtInput.style.backgroundColor = '#f5f7f8';
			amtInput.value = parseFloat((row.price || 0) * (row.quantity || 1)).toFixed(2);
			amtWrapper.appendChild(amtInput);
			amtWrapper.innerHTML += '<span class="input-bx-suffix">Dh</span>';
			amtTd.appendChild(amtWrapper);
			tr.appendChild(amtTd);

			// Delete button
			var delTd = document.createElement('td');
			var delBtn = document.createElement('button');
			delBtn.className = 'btn-delete';
			delBtn.innerHTML = '<svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>';
			delBtn.addEventListener('click', function () {
				productRows.splice(idx, 1);
				renderTable();
			});
			delTd.appendChild(delBtn);
			tr.appendChild(delTd);

			tbody.appendChild(tr);
		});

		updateTotals();
	}

	function recalcRowAmount(rowEl, rowData) {
		var inputs = rowEl.querySelectorAll('input');
		if (inputs.length >= 3) {
			var price = parseFloat(inputs[0].value || 0);
			var qty = parseInt(inputs[1].value || 1);
			inputs[2].value = (price * qty).toFixed(2);
		}
	}

	function updateTotals() {
		var sum = 0;
		productRows.forEach(function (row) {
			sum += (row.price || 0) * (row.quantity || 1);
		});

		var formatted = sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		
		document.getElementById('total-raw').textContent = 'Dh' + formatted;
		document.getElementById('total-before-tax').textContent = 'Dh' + formatted;
		document.getElementById('total-amount').textContent = 'Dh' + formatted;
	}

	// Save modified product rows back to Bitrix24
	function saveProductRows() {
		if (isSyncing) return;
		isSyncing = true;
		setStatus('Saving to CRM...', 'info');

		var ownerType = OWNER_TYPE_MAP[entityType];
		
		// Map for saving
		var rowsToSave = productRows.map(function (row) {
			return {
				productId: row.productId || 0,
				productName: row.productName,
				price: row.price || 0,
				quantity: row.quantity || 1,
				sort: row.sort || 10
			};
		});

		callMethod('crm.item.productrow.set', {
			ownerType: ownerType,
			ownerId: entityId,
			productRows: rowsToSave
		}).then(function (res) {
			logAction('Products saved to CRM successfully', 'success');
			setStatus('Synchronizing SPAs...', 'info');
			
			// Trigger backend sync to update Professional / Government SPAs
			return fetch('api/sync.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'sync',
					entityType: entityType,
					entityId: entityId
				})
			});
		}).then(function (response) { return response.json(); })
		.then(function (result) {
			if (result.success) {
				setStatus('Saved & Synced', 'success');
				logAction('SPA synchronized: ' + (result.actions || []).length + ' actions', 'success');
			} else {
				setStatus('Sync warning', 'warning');
				(result.errors || []).forEach(function (e) { logAction(e, 'warning'); });
			}
		}).catch(function (err) {
			var msg = err.message || String(err);
			setStatus('Error occurred', 'danger');
			logAction('Error: ' + msg, 'danger');
		}).finally(function () {
			isSyncing = false;
		});
	}

	window.FeeSyncWidget = {
		init: function (type, id) {
			entityType = type;
			entityId = id;

			BX24.init(function () {
				BX24.fitWindow();

				// Add product button handler
				document.getElementById('btn-add-product').addEventListener('click', function () {
					var defaultProduct = catalogProducts[0] || { id: 0, name: 'New Product' };
					productRows.push({
						productId: defaultProduct.id,
						productName: defaultProduct.name,
						price: 0,
						quantity: 1,
						sort: 10,
						costType: 'Professional Cost'
					});
					renderTable();
				});

				// Select product button handler
				document.getElementById('btn-select-product').addEventListener('click', function () {
					var defaultProduct = catalogProducts[0] || { id: 0, name: 'New Product' };
					productRows.push({
						productId: defaultProduct.id,
						productName: defaultProduct.name,
						price: 0,
						quantity: 1,
						sort: 10,
						costType: 'Professional Cost'
					});
					renderTable();
				});

				// Save button handler
				document.getElementById('btn-save').addEventListener('click', function () {
					saveProductRows();
				});

				setStatus('Loading...', 'info');

				// Fetch properties, products catalog, then current entity rows
				discoverProperties()
					.then(fetchCatalogProducts)
					.then(fetchProductRows)
					.then(function () {
						renderTable();
						setStatus('Loaded & Ready', 'success');
						logAction('Widget initialized successfully', 'info');

						// Real-time listener for external changes
						BX24.addCustomEvent('onPullEvent', function (event) {
							if (event && event.module === 'crm' && event.command === 'productrow') {
								var data = event.data || {};
								if (data.ownerId == entityId && !isSyncing) {
									logAction('Products updated externally. Reloading...', 'info');
									fetchProductRows().then(renderTable);
								}
							}
						});
					}).catch(function (err) {
						var msg = err.message || String(err);
						setStatus('Initialization failed', 'danger');
						logAction('Init Error: ' + msg, 'danger');
					});
			});
		}
	};
})();
