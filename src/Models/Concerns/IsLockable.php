<?php

namespace Hylk\Locking\Models\Concerns;

use Carbon\Carbon;
use Hylk\Locking\Exceptions\InvalidUserException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

/**
 * @property-read int|string|null $locked_by
 * @property-read Carbon|null $locked_at
 * @property-read bool $is_locked
 * @property-read bool $is_unlocked
 */
trait IsLockable
{
	/**
	 * Initialize the IsLockable-trait for an instance.
	 *
	 * @return void
	 */
	public function initializeIsLockable()
	{
		// Füge die CustomProperties dem Fillable-Array hinzu.
		$this->appends[] = 'locked_by';
		$this->appends[] = 'locked_at';
	}

	/**
	 * Locks model.
	 *
	 * @param Authenticatable|string|int|null $user
	 * @param bool                            $save
	 *
	 * @return IsLockable|Model
	 * @throws InvalidUserException if the user is not valid.
	 */
	public function lock(Authenticatable|string|int|null $user = null, bool $save = true): self
	{
		$userId = $this->lockingUserIdentifier($user);
		$this->attributes['locked_by'] = $userId;
		$this->attributes['locked_at'] = Carbon::now();

		if ($save) $this->save();

		return $this;
	}

	/**
	 * Unlocks a model and tests if it is locked by the given user.
	 *
	 * @param Authenticatable|string|int|null $user
	 * @param bool                            $save
	 *
	 * @return IsLockable|Model
	 * @throws InvalidUserException if the user is not valid.
	 * @throws InvalidUserException if the model is locked by another user.
	 */
	public function unlock(Authenticatable|string|int|null $user = null, bool $save = true): self
	{
		if (!$this->isLockedBy($user)) {
			throw new InvalidUserException('The model is locked by another user.');
		}

		return $this->unlockForced($save);
	}

	/**
	 * Unlocks a model.
	 *
	 * @param bool $save
	 *
	 * @return IsLockable|Model
	 */
	public function unlockForced(bool $save = true): self
	{
		$this->attributes['locked_by'] = null;
		$this->attributes['locked_at'] = null;

		if ($save) $this->save();

		return $this;
	}

	public function getLockedByAttribute(): int|string|null
	{
		return $this->attributes['locked_by'] ?? null;
	}

	public function getLockedAtAttribute(): Carbon|null
	{
		$lockedAt = $this->attributes['locked_at'] ?? null;

		return $lockedAt ? new Carbon($lockedAt) : null;
	}

	public function getIsLockedAttribute(): bool
	{
		return $this->isLocked();
	}

	public function getIsUnlockedAttribute(): bool
	{
		return $this->isUnlocked();
	}

	/**
	 * Returns if the model is locked.
	 *
	 * @param Authenticatable|string|int|null $user null for the current user
	 *
	 * @return bool
	 * @throws InvalidUserException
	 */
	public function isLockedBy(Authenticatable|string|int|null $user = null): bool
	{
		$userId = $this->lockingUserIdentifier($user);
		return $this->locked_by === $userId;
	}

	/**
	 * Returns if the model is locked.
	 *
	 * @return bool
	 */
	public function isLocked(): bool
	{
		return (bool) $this->locked_by;
	}

	/**
	 * Returns if the model is unlocked.
	 *
	 * @return bool
	 */
	public function isUnlocked(): bool
	{
		return !$this->isLocked();
	}

	/**
	 * Returns the user-identifier for the handed user or the currently locked in.
	 *
	 * @param Authenticatable|string|int|null $user
	 *
	 * @return int|string
	 * @throws InvalidUserException if the user is not valid.
	 */
	private function lockingUserIdentifier(Authenticatable|string|int|null $user): int|string
	{
		// get the user for an identifier or null
		if (is_string($user) || is_int($user)) $user = (config('auth.providers.users.model',  User::class))::find($user);
		if (!$user) $user = Auth::user();

		// get the identifier for the user
		if ($user instanceof Authenticatable) return $user->getAuthIdentifier();

		throw new InvalidUserException('Invalid locking user');
	}
}