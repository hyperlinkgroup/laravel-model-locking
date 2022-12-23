<?php

namespace Hylk\Locking\Http\Api\Controllers;

use Illuminate\Http\JsonResponse;

class HeartbeatController
{
	public function __invoke(): JsonResponse
	{
		return response()->json([
			'status' => 'ok',
		]);
	}
}