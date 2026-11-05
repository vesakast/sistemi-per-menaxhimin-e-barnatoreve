/* Optional: counts the dashboard numbers up when the page loads.
   It only touches ".stat-card strong" and skips values it can't read safely. */
document.addEventListener('DOMContentLoaded', function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    document.querySelectorAll('.stat-card strong').forEach(function (el) {
        var raw = el.textContent.trim();
        var match = raw.match(/\d+(\.\d+)?/);
        if (!match) return;

        var numText = match[0];
        var decimals = (numText.split('.')[1] || '').length;
        var target = parseFloat(numText);
        if (!isFinite(target) || target === 0) return;

        var template = raw.replace(numText, '\u0000');
        var duration = 1000;
        var start = null;

        function frame(now) {
            if (start === null) start = now;
            var p = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            var value = (target * eased).toFixed(decimals);
            el.textContent = template.replace('\u0000', value);
            if (p < 1) requestAnimationFrame(frame);
        }

        el.textContent = template.replace('\u0000', (0).toFixed(decimals));
        requestAnimationFrame(frame);
    });
});
