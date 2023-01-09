<template>
	<span v-if="false"></span>
</template>

<script>

export default {
	/*
	Do not change this file directly.
	It's published by the hylk/laravel-model-locking package.

	Checkout and forge the package, change it there an request an update by PR.
 */
	name: 'HeartbeatLockRefresher',

	props: {
		modelClass: {
			type: String,
			required: true,
		},
		modelId: {
			type: [Number, String],
			required: true,
		},
	},

	emits: ['lost'],

	data() {
		return {
			identifier: this.heartbeatManager.generateId(32),
		};
	},

	mounted() {
		const refreshCallback = lockStateData => {
			if (lockStateData.locked_by && !lockStateData.locked_by.is_current_user) {
				return this.$emit('lost');
			}

			this.identifier = this.heartbeatManager.generateId(32);
			this.heartbeatManager.registerForRefresh(this.modelClass, this.modelId, this.identifier, refreshCallback);
		};

		this.heartbeatManager.registerForLock(this.modelClass, this.modelId, this.identifier, refreshCallback);
	},

	beforeDestroy() {
		this.heartbeatManager.unlock(this.modelClass, this.modelId, this.identifier, () => {
			this.heartbeatManager.removeListener(this.identifier);
		});
	},
};
</script>