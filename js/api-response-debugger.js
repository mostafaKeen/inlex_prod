/**
 * Bitrix24 API Response Debugger
 * This script intercepts API calls and logs their full responses
 * Add this BEFORE fee-sync-widget.js in your HTML
 */

// Intercept BX24.callMethod to log all responses
var originalCallMethod = BX24.callMethod;
var apiResponses = {};

BX24.callMethod = function(method, params, callback) {
  var callId = method + '_' + Date.now();
  
  return originalCallMethod.call(this, method, params, function(response) {
    // Log everything
    console.group('🔵 BX24 API CALL: ' + method);
    console.log('Params:', params);
    console.log('Full Response:', response);
    console.log('Response Data:', response.data ? response.data() : 'NO DATA');
    console.log('Has Error:', response.error ? response.error() : 'NO ERROR');
    console.groupEnd();
    
    // Store for later analysis
    apiResponses[callId] = {
      method: method,
      params: params,
      response: response,
      timestamp: new Date().toISOString()
    };
    
    // Call original callback
    if (typeof callback === 'function') {
      callback(response);
    }
  });
};

// Helper function to dump all responses
window.dumpApiResponses = function() {
  console.group('📊 ALL API RESPONSES');
  Object.keys(apiResponses).forEach(function(key) {
    var call = apiResponses[key];
    console.group(call.method);
    console.log('Data:', call.response.data ? call.response.data() : 'EMPTY');
    console.groupEnd();
  });
  console.groupEnd();
  
  // Also return as JSON for copying
  return apiResponses;
};

console.log('✓ API Debugger loaded. All BX24.callMethod calls will be logged to console.');
console.log('Tip: Run dumpApiResponses() to see all API responses at once');