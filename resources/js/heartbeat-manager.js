/*
	Do not change this file directly.
	It's published by the hylk/laravel-model-locking package.

	Checkout and forge the package, change it there an request an update by PR.
 */
import Vue from 'vue';

const HeartbeatManager = {
	HeartbeatEventBus: new Vue(),

	intervals: {
		heartbeat_refresh: (process.env.MIX_HEARTBEAT_REFRESH ?? 60) * 1000,
		heartbeat_status: (process.env.MIX_HEARTBEAT_STATUS ?? 15) * 1000,
	},

	heartbeats: {},

	listeners: {},

	beat: {
		handle: null,
		counter: 0,
		skipModulo: 1,
	},

	init() {
		if (!window.hasOwnProperty('axios')) {
			throw new Error('\naxios is needed for the heartbeat and expected at window.axios\n\'Vue.use(HeartbeatManager);\' must be called after deining axios.\n');
		}

		// if we status and reresh requests, decide how often we only send status requests
		if (this.intervals.heartbeat_status < this.intervals.heartbeat_refresh) {
			this.beat.skipModulo = Math.floor(this.intervals.heartbeat_refresh / this.intervals.heartbeat_status);
		}

		if (this.hasHeartbeats()) this.scheduleBeat();
	},

	triggerBeat(scheduleBeat = true) {
		this.beat.counter = 1;
		if (scheduleBeat) {
			clearTimeout(this.beat.handle);
			this.beat.handle = null;
			this.scheduleBeat();
		}

		return axios.post('/api/locking/heartbeat', this.heartbeatData())
			.then(response => this.handleHeartbeatResponse(response.data))
			.catch(error => {
				throw new Error(error);
			});
	},

	timeout() {
		return this.hasStatusHeartbeats() ? this.intervals.heartbeat_status : this.intervals.heartbeat_refresh;
	},

	scheduleBeat() {
		if (!this.hasHeartbeats()) return;
		if (this.beat.handle) return;

		const heartbeatRequest = () => {
			++this.beat.counter;
			this.triggerBeat(false).finally(() => {
				this.beat.handle = setTimeout(heartbeatRequest, this.timeout());
			});
		};

		this.beat.handle = setTimeout(heartbeatRequest, this.timeout());
	},

	heartbeatData() {
		const mode = this.hasStatusHeartbeats() && this.beatCounter % this.beat.skipModulo !== 0 ? 'status' : 'all';
		const heartbeatsData = [];

		for (const [modelClass, heartbeats] of Object.entries(this.heartbeats)) {
			const lockableStatusIds = [];
			heartbeats.forEach(heartbeat => {
				// for the status-requests, we collect the ids for a batch request
				if (heartbeat.type === 'status') {
					lockableStatusIds.push(heartbeat.id);
				} else if (mode !== 'status') {
					// other requests we do in single request
					heartbeatsData.push({
						lockable_type: modelClass,
						lockable_id: heartbeat.id,
						request_type: heartbeat.type,
					});
				}
			});
			// add the status request if needed
			if (lockableStatusIds.length) {
				heartbeatsData.push({
					lockable_type: modelClass,
					lockable_ids: lockableStatusIds,
					request_type: 'status',
				});
			}
		}

		return { heartbeats: heartbeatsData };
	},

	handleHeartbeatResponse(responseData) {
		responseData.heartbeats.forEach(heartbeatData => {
			this.listeners[heartbeatData.lockable_type].forEach(listener => {
				if (String(listener.id) === String(heartbeatData.lockable_id)) {
					// if it's not a status request remove the listener
					const removeListener = (this.heartbeats[heartbeatData.lockable_type].find(heartbeat => heartbeat.id === String(heartbeatData.lockable_id) && heartbeat.type !== 'status'));
					if (removeListener) this.removeListener(listener.identifier);

					this.HeartbeatEventBus.$emit('state_refreshed_' + listener.identifier, heartbeatData);
					if (removeListener) this.HeartbeatEventBus.$off('state_refreshed_' + listener.identifier);
				}
			});
		});
	},

	registerListener(modelClass, modelId, identifier, callback, type = 'status') {
		if (!this.listeners.hasOwnProperty(modelClass)) this.listeners[modelClass] = [];
		this.listeners[modelClass].push({
			id: String(modelId),
			identifier: identifier,
		});

		if (!this.heartbeatExists(modelClass, modelId)) {
			if (!this.heartbeats.hasOwnProperty(modelClass)) this.heartbeats[modelClass] = [];
			this.heartbeats[modelClass].push({
				id: String(modelId),
				type: type,
			});
		}

		this.HeartbeatEventBus.$on('state_refreshed_' + identifier, callback);

		this.scheduleBeat();
	},

	registerForLock(modelClass, modelId, identifier, callback) {
		this.registerListener(modelClass, modelId, identifier, callback, 'lock');
		this.triggerBeat();
	},
	unlock(modelClass, modelId, identifier, callback) {
		this.registerListener(modelClass, modelId, identifier, callback, 'unlock');
		this.triggerBeat();
	},
	registerForRefresh(modelClass, modelId, identifier, callback) {
		this.registerListener(modelClass, modelId, identifier, callback, 'refresh');
	},

	removeListener(identifier) {
		let modelClass;
		let modelId;

		// delete the listener
		for (const [modelClassIt, listeners] of Object.entries(this.listeners)) {
			const listener = listeners.find(listener => listener?.identifier === identifier);
			if (!listener) continue;

			modelClass = modelClassIt;
			modelId = listener.id;

			const listenerIndex = this.listeners[modelClass].indexOf(listener);
			this.listeners[modelClass].splice(listenerIndex, 1);

			break;
		}

		// test if there are other listeners for this model
		if (!this.listeners[modelClass].length || !this.listeners[modelClass].find(listener => listener?.id === String(modelId))) {
			const hadStatusRequestsBefore = this.hasStatusHeartbeats();
			// remove the heartbeat
			const heartbeat = this.heartbeats[modelClass].find(heartbeat => heartbeat?.id === String(modelId));
			const heartbeatIndex = this.heartbeats[modelClass].indexOf(heartbeat);
			this.heartbeats[modelClass].splice(heartbeatIndex, 1);

			if (!this.hasHeartbeats()) {
				clearTimeout(this.beat.handle);
				this.beat.handle = null;
			}

			// if there are no status-requests anymore, we trigger a heartbeat to make sure we do not lose a lock
			if (hadStatusRequestsBefore && !this.hasStatusHeartbeats() && this.hasHeartbeats()) {
				this.triggerBeat();
			}
		}
	},

	heartbeatExists(modelClass, id) {
		return this.heartbeats.hasOwnProperty(modelClass) && !!this.heartbeats[modelClass].find(heartbeat => heartbeat.id === String(id));
	},

	hasHeartbeats() {
		if (Object.keys(this.heartbeats).length === 0) return false;

		for (const keyValue of Object.entries(this.heartbeats)) {
			if (keyValue[1].length) return true;
		}

		return false;
	},

	hasStatusHeartbeats() {
		if (Object.keys(this.heartbeats).length === 0) return false;

		for (const keyValue of Object.entries(this.heartbeats)) {
			if (keyValue[1].find(heartbeat => heartbeat?.type === 'status')) return true;
		}

		return false;
	},
};

export default {
	install: Vue => {
		Vue.prototype.heartbeatManager = {
			registerForLock(modelClass, modelId, identifier, callback) {
				HeartbeatManager.registerForLock(modelClass, modelId, identifier, callback);
			},
			registerForRefresh(modelClass, modelId, identifier, callback) {
				HeartbeatManager.registerForRefresh(modelClass, modelId, identifier, callback);
			},
			unlock(modelClass, modelId, identifier, callback) {
				HeartbeatManager.unlock(modelClass, modelId, identifier, callback);
			},
			registerListener(modelClass, modelId, identifier, callback) {
				HeartbeatManager.registerListener(modelClass, modelId, identifier, callback);
			},
			removeListener(identifier) {
				HeartbeatManager.removeListener(identifier);
			},
			generateId(length) {
				let result = '';
				const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
				for (let i = 0; i < length; ++i) {
					result += characters.charAt(Math.floor(Math.random() * characters.length));
				}

				return result;
			},
			triggerHeartbeat() {
				HeartbeatManager.triggerBeat();
			},
		};
		HeartbeatManager.init();
	},
};