<?php
require_once (__DIR__.'/crest.php');

$install_result = CRest::installApp();

$baseUrl = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http') . '://'
	. $_SERVER['SERVER_NAME']
	. (in_array($_SERVER['SERVER_PORT'], ['80', '443'], true) ? '' : ':' . $_SERVER['SERVER_PORT'])
	. str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);

$placements = [
	[
		'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
		'HANDLER'   => $baseUrl . '/placement-fee-sync.php',
		'TITLE'     => 'Fee SPA Sync',
	],
	[
		'PLACEMENT' => 'CRM_LEAD_DETAIL_TAB',
		'HANDLER'   => $baseUrl . '/placement-fee-sync.php',
		'TITLE'     => 'Fee SPA Sync',
	],
];

foreach ($placements as $placement) {
	$result = CRest::call('placement.bind', $placement);
	CRest::setLog(['placement' => $placement['PLACEMENT'], 'result' => $result], 'installation');
}

$handlerUrl = $baseUrl . '/handler.php';
$events = [
	'ONCRMDEALUPDATE',
	'ONCRMDEALADD',
	'ONCRMLEADUPDATE',
	'ONCRMLEADADD',
	'ONCRMDYNAMICITEMUPDATE',
];

foreach ($events as $event) {
	$result = CRest::call('event.bind', [
		'event'   => $event,
		'handler' => $handlerUrl,
	]);
	CRest::setLog(['event' => $event, 'result' => $result], 'installation');
}

if($install_result['rest_only'] === false):?>
<head>
	<script src="//api.bitrix24.com/api/v1/"></script>
	<?if($install_result['install'] == true):?>
	<script>
		BX24.init(function(){
			BX24.installFinish();
		});
	</script>
	<?endif;?>
</head>
<body>
	<?if($install_result['install'] == true):?>
		installation has been finished
	<?else:?>
		installation error
	<?endif;?>
</body>
<?endif;
