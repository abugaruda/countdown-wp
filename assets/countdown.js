(() => {
    'use strict';

    const units = [86400, 3600, 60, 1];
    const labels = {
        it: [['Giorno', 'Giorni'], ['Ora', 'Ore'], ['Minuto', 'Minuti'], ['Secondo', 'Secondi']],
        en: [['Day', 'Days'], ['Hour', 'Hours'], ['Minute', 'Minutes'], ['Second', 'Seconds']],
    };

    function startCountdown(element) {
        const deadline = Number(element.dataset.deadline) * 1000;
        if (!element.dataset.deadline || !Number.isFinite(deadline)) {
            return;
        }

        const unitLabels = element.lang === 'it' ? labels.it : labels.en;
        const counters = units.map((seconds, index) => {
            const part = document.createElement('span');
            const value = document.createElement('span');
            const label = document.createElement('span');

            part.className = 'countdown-evento-unit';
            value.className = 'countdown-evento-value';
            label.className = 'countdown-evento-label';
            part.append(value, ' ', label);

            const [singular, plural] = unitLabels[index];
            return { seconds, part, value, label, singular, plural };
        });
        element.replaceChildren(...counters.map(({ part }) => part));

        function update() {
            const millisecondsLeft = Math.max(0, deadline - Date.now());
            let secondsLeft = Math.ceil(millisecondsLeft / 1000);

            counters.forEach(({ seconds, value, label, singular, plural }) => {
                const amount = Math.floor(secondsLeft / seconds);
                secondsLeft %= seconds;
                value.textContent = String(amount).padStart(2, '0');
                label.textContent = amount === 1 ? singular : plural;
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
