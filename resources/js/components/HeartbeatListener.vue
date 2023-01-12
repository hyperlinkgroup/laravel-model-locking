<template>
	<span v-if="false"></span>
</template>

<script>
/*
	Do not change this file directly.
	It's published by the hylk/laravel-model-locking package.

	Checkout and forge the package, change it there and request an update by PR.
 */
export default {
	name: 'HeartbeatListener',

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

	data() {
		return {
			identifier: this.heartbeatManager.generateId(10),
		};
	},

	emits: ['locked', 'unlocked'],

	mounted() {
		this.heartbeatManager.registerListener(this.modelClass, this.modelId, this.identifier, lockData => {
			if (lockData.locked_by.name) return this.$emit('locked', lockData);

			return this.$emit('unlocked', lockData);
		});
	},

	beforeDestroy() {
		this.heartbeatManager.removeListener(this.identifier);
	},
};
</script>
