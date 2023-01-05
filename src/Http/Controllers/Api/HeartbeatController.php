<?php

namespace Hylk\Locking\Http\Controllers\Api;

use Hylk\Locking\Http\Controllers\Api\Dtos\HeartbeatCollection;
use Hylk\Locking\Http\Requests\Api\HeartbeatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HeartbeatController extends Controller
{
	public function __invoke(HeartbeatRequest $request): JsonResponse
	{
		return response()->json([
			'heartbeats' => (new HeartbeatCollection($request->input('heartbeats', [])))->handle()->modelStates(),
		]);
	}
}