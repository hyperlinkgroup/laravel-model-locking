import Vue from 'vue';

const HeartbeatManager = {
	HeartbeatEventBus: new Vue(),

	heartbeats: {},

	listeners: {},

	init() {
		if (!window.hasOwnProperty('axios')) {
			throw new Error('\naxios is needed for the heartbeat and expected at window.axios\n\'Vue.use(HeartbeatManager);\' must be called after deining axios.\n');
		}
	},

	registerListener(modelClass, id, caller, callback) {
		if (!this.listeners.hasOwnProperty(modelClass)) this.listeners[modelClass] = [];
		this.listeners[modelClass].push({
			id: String(id),
			caller: caller,
		});

		if (!this.heartbeatExists(modelClass, id)) {
			if (!this.heartbeats.hasOwnProperty(modelClass)) this.heartbeats[modelClass] = [];
			this.heartbeats[modelClass].push({
				id: String(id),
				type: 'status',
			});
		}

		this.HeartbeatEventBus.$on('state_changed_', callback);

		setTimeout(() => {
			this.HeartbeatEventBus.$emit('state_changed_', { locked_by: { name: 'Sven' }, lockable_id: '14' });
			console.log('triggered');
		}, 2000);
	},

	heartbeatExists(modelClass, id) {
		return this.heartbeats.hasOwnProperty(modelClass) && this.heartbeats[modelClass].find(heartbeat => heartbeat.id === String(id));
	},
};

export default {
	install: Vue => {
		Vue.prototype.heartbeatManager = {
			registerLock(modelClass, id) {
				console.log(modelClass + ' ' + id);
			},
			registerRefresh(modelClass, id) {
				console.log(modelClass + ' ' + id);
			},
			unlock(modelClass, id) {
				console.log(modelClass + ' ' + id);
			},
			registerListener(modelClass, id, caller, callback) {
				HeartbeatManager.registerListener(modelClass, id, caller, callback);
				console.log('Listener for ' + modelClass + '::' + id + ' registered.');
			},
		};
		HeartbeatManager.init();
	},
};