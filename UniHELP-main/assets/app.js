/* ============================================================
   UniHELP — app.js
   Vanilla JS for all interactive UI behaviour
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Mobile navbar toggle ────────────────────────────── */
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', () => links.classList.toggle('open'));
        document.addEventListener('click', e => {
            if (!toggle.contains(e.target) && !links.contains(e.target)) {
                links.classList.remove('open');
            }
        });
    }

    /* ── Auto-dismiss flash messages ────────────────────── */
    const flash = document.getElementById('flashMsg');
    if (flash) setTimeout(() => flash.style.opacity = '0', 4000);

    /* ── OTP / 2FA input auto-advance ───────────────────── */
    const otpInputs = document.querySelectorAll('.otp-input');
    otpInputs.forEach((input, i) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 1);
            input.classList.toggle('filled', input.value !== '');
            if (input.value && i < otpInputs.length - 1) {
                otpInputs[i + 1].focus();
            }
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !input.value && i > 0) {
                otpInputs[i - 1].focus();
            }
        });
        input.addEventListener('paste', e => {
            const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
            e.preventDefault();
            [...text.slice(0, 6)].forEach((ch, j) => {
                if (otpInputs[i + j]) {
                    otpInputs[i + j].value = ch;
                    otpInputs[i + j].classList.add('filled');
                }
            });
            const next = Math.min(i + text.length, otpInputs.length - 1);
            otpInputs[next].focus();
        });
    });

    /* ── Collect OTP value into hidden field ─────────────── */
    const otpForm = document.getElementById('otpForm');
    if (otpForm) {
        otpForm.addEventListener('submit', () => {
            const code = [...otpInputs].map(i => i.value).join('');
            document.getElementById('otpValue').value = code;
        });
    }

    /* ── Availability grid toggle ────────────────────────── */
    document.querySelectorAll('.avail-slot').forEach(slot => {
        slot.addEventListener('click', () => {
            slot.classList.toggle('on');
            const hidden = document.getElementById(slot.dataset.hidden);
            if (hidden) hidden.value = slot.classList.contains('on') ? '1' : '0';
        });
    });

    /* ── Skill checkbox styling ──────────────────────────── */
    document.querySelectorAll('.skill-item input[type=checkbox]').forEach(cb => {
        const item = cb.closest('.skill-item');
        const sync = () => item.classList.toggle('checked', cb.checked);
        sync();
        cb.addEventListener('change', sync);
    });

    /* ── Mode toggle buttons ─────────────────────────────── */
    document.querySelectorAll('.mode-group').forEach(group => {
        const btns   = group.querySelectorAll('.mode-btn');
        const hidden = group.querySelector('input[type=hidden]');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                btns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (hidden) hidden.value = btn.dataset.value;
            });
        });
    });

    /* ── Star rating ──────────────────────────────────────── */
    const starGroup = document.querySelector('.star-rating');
    if (starGroup) {
        const stars    = starGroup.querySelectorAll('.star-btn');
        const labelEl  = starGroup.querySelector('.star-label');
        const hidden   = document.getElementById('ratingValue');
        const labels   = ['','Poor','Fair','Good','Very Good','Excellent'];
        let current    = parseInt(hidden?.value || '0');

        const paint = val => {
            stars.forEach((s, i) => {
                const fill = i < val ? '#F59E0B' : 'none';
                const stroke = i < val ? '#F59E0B' : '#CBD5E1';
                s.querySelector('svg').setAttribute('fill',   fill);
                s.querySelector('svg').setAttribute('stroke', stroke);
            });
            if (labelEl) labelEl.textContent = val > 0 ? labels[val] : '';
        };

        paint(current);

        stars.forEach((btn, i) => {
            btn.addEventListener('mouseenter', () => paint(i + 1));
            btn.addEventListener('mouseleave', () => paint(current));
            btn.addEventListener('click', () => {
                current = i + 1;
                if (hidden) hidden.value = current;
                paint(current);
            });
        });
    }

    /* ── Quick tags for feedback ──────────────────────────── */
    const tagBtns = document.querySelectorAll('.quick-tag');
    const commentBox = document.getElementById('feedbackComment');
    if (tagBtns.length && commentBox) {
        tagBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('selected');
                const val = btn.dataset.tag;
                const tags = (commentBox.value.match(/^Tags: (.+?)(\.|\n|$)/) || [])[1]?.split(', ') || [];
                if (btn.classList.contains('selected')) {
                    tags.push(val);
                } else {
                    const idx = tags.indexOf(val);
                    if (idx > -1) tags.splice(idx, 1);
                }
                const base = commentBox.value.replace(/^Tags: [^\n]+\n?/, '');
                commentBox.value = tags.length ? `Tags: ${tags.join(', ')}\n${base}` : base;
            });
        });
    }

    /* ── Generic modal helpers ───────────────────────────── */
    window.openModal = id => {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    };
    window.closeModal = id => {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    };

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => {
            if (e.target === m) m.style.display = 'none';
        });
    });

    /* ── AJAX: Accept / Reject request ──────────────────── */
    document.querySelectorAll('[data-action="accept"], [data-action="reject"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action    = btn.dataset.action;
            const requestId = btn.dataset.id;
            const csrf      = document.querySelector('meta[name=csrf]')?.content || '';

            btn.disabled = true;
            btn.textContent = '…';

            const res  = await fetch('ajax/handle_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, request_id: requestId, csrf }),
            });
            const data = await res.json();

            if (data.ok) {
                const card = btn.closest('.request-card');
                card.classList.add(action === 'accept' ? 'accepted' : 'rejected');
                const actions = card.querySelector('.card-actions');
                actions.innerHTML = action === 'accept'
                    ? `<span class="text-success font-bold">✓ Accepted</span>`
                    : `<span class="text-danger font-bold">✗ Declined</span>`;
            } else {
                alert(data.error || 'Something went wrong.');
                btn.disabled = false;
                btn.textContent = action === 'accept' ? 'Accept' : 'Reject';
            }
        });
    });

    /* ── AJAX: Mark session completed ────────────────────── */
    document.querySelectorAll('[data-action="complete"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const sessionId = btn.dataset.id;
            const csrf      = document.querySelector('meta[name=csrf]')?.content || '';

            const res  = await fetch('ajax/update_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'complete', session_id: sessionId, csrf }),
            });
            const data = await res.json();

            if (data.ok) {
                const card = btn.closest('.session-card');
                card.classList.add('completed');
                const actions = card.querySelector('.card-actions');
                actions.innerHTML = `<span class="text-success font-bold">✓ Completed</span>
                    <a href="feedback.php?session_id=${sessionId}" class="btn btn-outline btn-sm">⭐ Give Feedback</a>`;
            } else {
                alert(data.error || 'Could not update session.');
            }
        });
    });

    /* ── AJAX: Cancel session ────────────────────────────── */
    document.querySelectorAll('[data-action="cancel-session"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Cancel this session?')) return;
            const sessionId = btn.dataset.id;
            const csrf      = document.querySelector('meta[name=csrf]')?.content || '';

            const res  = await fetch('ajax/update_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancel', session_id: sessionId, csrf }),
            });
            const data = await res.json();

            if (data.ok) {
                const card = btn.closest('.session-card');
                card.classList.add('cancelled');
                card.querySelector('.card-actions').innerHTML =
                    `<span class="text-danger font-bold">✗ Cancelled</span>`;
            } else {
                alert(data.error || 'Could not cancel session.');
            }
        });
    });

    /* ── Volunteer card select ───────────────────────────── */
    document.querySelectorAll('.select-vol-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.vol-card').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('.select-vol-btn').forEach(b => {
                b.textContent = 'Select Volunteer';
                b.classList.replace('btn-success', 'btn-outline');
            });
            const card = btn.closest('.vol-card');
            card.classList.add('selected');
            btn.textContent = '✓ Selected';
            btn.classList.replace('btn-outline', 'btn-success');
            document.getElementById('selectedVolId').value = btn.dataset.volId;
        });
    });

    /* ── Char counter for textareas ──────────────────────── */
    document.querySelectorAll('textarea[data-maxlength]').forEach(ta => {
        const max     = parseInt(ta.dataset.maxlength);
        const counter = document.getElementById(ta.dataset.counter);
        const update  = () => {
            if (counter) counter.textContent = `${ta.value.length}/${max}`;
        };
        ta.addEventListener('input', update);
        update();
    });

    /* ── Password strength indicator ─────────────────────── */
    const pwInput   = document.getElementById('password');
    const pwStrength = document.getElementById('pwStrength');
    if (pwInput && pwStrength) {
        pwInput.addEventListener('input', () => {
            const v = pwInput.value;
            let score = 0;
            if (v.length >= 8)         score++;
            if (/[A-Z]/.test(v))       score++;
            if (/[0-9]/.test(v))       score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['', '#DC2626', '#D97706', '#2563EB', '#16A34A'];
            pwStrength.textContent  = v.length ? labels[score] : '';
            pwStrength.style.color  = colors[score];
        });
    }

    /* ── Volunteer filter (client-side) ──────────────────── */
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const inputs  = filterForm.querySelectorAll('select, input');
        const cards   = document.querySelectorAll('.vol-card');
        const doFilter = () => {
            const skill  = filterForm.querySelector('[name=skill]')?.value.toLowerCase()  || '';
            const mode   = filterForm.querySelector('[name=mode]')?.value.toLowerCase()   || '';
            let   shown  = 0;
            cards.forEach(card => {
                const cardSkill = (card.dataset.skill || '').toLowerCase();
                const cardMode  = (card.dataset.mode  || '').toLowerCase();
                const match = (!skill || cardSkill.includes(skill)) &&
                              (!mode  || cardMode.includes(mode));
                card.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            const counter = document.getElementById('volCount');
            if (counter) counter.textContent = shown;
        };
        inputs.forEach(i => i.addEventListener('change', doFilter));
    }

});
