(() => {
    'use strict';

    const units = [
        { seconds: 86400, singular: 'Giorno', plural: 'Giorni' },
        { seconds: 3600, singular: 'Ora', plural: 'Ore' },
        { seconds: 60, singular: 'Minuto', plural: 'Minuti' },
        { seconds: 1, singular: 'Secondo', plural: 'Secondi' },
    ];

    function startCountdown(element) {
        const deadline = Number(element.dataset.deadline) * 1000;
        if (!element.dataset.deadline || !Number.isFinite(deadline)) {
            return;
        }

        const counters = units.map((unit) => {
            const part = document.createElement('span');
            const value = document.createElement('span');
            const label = document.createElement('span');

            part.className = 'countdown-evento-unit';
            value.className = 'countdown-evento-value';
            part.append(value, ' ', label);

            return { unit, part, value, label };
        });
        element.replaceChildren(...counters.map(({ part }) => part));

        function update() {
            const millisecondsLeft = Math.max(0, deadline - Date.now());
            let secondsLeft = Math.ceil(millisecondsLeft / 1000);

            counters.forEach(({ unit, value, label }) => {
                const amount = Math.floor(secondsLeft / unit.seconds);
                secondsLeft %= unit.seconds;
                value.textContent = String(amount).padStart(2, '0');
                label.textContent = amount === 1 ? unit.singular : unit.plural;
            });

            if (millisecondsLeft > 0) {
                window.setTimeout(update, millisecondsLeft % 1000 || 1000);
            }
        }

        update();
    }

    function init() {
        document.querySelectorAll('.countdown-evento[data-deadline]').forEach(startCountdown);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
