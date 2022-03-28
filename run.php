<?php
	require 'vendor/autoload.php';

	use xPaw\SourceQuery\SourceQuery;
	use PHPMinecraft\MinecraftQuery\MinecraftQueryResolver;

    if (!function_exists('str_contains')) {
        function str_contains($haystack, $needle) {
            return strpos($haystack, $needle) !== false;
        }
    }

	function format($string, $kvPairs) {
		foreach ($kvPairs as $key => $value) {
			$string = str_replace('{' . $key . '}', $value, $string);
		}

		return $string;
	}

	function hasActiveAlarm($address, $port) {
		global $ongoingAlarms;

		return in_array( $address . ':' . $port, $ongoingAlarms );
	}

	function setAlarm($address, $port) {
		global $ongoingAlarms;

		if (!hasActiveAlarm($address, $port)) {
			$ongoingAlarms[] = $address . ':' . $port;
		}

		sendDiscordStatus($address, $port, STATUS_DOWN);
	}

	function unsetAlarm($address, $port) {
		global $ongoingAlarms;

		foreach ($ongoingAlarms as $index => $value) {
			if ($value == $address . ':' . $port) {
				unset($ongoingAlarms[ $index ]);
			}
		}

		$ongoingAlarms = array_values($ongoingAlarms);

		sendDiscordStatus($address, $port, STATUS_UP);
	}

	function sendDiscordStatus($address, $port, $status) {
		global $discordWebhookUrl, $activeGame, $interval;

		if (!$discordWebhookUrl) { return; }

		$request = curl_init();

		curl_setopt_array($request, [
			CURLOPT_URL				=> $discordWebhookUrl,
			CURLOPT_POST			=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_HTTPHEADER		=> [ 'Content-Type: application/json' ],
			CURLOPT_POSTFIELDS		=> json_encode([
				'content'		=> '@here A server ' . ($status == STATUS_UP ? 'was' : 'is') . ' down and is now ' . ($status == STATUS_UP ? 'back up!' : 'restarting...'),
				'embeds'		=> [
					[
						'description' => 'The <:' . $activeGame['icons']['discord'] . '> **' . $activeGame['realName'] . '** server at **' . $address . ':' . $port . '** is currently **' . ($status ? 'back online' : 'down') . '** and will be tested again ' . ($status == STATUS_UP ? 'every' : 'in') . ' **' . $interval . ' seconds**.',
						'color'		  =>
							$status == STATUS_UP
								? HEX_COLOR_GREEN
								: HEX_COLOR_RED
					]
				]
			])
		]);

		$response = curl_exec($request);

		$httpStatus = curl_getinfo($request, CURLINFO_HTTP_CODE);

		$success = $httpStatus >= 200 && $httpStatus <= 204;

		if (!$success) {
			print 'WARN: Failed to send Discord message: ' . $response . PHP_EOL;
		}

		return $success;
	}

	define( 'SUPPORTED_GAMES' , [
		'hl1'		=> [
            'realName'      => 'Half-Life 1',
            'processFilter' => 'grep hlds | grep -v grep | cut -d p -f 1',
            'defaultPort'   => 27015,
			'icons'			=> [
				'discord' => 'hl1:849034010134315038'
			]
        ],
		'minecraft'	=> [
            'realName'      => 'Minecraft',
            'processFilter' => 'grep java | grep -v grep | grep {jarFile} | cut -d p -f 1',
            'defaultPort'   => 25565,
			'icons'			=> [
				'discord' => 'minecraft:849034461890740294'
			]
        ]
	]);

	// Checks whether we're running on a Unix-like OS or not
	define( 'IS_LINUX' , str_contains(PHP_OS, 'Linux') );

    define( 'TCP_MIN_PORT', 1     );
    define( 'TCP_MAX_PORT', 65535 );

	define( 'SQ_ENGINE',      SourceQuery::GOLDSOURCE );

	define( 'STATUS_UP',  	true  );
	define( 'STATUS_DOWN' , false );

	define( 'HEX_COLOR_GREEN' , 65280 	 );
	define( 'HEX_COLOR_RED'	  , 16711680 );

	$arguments = getopt('', [ 'game:', 'server:', 'jarfile::', 'timeout::', 'interval::', 'discord-webhook-url::' ]);

	foreach ([ 'timeout', 'interval' ] as $key) {
		if ( isset($arguments[ $key ]) && !empty($arguments[ $key ]) ) {
			if (!is_numeric($arguments[ $key ]) || $arguments[ $key ] < 1) {
				print 'The value for "' . $key . '" must be a number greater than 0.' . PHP_EOL;

				exit(1);
			}
		} else {
			$arguments[ $key ] = '';
		}
	}

	// Expressed as seconds
	$timeout =
		is_numeric($arguments['timeout']) && $arguments['timeout'] > 0
			? $arguments['timeout']
			: 30;

	// Expressed as seconds
	$interval =
		is_numeric($arguments['interval']) && $arguments['interval'] > 0
			? $arguments['interval']
			: 10;

	$discordWebhookUrl =
		isset($arguments['discord-webhook-url']) && !empty($arguments['discord-webhook-url'])
			? $arguments['discord-webhook-url']
			: '';

    if (!isset($arguments['game'])) {
        $arguments['game'] = '';
    }

	$arguments['game'] = strtolower($arguments['game']); // enforce lowercase

    if ($arguments['game'] == 'minecraft') {
		if (
			!isset( $arguments['jarfile'] )
			||
			empty( $arguments['jarfile'] )
		) {
			print 'The selected game requires the argument "jarfile".' . PHP_EOL;

			exit(1);
		}
    } else {
        $arguments['jarfile'] = '';
    }

	if (
		empty($arguments['server']) || empty($arguments['game'])
		||
		!in_array($arguments['game'], array_keys(SUPPORTED_GAMES))
	) {
		print
			'Usage:' . PHP_EOL .
			PHP_EOL  .
			'php ' 	 . $argv[0] .
			' --game=' . implode('|', array_keys(SUPPORTED_GAMES)) .
			' --server=127.0.0.1'			 .
			' [--server=192.168.0.25:27021]' . 
            ' [--jarfile=paper-1.18.1.jar]'  .
			' [--timeout=30]'				 .
			' [--interval=30]'				 .
			' [--discord-webhook-url]'		 .
            PHP_EOL;

		exit(1);
	}

	if (!is_array($arguments['server'])) {
		$arguments['server'] = [ $arguments['server'] ]; // normalize the data structure
	}

	foreach ($arguments['server'] as $index => $address) {
		$ipPort = explode(':', $address);

		if (
			!isset($ipPort[0]) && empty($ipPort[0])
			||
			filter_var($ipPort[0], FILTER_VALIDATE_IP)	   === false
			||
			filter_var($ipPort[0], FILTER_VALIDATE_DOMAIN) === false
		) {
			print 'The server address provided at position ' . ($index + 1) . ' (' . $ipPort[0] . ') is invalid (it should be an IPv4 or IPv6 address, or a hostname).' . PHP_EOL;

			exit(1);
		}

        $activeGame = SUPPORTED_GAMES[ $arguments['game'] ];

        if (
            !isset($ipPort[1])
            ||
            (
                empty($ipPort[1])
                &&
                $ipPort[1] !== '0'
            )
        ) {
            $arguments['server'][ $index ] = $ipPort[0] . ':' . $activeGame['defaultPort'];
        } else if (
            !is_numeric($ipPort[1])
            ||
            $ipPort[1] > TCP_MAX_PORT
            ||
            $ipPort[1] < TCP_MIN_PORT
        ) {
            print 'The port defined for the server address provided at position ' . ($index + 1) . ' (' . $ipPort[1] . ') is invalid (it should be an integer between ' . TCP_MIN_PORT . ' and ' . TCP_MAX_PORT . ').' . PHP_EOL;

            exit(1);
        }
	}

	if (!IS_LINUX) {
		print 'WARN: Automatic server restarts won\'t work as we aren\'t running on a Linux (or similar) system.' . PHP_EOL;
	}

	$ongoingAlarms = [];

    print
        'Monitoring will start now!' . PHP_EOL .
        PHP_EOL .
        'GAME: '     		 . $activeGame['realName']               					 	  . PHP_EOL .
        'SERVERS: '  		 . implode(', ', $arguments['server'])   					 	  . PHP_EOL .
		'TIMEOUT: '	 		 . $timeout . ' seconds'				 					 	  . PHP_EOL .
		'INTERVAL: ' 		 . $interval . ' seconds'		 		 					 	  . PHP_EOL .
		'JARFILE: '  		 . ($arguments['jarfile'] ? $arguments['jarfile'] : 'None'	   )  . PHP_EOL .
		'DISCORD WEBHOOK: '  . ($discordWebhookUrl 	  ? 'Enabled'			  : 'Disabled' )  . PHP_EOL .
        PHP_EOL;

	while (true) {
		foreach ($arguments['server'] as $address) {
			try {
				$address = explode(':', $address);

				$port    = $address[1];
				$address = $address[0];

				print 'Probing server at address ' . $address . ':' . $port . '... ';

				switch ($arguments['game']) {
					case 'hl1':
                        $query = new SourceQuery();
						$query->Connect( $address, $port, $timeout, SQ_ENGINE );
						$query->GetInfo(); // won't be using the returned info, it's just to make sure the server actually replies

						// print_r( $query->GetPlayers( ) );
						// print_r( $query->GetRules( ) );

						break;
					case 'minecraft':
						$resolver = new MinecraftQueryResolver($address, $port, (int) $timeout);

						$result = $resolver->getResult($tryOldQueryProtocolPre17 = true);

						break;
				}

				if (hasActiveAlarm($address, $port)) {
					unsetAlarm($address, $port);
				}

				print 'OK';
			} catch( Exception $e ) {
				print 'FAIL: ' . $e->getMessage() . PHP_EOL;

				if (!hasActiveAlarm($address, $port)) {
					setAlarm($address, $port);
				}

				if (IS_LINUX) {
					print 'Trying to restart server... ';

                    $command = 'ps -ax | ';

                    if ( $arguments['game'] != 'minecraft' ) {
                        $command .= 'grep ' . $port . ' | ';
                    }

                    $command .= $activeGame['processFilter'];

                    $command = format($command, [ 'jarFile' => $arguments['jarfile'] ]);

					$pids = trim( shell_exec($command) );

					if (empty($pids)) {
						print 'FAIL: No such process, try manual restart.';
					} else {
						foreach (explode(PHP_EOL, $pids) as $pid) {
							shell_exec('kill ' . trim($pid));
						}

						print 'OK';
					}
				}
			} finally {
                if ( $arguments['game'] == 'hl1' ) {
    				$query->Disconnect();
                }
			}

			print PHP_EOL;
		}

		print 'Testing again in ' . $interval . ' seconds...' . PHP_EOL;

		sleep($interval);
	}
?>
