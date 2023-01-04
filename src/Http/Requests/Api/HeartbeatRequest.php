<?php

namespace Hylk\Locking\Http\Requests\Api;

use Hylk\Locking\Rules\IdOrUuidRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
		return [
			'heartbeats' => 'present|array',
			'heartbeats.*.lockable_type' => 'required|string',
			'heartbeats.*.request_type' => 'required|in:lock,refresh,status,unlock',
			'heartbeats.*' => Rule::forEach(function($heartbeat, $attribute) {
				return match (data_get($heartbeat, 'request_type')) {
					'lock' => [
						'lockable_id' => 'required',
						new IdOrUuidRule,
					],
					'refresh', 'unlock' => [
						'lockable_id' => ['required', new IdOrUuidRule],
						'lock_id'     => 'required|int',
					],
					'status' => [
						'lockable_ids'   => 'present|array',
						'lockable_ids.*' => ['required', new IdOrUuidRule],
					],
					default => [],
				};
			}),
		];
    }
}
