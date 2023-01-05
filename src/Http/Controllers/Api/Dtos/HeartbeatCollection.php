<?php

namespace Hylk\Locking\Http\Controllers\Api\Dtos;

use Hylk\Locking\Models\Concerns\IsLockable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class HeartbeatCollection extends Collection
{
	private array $models = [];

	/** @inheritDoc */
	public function __construct($items = [])
	{
		parent::__construct([]);

		foreach($this->getArrayableItems($items) as $item) {
			$this->items[] = new Heartbeat($item, $this);
		}
	}

	/** @inheritDoc */
	public function add($item)
	{
		if(is_array($item)) $item = new Heartbeat($item, $this);

		return parent::add($item);
	}

	/**
	 * Returns the lockable Model for a class-string and an id.
	 *
	 * @param class-string<Model&IsLockable> $modelClass
	 * @param array                          $ids
	 *
	 * @return Collection<Model&IsLockable>
	 */
	public function lockables(string $modelClass, array $ids): Collection
	{
		if ($modelCollection = data_get($this->models, $modelClass)) {
			$emptyModel = new $modelClass();
			/** @var \Illuminate\Database\Eloquent\Collection $models */
			$models = $modelCollection->whereIn($emptyModel->getKeyName(), $ids);

			if ($models->count()) return $models;
		}

		return $modelClass::find($ids);
	}

	/**
	 * Handles the requested tasks by the heartbeats.
	 *
	 * @return HeartbeatCollection
	 * @throws \Hylk\Locking\Exceptions\InvalidUserException
	 * @throws \Hylk\Locking\Exceptions\ModelIsLockedException
	 */
	public function handle(): self
	{
		$modelsToFetch = [];
		// collect the ids and entities to fetch from the db
		$this->each(function (Heartbeat $heartbeat) use (&$modelsToFetch) {
			if(!array_key_exists($heartbeat->lockableType(), $modelsToFetch)) {
				$modelsToFetch[$heartbeat->lockableType()] = [];
			}

			$modelsToFetch[$heartbeat->lockableType()] = array_merge($heartbeat->lockableIds(), $modelsToFetch[$heartbeat->lockableType()]);
		});

		// fetch them with one query per entity
		/**
		 * @var  class-string<Model&IsLockable> $modelClass
		 * @var  array $ids
		 */
		foreach($modelsToFetch as $modelClass => $ids) {
			$this->models[$modelClass] = $modelClass::find($ids);
		}

		// handle the tasks
		$this->each(function (Heartbeat $heartbeat) {
			$heartbeat->handle();
		});

		return $this;
	}

	public function modelStates(): Collection
	{
		$this->each(function (Heartbeat $heartbeat) use (&$responseData) {
			$responseData = $responseData ? $responseData->merge($heartbeat->modelStates()) : $heartbeat->modelStates();
		});

		return $responseData;
	}
}