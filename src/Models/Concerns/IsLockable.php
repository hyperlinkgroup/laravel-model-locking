<?php

namespace Hylk\Locking\Models\Concerns;

use Carbon\Carbon;
use Hylk\Locking\Exceptions\InvalidUserException;
use Hylk\Locking\Exceptions\ModelIsLockedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

/**
 * @property-read int|string|null $locked_by
 * @property-read Carbon|null $locked_at
 * @property-read bool $is_locked
 * @property-read bool $is_unlocked
 * @property-read Authenticatable $isLockedByUser
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
	 * @throws ModelIsLockedException if the model is already locked.
	 */
	public function lock(Authenticatable|string|int|null $user = null, bool $save = true): self
	{
		if ($this->isLocked() && !$this->isLockedBy($user)) {
			throw new ModelIsLockedException('The model is already locked by another user.');
		}
		$this->attributes['locked_by'] = $this->lockingUserIdentifier($user);
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
		return $this->releaseLockIfExpired()->attributes['locked_by'] ?? null;
	}

	public function getLockedAtAttribute(): Carbon|null
	{
		$lockedAt = $this->releaseLockIfExpired()->attributes['locked_at'] ?? null;

		return $lockedAt ? new Carbon($lockedAt) : null;
	}

	public function getIsLockedAttribute(): bool
	{
		return $this->isLocked(false);
	}

	public function getIsUnlockedAttribute(): bool
	{
		return $this->isUnlocked(false);
	}

	/**
	 * Returns if the model is locked.
	 * Releases the lock if the lock is expired.
	 *
	 * @param Authenticatable|string|int|null $user null for the current user
	 * @param bool                            $saveOnRelease
	 *
	 * @return bool
	 * @throws InvalidUserException
	 */
	public function isLockedBy(Authenticatable|string|int|null $user = null, bool $saveOnRelease = false): bool
	{
		$userId = $this->lockingUserIdentifier($user);

		return (string) $this->releaseLockIfExpired($saveOnRelease)->locked_by === (string) $userId;
	}
	
	public function isLockedByUser(): BelongsTo
	{
		$emptyUser = new (config('auth.providers.users.model',  User::class))();

		return $this->belongsTo($emptyUser::class, 'locked_by', $emptyUser->getKeyName());
	}

	/**
	 * Returns if the model is locked.
	 * Releases the lock if the lock is expired.
	 *
	 * @param bool $saveOnRelease
	 *
	 * @return bool
	 */
	public function isLocked(bool $saveOnRelease = false): bool
	{
		return (bool) $this->releaseLockIfExpired($saveOnRelease)->locked_by;
	}

	/**
	 * Returns if the model is unlocked.
	 * Releases the lock if the lock is expired.
	 *
	 * @param bool $saveOnRelease
	 *
	 * @return bool
	 */
	public function isUnlocked(bool $saveOnRelease = false): bool
	{
		return !$this->isLocked($saveOnRelease);
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

	/**
	 * If the lock is expired the model will be unlocked.
	 *
	 * @param bool $saveOnRelease
	 *
	 * @return IsLockable|Model|self
	 */
	protected function releaseLockIfExpired(bool $saveOnRelease = false): self
	{
		if (empty($lockedAt = data_get($this->attributes, 'locked_at'))) return $this;
		// test on expiration
		$lockedAt = is_string($lockedAt) ? new Carbon($lockedAt) : $lockedAt->copy();
		$lockedAt->addSeconds(config('model-locking.lock_duration', 60));
		if ($lockedAt->isPast()) {
			return $this->unlockForced($saveOnRelease);
		}

		return $this;
	}
}