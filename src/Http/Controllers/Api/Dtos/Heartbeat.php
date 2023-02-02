<?php

namespace Hylk\Locking\Http\Controllers\Api\Dtos;

use Hylk\Locking\Exceptions\InvalidUserException;
use Hylk\Locking\Exceptions\ModelIsLockedException;
use Hylk\Locking\Models\Concerns\IsLockable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Heartbeat
{
	private ?string $lock_id;
	private string $lockable_type;
	private array $lockable_ids;
	private string $request_type;
	/**
	 * @var Collection<Model&IsLockable>
	 */
	private ?Collection $lockables = null;
	private HeartbeatCollection $collection;

	public function __construct(array $attributes, $heartbeatCollection)
	{
		$this->lockable_type = data_get($attributes, 'lockable_type');
		$this->request_type = data_get($attributes, 'request_type');
		$this->lock_id = data_get($attributes, 'lock_id');

		$ids = array_filter([data_get($attributes, 'lockable_id')]);
		if (!count($ids)) $ids = data_get($attributes, 'lockable_ids', []);
		$this->lockable_ids = $ids;

		$this->collection = $heartbeatCollection;
	}

	public function collection(): HeartbeatCollection
	{
		return $this->collection;
	}

	/**
	 * lockable
	 *
	 * @param Collection<Model&IsLockable>|null $lockable
	 *
	 * @return Collection<Model&IsLockable>|null
	 */
	public function lockables(Collection $lockable = null): ?Collection
	{
		if (!$this->lockables) $this->lockables = $lockable;
		if (!$this->lockables && $this->collection()) {
			$this->lockables = $this->collection()->lockables($this->lockable_type, $this->lockable_ids);
		}

		return $this->lockables;
	}

	public function requestType(): string
	{
		return $this->request_type;
	}

	public function lockId(): string|int|null
	{
		if(is_numeric($this->lock_id)) return (int) $this->lock_id;

		return $this->lock_id;
	}

	public function lockableType(): string
	{
		return $this->lockable_type;
	}

	public function lockableIds(): array
	{
		return $this->lockable_ids;
	}

	/**
	 * handle
	 *
	 * @return $this
	 * @throws InvalidUserException
	 * @throws ModelIsLockedException
	 */
	public function handle(): self
	{
		/** @var Model&IsLockable $lockables */
		$lockables = $this->lockables();
		match ($this->requestType()) {
			'lock', 'refresh' => $lockables->each(fn($lockable) => $lockable->lock()),
			'unlock' => $lockables->each(fn($lockable) => $lockable->unlock()),
			default => [],
		};

		return $this;
	}

	public function modelStates()
	{
		return $this->lockables()->map(function($lockable) {
			/** @var Model&IsLockable $lockable */
			return [
				'lockable_id' => $lockable->{$lockable::lockableIdField()},
				'lockable_type' => $lockable::class,
				'locked_at' => $lockable->locked_at,
				'locked_by' => $lockable->locked_by,
			];
		});
	}
}
