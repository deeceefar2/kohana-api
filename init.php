<?php
/**
 * Extra HTTP Status Code Messages
 */
Response::$messages[102] = 'Processing';
Response::$messages[207] = 'Multi-Status';
Response::$messages[422] = 'Unprocessable Entity';
Response::$messages[423] = 'Locked';
Response::$messages[424] = 'Failed Dependency';
Response::$messages[507] = 'Insufficient Storage';

/**
 * Routes
 */

	Route::set('api', 'api(/<format>)(/<controller>(/<id>(/<custom>)))(.<extension>)',
		array(
			'id'		=> '[\w]{8}-[\w]{4}-[\w]{4}-[\w]{4}-[\w]{12}|[0-9]*|[\w]{40}',
			'format'	=> 'json|xml',
			'extension'	=> 'json|xml',
		)
	)->defaults(array(
		'directory'	=> 'api',
		'action'	=> 'index',
		'id'		=> FALSE,
		'format'	=> FALSE,
		'custom'	=> FALSE,
		'extension'	=> FALSE,
	));
