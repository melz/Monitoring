<?php

return new \Phalcon\Config(array(
	'database' => array(
		'adapter'  => 'Mysql',
		'host'     => '127.0.0.1',
		'username' => 'root',
		'password' => '',
		'name'     => 'monitoring',
	),
	'application' => array(
		'controllersDir' => __DIR__ . '/../../app/controllers/',
		'modelsDir'      => __DIR__ . '/../../app/models/',
		'viewsDir'       => __DIR__ . '/../../app/views/',
        'formsDir'       => __DIR__ . '/../../app/forms/',
        'helpersDir'     => __DIR__ . '/../../app/helpers/',
		'baseUri'        => '/',
	),
    'postmark' => array(
        'key'      => '', // You can get a free Postmark key at www.postmarkapp.com
        'from'     => 'you@yourdomain.com'
    ),
    'probes' => array(
        'key'      => 'PUT_YOUR_KEY_HERE', // Anything will do
        'interval' => 5, // Minutes
        'hosts'    => array(
            'ams' => '', // host name => IP
            'sfo' => '',
            'nyc' => '',
            'sgp' => ''
        ),
    ),
    'url' => 'http://178.62.56.146'
));
