(function () {
	'use strict';

	// ---- Ticker: wrap Text module content in a track and duplicate it ----
	function initTickers() {
		document.querySelectorAll('.sd-ticker').forEach(function (el) {
			if (el.dataset.sdTickerReady) return;
			el.dataset.sdTickerReady = '1';

			// Divi Text modules wrap content in .et_pb_text_inner; fall back to the element itself.
			var inner = el.querySelector('.et_pb_text_inner') || el;

			var item = document.createElement('div');
			item.className = 'sd-ticker__item';
			while (inner.firstChild) item.appendChild(inner.firstChild);

			var track = document.createElement('div');
			track.className = 'sd-ticker__track';
			track.appendChild(item);

			// Repeat until one copy is at least as wide as the container, then double for a seamless loop.
			inner.appendChild(track);
			var base = item.innerHTML;
			var guard = 0;
			while (item.offsetWidth < inner.offsetWidth && guard++ < 20) {
				item.insertAdjacentHTML('beforeend', base);
			}
			var clone = item.cloneNode(true);
			clone.setAttribute('aria-hidden', 'true');
			track.appendChild(clone);
		});
	}

	// ---- Count-up for [google_review_count animate="yes"] ----
	function animate(el) {
		var target = parseInt(el.dataset.sdCount, 10);
		if (!target) return;
		var prefix = el.textContent.match(/^\D*/)[0];
		var suffix = el.textContent.match(/\D*$/)[0];
		var start = null, duration = 1500;
		var fmt = new Intl.NumberFormat(document.documentElement.lang || undefined);
		function step(ts) {
			if (!start) start = ts;
			var p = Math.min((ts - start) / duration, 1);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = prefix + fmt.format(Math.round(target * eased)) + suffix;
			if (p < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	function initCounters() {
		var els = document.querySelectorAll('[data-sd-count]');
		if (!els.length) return;
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) {
					animate(e.target);
					io.unobserve(e.target);
				}
			});
		}, { threshold: 0.4 });
		els.forEach(function (el) { io.observe(el); });
	}

	function init() {
		initTickers();
		initCounters();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
