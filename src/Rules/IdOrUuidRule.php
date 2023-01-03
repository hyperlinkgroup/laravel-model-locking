<?php

namespace Hylk\Locking\Rules;

use Illuminate\Contracts\Validation\InvokableRule;
use Ramsey\Uuid\Uuid;

class IdOrUuidRule implements InvokableRule
{
	/**
	 * Run the validation rule.
	 *
	 * @param  string  $attribute
	 * @param  mixed  $value
	 * @param  \Closure  $fail
	 * @return void
	 */
	public function __invoke($attribute, $value, $fail)
	{
		if (!Uuid::isValid($value) && !is_numeric($value)) {
			$fail('The :attribute must be a valid id or uuid.');
		}
	}
}
