// Make togglePw globally accessible
window.togglePw = function(id, btn){
    const input = document.getElementById(id);
    const isPw = input.type === 'password';
    input.type = isPw ? 'text' : 'password';
}

// Password strength and validation
const pwd = document.getElementById('password');
const pwdConfirm = document.getElementById('password_confirmation');
const bar = document.getElementById('pwdStrength');
const requirements = document.getElementById('pwdRequirements');
const matchMsg = document.getElementById('pwdMatch');

const checks = {
    length: (p) => p.length >= 8,
    upper: (p) => /[A-Z]/.test(p),
    lower: (p) => /[a-z]/.test(p),
    number: (p) => /\d/.test(p),
    symbol: (p) => /[^\w\s]/.test(p)
};

function updateRequirement(id, met) {
    const icon = document.getElementById(id);
    if (!icon) return;

    if (met) {
        icon.setAttribute('data-lucide', 'check-circle-2');
        icon.setAttribute('class', 'w-3 h-3 text-success');
    } else {
        icon.setAttribute('data-lucide', 'circle');
        icon.setAttribute('class', 'w-3 h-3 text-base-content/40');
    }
    window.lucide && window.lucide.createIcons();
}

function checkStrength(p) {
    if (!p) return 0;

    let score = 0;
    const results = {};

    Object.keys(checks).forEach(key => {
        results[key] = checks[key](p);
        if (results[key]) score++;
    });

    // Update individual requirements
    updateRequirement('req-length', results.length);
    updateRequirement('req-upper', results.upper);
    updateRequirement('req-lower', results.lower);
    updateRequirement('req-number', results.number);
    updateRequirement('req-symbol', results.symbol);

    return score;
}

function renderStrength(score, password) {
    if (!password || password.length === 0) {
        bar.style.width = '0%';
        bar.className = 'h-full transition-all duration-300';
        requirements.classList.add('hidden');
        return;
    }

    requirements.classList.remove('hidden');

    const widths = ['0%', '20%', '40%', '60%', '80%', '100%'];
    const colors = ['bg-error', 'bg-error', 'bg-warning', 'bg-warning', 'bg-success', 'bg-success'];

    bar.style.width = widths[score];
    bar.className = 'h-full ' + colors[score] + ' transition-all duration-300';
}

function checkPasswordMatch() {
    if (!pwdConfirm.value) {
        matchMsg.classList.add('hidden');
        return;
    }

    const matches = pwd.value === pwdConfirm.value;
    matchMsg.classList.remove('hidden');

    if (matches) {
        matchMsg.textContent = 'Passwords match';
        matchMsg.className = 'text-xs mt-1 text-success';
    } else {
        matchMsg.textContent = 'Passwords do not match';
        matchMsg.className = 'text-xs mt-1 text-error';
    }
}

if (pwd && bar) {
    pwd.addEventListener('input', (e) => {
        const score = checkStrength(e.target.value);
        renderStrength(score, e.target.value);
        checkPasswordMatch();
    });
}

if (pwdConfirm) {
    pwdConfirm.addEventListener('input', checkPasswordMatch);
}
