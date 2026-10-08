(function () {
    const selectors = '.alert:not([role="status"])';
    const timers = new WeakMap();

    function processAlert(alert) {
        if (!(alert instanceof HTMLElement) || !alert.matches(selectors)) return;

        if (alert.classList.contains('d-none') || alert.hidden) {
            clearTimeout(timers.get(alert));
            timers.delete(alert);
            return;
        }

        if (timers.has(alert)) return;

        timers.set(alert, setTimeout(() => {
            alert.classList.add('d-none');
            timers.delete(alert);
        }, 15000));
    }

    function processNode(node) {
        if (!(node instanceof Element)) return;
        if (node.matches(selectors)) processAlert(node);
        node.querySelectorAll(selectors).forEach(processAlert);
    }

    document.querySelectorAll(selectors).forEach(processAlert);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes') {
                processAlert(mutation.target);
                return;
            }

            mutation.addedNodes.forEach(processNode);
        });
    });

    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['class', 'hidden'],
        childList: true,
        subtree: true,
    });
})();
