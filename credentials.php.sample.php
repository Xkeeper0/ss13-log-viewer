<?php

	/*
		Array of log servers to use
		servers [
			key [
				url: base URL (without "logs/full/"). use <auth> to inject credentials
				auth: (optional) which auth set to use
			]
		]
		auth [
			key :  HTTP authentication string ("username:password")
		]

	*/

	$config		= [
		'servers'	=> [
			'example'		=> [ 'url' => 'http://<auth>@example1.examplehub.com/', 'auth' => 'example' ],
			'example-rp'	=> [ 'url' => 'http://<auth>@example2.examplehub.com/', 'auth' => 'example' ],
			],

		'auth' => [
			'example'		=> 'admin:password',
			],
		];
