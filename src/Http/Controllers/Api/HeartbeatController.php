<?php

namespace Hylk\Locking\Http\Controllers\Api;

use Hylk\Locking\Http\Requests\Api\HeartbeatRequest;
use Illuminate\Http\JsonResponse;

class HeartbeatController
{
	public function __invoke(HeartbeatRequest $request): JsonResponse
	{
		return response()->json([
			'status' => 'ok',
		]);
	}
}