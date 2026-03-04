<script setup>
import { ref } from 'vue';

defineProps({
	disabled: {
		type: Boolean,
		default: false,
	},
});

const emit = defineEmits(['voice-recorded']);

const recording = ref(false);
const recorder = ref(null);
const chunks = ref([]);
const stream = ref(null);

const startRecording = async () => {
	if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
		window.alert('Voice recording is not supported in this browser.');
		return;
	}

	stream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
	recorder.value = new MediaRecorder(stream.value);
	chunks.value = [];

	recorder.value.ondataavailable = (event) => {
		if (event.data?.size) chunks.value.push(event.data);
	};

	recorder.value.onstop = () => {
		const blob = new Blob(chunks.value, { type: recorder.value.mimeType || 'audio/webm' });
		emit('voice-recorded', blob);
		stream.value?.getTracks().forEach((track) => track.stop());
	};

	recorder.value.start();
	recording.value = true;
};

const stopRecording = () => {
	if (!recorder.value || recorder.value.state === 'inactive') return;
	recorder.value.stop();
	recording.value = false;
};
</script>

<template>
	<button
		class="btn btn-circle"
		type="button"
		:class="recording ? 'btn-error' : 'btn-outline'"
		:disabled="disabled"
		@click="recording ? stopRecording() : startRecording()"
	>
		{{ recording ? '■' : '🎤' }}
	</button>
</template>
