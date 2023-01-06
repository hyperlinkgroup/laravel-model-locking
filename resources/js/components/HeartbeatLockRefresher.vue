<template>
	<span v-if="false"></span>
</template>

<script>

export default {
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

	data() {
		return {
			identifier: this.heartbeatManager.generateId(32),
		};
	},

	mounted() {
		const refreshCallback = () => {
			this.identifier = this.heartbeatManager.generateId(32);
			this.heartbeatManager.registerForRefresh(this.modelClass, this.modelId, this.identifier, refreshCallback);
		};

		this.heartbeatManager.registerForLock(this.modelClass, this.modelId, this.identifier, refreshCallback);
	},

	unmounted() {
		this.heartbeatManager.unlock(this.modelClass, this.modelId, this.identifier, () => {
			this.heartbeatManager.removeListener(this.identifier);
		});
	},
};
</script>