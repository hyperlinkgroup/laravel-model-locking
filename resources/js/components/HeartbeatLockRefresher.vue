<template>
	<span v-if="false"></span>
</template>

<script>

export default {
	/*
	Do not change this file directly.
	It's published by the hylk/laravel-model-locking package.

	Checkout and forge the package, change it there and request an update by PR.
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
			confirmed: false,
		};
	},

	methods: {
		propagateLostLock() {
			const lockedBy = this.$page.props.locked?.locked_by?.name;
			const message = this.$page.props.locked?.model_msg_prefix + ' wurde von ***' + lockedBy + '*** übernommen. Ungespeicherte Änderungen sind ggf. verloren.';
			window.alert(message);

			return window.location = this.route(this.$page.props.locked?.redirect_route_name);
		},

		handleLockState() {
			// redirect if it's a claim call
			if (this.$page.url.includes('claimLock=')) return window.location = this.$page.url.split('?')[0];
			// ignore the logic if there is no lock
			if (!this.$page.props?.locked) return;

			if (this.route().params?.lostLock) return this.propagateLostLock();

			return this.handleLockedState();
		},

		handleLostLock() {
			if (this.route().params?.lostLock) return;
			const params = this.route().params;
			params.lostLock = true;

			return window.location = this.route(this.route().current(), params);
		},

		handleLockedState() {
			const lockedBy = this.$page.props.locked?.locked_by?.name;
			const message = this.$page.props.locked?.model_msg_prefix + ' ist aktuell durch ***' + lockedBy + '*** in Bearbeitung.\n\nMöchtest du die Bearbeitung erzwingen?\n\nUngespeicherte Inhalte des anderen Benutzers gehen dabei ggf. verloren.';

			// if the claim is not forced, the user is redirected redirect route
			if (window.confirm(message) !== true) {
				// this.$inertia.visit does not work here
				return window.location = this.route(this.$page.props.locked?.redirect_route_name);
			}

			const params = this.route().params;
			params.claimLock = true;

			return this.$inertia.visit(this.route(this.route().current(), params));
		},
	},

	mounted() {
		this.handleLockState();

		const refreshCallback = lockStateData => {
			if (this.confirmed && lockStateData.locked_by && !lockStateData.locked_by.is_current_user) {
				this.handleLostLock();

				return this.$emit('lost');
			} else if (lockStateData.locked_by && lockStateData.locked_by.is_current_user) {
				this.confirmed = true;
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
