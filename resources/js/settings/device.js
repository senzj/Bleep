const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const modal = document.getElementById('confirmModal');
const modalText = document.getElementById('confirmModalText');
const modalCancel = document.getElementById('confirmModalCancel');
const modalConfirm = document.getElementById('confirmModalConfirm');

let confirmAction = null;

function showModal(text, action) {
    modalText.textContent = text;
    modal.classList.remove('hidden');
    confirmAction = action;
}

modalCancel.onclick = function() {
    modal.classList.add('hidden');
    confirmAction = null;
};

modalConfirm.onclick = function() {
    if (confirmAction) confirmAction();
    modal.classList.add('hidden');
    confirmAction = null;
};

document.querySelectorAll('.revoke-session-btn').forEach(btn => {
    btn.onclick = function() {
        const sessionId = btn.dataset.sessionId;
        showModal('Log out this session?', function() {
            fetch(`/settings/devices/${sessionId}/revoke/session`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            }).then(() => location.reload());
        });
    }
});

document.querySelectorAll('.revoke-device-btn').forEach(btn => {
    btn.onclick = function() {
        const deviceId = btn.dataset.deviceId;
        showModal('Remove this device?', function() {
            fetch(`/settings/devices/${deviceId}/revoke/device`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            }).then(() => location.reload());
        });
    }
});
