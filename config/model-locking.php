<?php

return [

	/*
	 * The duration of the lock in seconds.
	 * It should be 10 seconds longer than the heartbeat-refresh-intervall
	 */
	'lock_duration' => 70,

	'intervals' => [
		/*
		 * Time in seconds to refresh the lock.
		 * Should be a multiple of the heartbeat-status-interval
		 */
		'heartbeat_refresh' => 60,
		/*
		 * Time to request the status of the lock in seconds.
		 * It's used to request the state in index-routes to show the lock-status.
		 */
		'heartbeat_status' => 15,
	],
];