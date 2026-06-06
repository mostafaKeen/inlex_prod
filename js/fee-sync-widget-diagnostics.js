/**
 * Fee Sync Widget - Diagnostic Helper
 * Add this script to your HTML to debug property loading issues
 * 
 * Usage: Call FeeSyncDiagnostics.dumpState() in browser console
 */

var FeeSyncDiagnostics = (function () {

  /**
   * Dump current widget state to console
   */
  function dumpState() {
    console.group('🔍 Fee Sync Widget State Dump');
    
    // Check if FeeSyncWidget is loaded
    if (typeof FeeSyncWidget === 'undefined') {
      console.error('❌ FeeSyncWidget not loaded');
      console.groupEnd();
      return;
    }

    console.log('✓ FeeSyncWidget loaded');

    // Try to access state (may not be accessible - internal closure)
    console.log('Note: Internal state is not directly accessible (closure)');
    console.log('But we can inspect the DOM and logged values');

    dumpCatalog();
    dumpRows();
    dumpPropertyMappings();
    
    console.groupEnd();
  }

  /**
   * Dump catalog products with their properties
   */
  function dumpCatalog() {
    console.group('📦 Catalog Products');
    
    // Try to find catalog in sync-log or reconstruct from DOM selects
    var productSelects = document.querySelectorAll('.js-product-select option');
    var catalog = {};

    productSelects.forEach(function (opt) {
      if (opt.value) {
        catalog[opt.value] = opt.textContent;
      }
    });

    if (Object.keys(catalog).length === 0) {
      console.warn('⚠️  No products found in selects');
    } else {
      console.table(catalog);
    }

    console.groupEnd();
  }

  /**
   * Dump current row data from DOM
   */
  function dumpRows() {
    console.group('📋 Current Rows (from DOM)');
    
    var rows = [];
    document.querySelectorAll('#product-rows-body tr').forEach(function (tr, idx) {
      var rowId = tr.getAttribute('data-row-id');
      var productSel = tr.querySelector('.js-product-select');
      var productName = tr.querySelector('.js-product-name');
      var tocSel = tr.querySelector('.js-type-of-cost');
      var paymentsSel = tr.querySelector('.js-payments');
      var priceInput = tr.querySelector('.js-price');
      var taxInput = tr.querySelector('.js-tax');

      rows.push({
        'Row #': idx + 1,
        'ID': rowId,
        'Product Value': productSel ? productSel.value : '—',
        'Product Name': productName ? productName.value : '—',
        'Type of Cost': tocSel ? tocSel.value : '—',
        'Payments': paymentsSel ? paymentsSel.value : '—',
        'Price': priceInput ? priceInput.value : '—',
        'Tax %': taxInput ? taxInput.value : '—'
      });
    });

    if (rows.length === 0) {
      console.warn('⚠️  No rows found');
    } else {
      console.table(rows);
    }

    console.groupEnd();
  }

  /**
   * Show property option values and their IDs
   */
  function dumpPropertyMappings() {
    console.group('🔗 Property Enum Mappings');
    
    console.group('Type of Cost Enum');
    console.log('207 = Government Cost');
    console.log('209 = Professional Fees');
    console.groupEnd();

    console.group('Payments Enum');
    console.log('193 = Annually');
    console.log('195 = One Time');
    console.log('197 = Quarterly');
    console.log('199 = Every 2 years');
    console.log('201 = Monthly');
    console.log('203 = In The Order Of Discussion');
    console.log('205 = One time (variable)');
    console.groupEnd();

    console.groupEnd();
  }

  /**
   * Check if a specific property value is being rendered correctly
   */
  function inspectPropertyRendering(propertyId, expectedValue) {
    console.group('🔎 Inspecting Property Rendering');
    console.log('Looking for property:', propertyId);
    console.log('Expected value:', expectedValue);

    // Find all selects with this data attribute or class
    var relatedSelects = document.querySelectorAll('[data-property="' + propertyId + '"]');
    console.log('Found ' + relatedSelects.length + ' selects with this property');

    if (relatedSelects.length > 0) {
      relatedSelects.forEach(function (sel, idx) {
        console.group('Select #' + (idx + 1));
        console.log('Current value:', sel.value);
        console.log('Selected option text:', sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : 'none');
        
        // Check all available options
        var optionMatches = [];
        for (var i = 0; i < sel.options.length; i++) {
          if (sel.options[i].value === String(expectedValue)) {
            optionMatches.push(i);
          }
        }
        console.log('Options matching value "' + expectedValue + '":', optionMatches);
        console.groupEnd();
      });
    }

    console.groupEnd();
  }

  /**
   * Analyze sync-log for property values
   */
  function analyzeSyncLog() {
    console.group('📝 Analyzing Sync Log');
    
    var syncLog = document.getElementById('sync-log');
    if (!syncLog) {
      console.warn('Sync log element not found');
      console.groupEnd();
      return;
    }

    var logText = syncLog.innerText || syncLog.textContent;
    var lines = logText.split('\n').filter(function (l) { return l.trim().length > 0; });

    console.log('Recent log entries:');
    lines.slice(0, 20).forEach(function (line) {
      console.log('  ' + line);
    });

    // Look for property-related entries
    var propertyLines = lines.filter(function (l) {
      return l.indexOf('typeOfCost') !== -1 || 
             l.indexOf('payments') !== -1 ||
             l.indexOf('PROPERTY_') !== -1;
    });

    if (propertyLines.length > 0) {
      console.group('Property-related log entries');
      propertyLines.forEach(function (line) {
        console.log('  ' + line);
      });
      console.groupEnd();
    }

    console.groupEnd();
  }

  /**
   * Test enum value matching logic
   */
  function testValueMatching(testValue) {
    console.group('🧪 Testing Value Matching Logic');
    console.log('Test value:', testValue);
    console.log('Test value (string):', String(testValue));
    console.log('Test value (trimmed):', String(testValue || '').trim());

    var enumValues = {
      '207': 'Government Cost',
      '209': 'Professional Fees',
      '193': 'Annually',
      '195': 'One Time'
    };

    Object.keys(enumValues).forEach(function (id) {
      var label = enumValues[id];
      var idMatch = String(id).trim() === String(testValue || '').trim();
      var labelMatch = label.toLowerCase() === String(testValue || '').toLowerCase();
      
      console.log(id + ' (' + label + '): idMatch=' + idMatch + ', labelMatch=' + labelMatch);
    });

    console.groupEnd();
  }

  /**
   * Quick health check
   */
  function healthCheck() {
    console.group('🏥 Widget Health Check');
    
    var checks = {
      'Widget loaded': typeof FeeSyncWidget !== 'undefined',
      'Entity selector visible': !!document.getElementById('select-entity'),
      'Editor content exists': !!document.getElementById('editor-content'),
      'Product table exists': !!document.getElementById('product-rows-body'),
      'Sync log exists': !!document.getElementById('sync-log'),
      'Bitrix24 API available': typeof BX24 !== 'undefined'
    };

    Object.keys(checks).forEach(function (check) {
      var status = checks[check] ? '✓' : '✗';
      console.log(status + ' ' + check);
    });

    console.groupEnd();
  }

  /**
   * Export current state for analysis
   */
  function exportState() {
    console.group('💾 Exporting State');
    
    var stateExport = {
      timestamp: new Date().toISOString(),
      rows: [],
      logs: document.getElementById('sync-log') ? document.getElementById('sync-log').innerText : '',
      status: document.getElementById('sync-status') ? document.getElementById('sync-status').innerText : ''
    };

    // Collect row data
    document.querySelectorAll('#product-rows-body tr').forEach(function (tr) {
      var rowData = {
        product_value: tr.querySelector('.js-product-select') ? tr.querySelector('.js-product-select').value : '',
        product_name: tr.querySelector('.js-product-name') ? tr.querySelector('.js-product-name').value : '',
        type_of_cost_value: tr.querySelector('.js-type-of-cost') ? tr.querySelector('.js-type-of-cost').value : '',
        payments_value: tr.querySelector('.js-payments') ? tr.querySelector('.js-payments').value : '',
        price: tr.querySelector('.js-price') ? tr.querySelector('.js-price').value : '',
        tax: tr.querySelector('.js-tax') ? tr.querySelector('.js-tax').value : ''
      };
      stateExport.rows.push(rowData);
    });

    console.log(JSON.stringify(stateExport, null, 2));
    console.groupEnd();

    return stateExport;
  }

  // ─── Public API ──────────────────────────────────────────────────────────
  return {
    dumpState:               dumpState,
    dumpCatalog:             dumpCatalog,
    dumpRows:                dumpRows,
    dumpPropertyMappings:    dumpPropertyMappings,
    inspectPropertyRendering: inspectPropertyRendering,
    analyzeSyncLog:          analyzeSyncLog,
    testValueMatching:       testValueMatching,
    healthCheck:             healthCheck,
    exportState:             exportState
  };

})();

// ─── Usage Instructions ─────────────────────────────────────────────────
// 
// Open browser console (F12 → Console tab) and run:
//
// 1. Quick health check:
//    FeeSyncDiagnostics.healthCheck()
//
// 2. Dump all current state:
//    FeeSyncDiagnostics.dumpState()
//
// 3. Test value matching (replace 'TEST' with your value):
//    FeeSyncDiagnostics.testValueMatching('209')
//
// 4. Analyze sync log:
//    FeeSyncDiagnostics.analyzeSyncLog()
//
// 5. Export state for sharing:
//    var exported = FeeSyncDiagnostics.exportState()
//    console.log(JSON.stringify(exported, null, 2))
//
// ────────────────────────────────────────────────────────────────────────