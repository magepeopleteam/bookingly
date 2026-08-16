/**
 * Bookingly front-end behaviour.
 *
 * No jQuery, no dependencies. Every feature is opt-in by markup, and every
 * block is scoped so a section dropped into a page builder more than once still
 * behaves — nothing here assumes a single instance per page.
 */
(function (root) {
	'use strict';

	/**
	 * Small timer/state controller kept separate from the DOM so its behaviour
	 * can be regression-tested without a browser.
	 *
	 * @param {Object} options Controller options.
	 * @return {Object} Public controller API.
	 */
	function createHeroAutoplayController(options) {
		var settings = options || {};
		var interval = Number(settings.interval) || 5000;
		var scheduleTimeout = settings.setTimeout || setTimeout;
		var cancelTimeout = settings.clearTimeout || clearTimeout;
		var onAdvance = typeof settings.onAdvance === 'function' ? settings.onAdvance : function () {};
		var timer = null;
		var destroyed = false;
		var manualPaused = false;
		var blockers = {
			hover: false,
			focus: false,
			hidden: false,
			reduced: false
		};

		function canRun() {
			return !destroyed && !manualPaused && !blockers.hover && !blockers.focus && !blockers.hidden && !blockers.reduced;
		}

		function cancel() {
			if (timer !== null) {
				cancelTimeout(timer);
				timer = null;
			}
		}

		function schedule() {
			cancel();
			if (!canRun()) {
				return;
			}

			timer = scheduleTimeout(function () {
				timer = null;
				if (!canRun()) {
					return;
				}
				onAdvance();
				schedule();
			}, interval);
		}

		return {
			restart: schedule,
			setManualPaused: function (paused) {
				manualPaused = !!paused;
				schedule();
			},
			setBlocker: function (name, blocked) {
				if (!Object.prototype.hasOwnProperty.call(blockers, name)) {
					return;
				}
				blockers[name] = !!blocked;
				schedule();
			},
			getState: function () {
				return {
					manualPaused: manualPaused,
					blockers: {
						hover: blockers.hover,
						focus: blockers.focus,
						hidden: blockers.hidden,
						reduced: blockers.reduced
					},
					running: timer !== null
				};
			},
			destroy: function () {
				destroyed = true;
				cancel();
			}
		};
	}

	/**
	 * Create a cache-safe RFC 4122 version-4 submission identifier.
	 *
	 * @param {Crypto} cryptoObject Browser crypto implementation.
	 * @return {string} UUID, or an empty string when secure randomness is absent.
	 */
	function createContactSubmissionId(cryptoObject) {
		if (!cryptoObject) {
			return '';
		}
		if (typeof cryptoObject.randomUUID === 'function') {
			return cryptoObject.randomUUID().toLowerCase();
		}
		if (typeof cryptoObject.getRandomValues !== 'function') {
			return '';
		}

		var bytes = new Uint8Array(16);
		cryptoObject.getRandomValues(bytes);
		bytes[6] = (bytes[6] & 15) | 64;
		bytes[8] = (bytes[8] & 63) | 128;
		var hex = Array.prototype.map.call(bytes, function (byte) {
			return byte.toString(16).padStart(2, '0');
		}).join('');

		return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
	}

	/** Retain a valid ID for retry; generate one for a new/cached form. */
	function ensureContactSubmissionId(current, cryptoObject) {
		return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(current || '')
			? current
			: createContactSubmissionId(cryptoObject);
	}

	if (typeof module === 'object' && module.exports) {
		module.exports = {
			createHeroAutoplayController: createHeroAutoplayController,
			createContactSubmissionId: createContactSubmissionId,
			ensureContactSubmissionId: ensureContactSubmissionId
		};
	}

	if (!root || !root.document) {
		return;
	}

	var window = root;
	var document = root.document;
	var data = window.bookinglyData || {};

	/* ---------------------------------------------------------------------
	 * Featured-service carousel
	 *
	 * Each carousel owns its state and timer. Autoplay pauses temporarily for
	 * interaction, page visibility, and reduced motion, while a visitor's
	 * explicit Pause choice remains independent of those temporary blockers.
	 * ------------------------------------------------------------------- */

	Array.prototype.forEach.call(document.querySelectorAll('[data-hv-hero-slider]'), function (carousel) {
		var slides = carousel.querySelectorAll('[data-hv-hero-slide]');
		var dots = carousel.querySelectorAll('[data-hv-hero-dot]');
		var toggle = carousel.querySelector('[data-hv-hero-toggle]');
		var status = carousel.querySelector('[data-hv-hero-status]');
		var interval = Math.max(1000, parseInt(carousel.getAttribute('data-interval'), 10) || 5000);

		if (slides.length < 2 || slides.length !== dots.length || !toggle) {
			return;
		}

		var current = 0;
		var mediaQuery = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
		var themeReduced = document.body.classList.contains('hv-motion-reduced');
		var controller;

		function showSlide(index, announce) {
			current = (index + slides.length) % slides.length;

			Array.prototype.forEach.call(slides, function (slide, slideIndex) {
				var active = slideIndex === current;
				slide.classList.toggle('is-active', active);
				slide.setAttribute('aria-hidden', active ? 'false' : 'true');
				if (active) {
					slide.removeAttribute('inert');
				} else {
					slide.setAttribute('inert', '');
				}
			});

			Array.prototype.forEach.call(dots, function (dot, dotIndex) {
				var active = dotIndex === current;
				dot.classList.toggle('is-on', active);
				dot.setAttribute('aria-current', active ? 'true' : 'false');
			});

			if (announce && status) {
				status.textContent = dots[current].getAttribute('data-announcement') || '';
			}
		}

		controller = createHeroAutoplayController({
			interval: interval,
			onAdvance: function () {
				showSlide(current + 1, false);
			}
		});

		function isReduced() {
			return themeReduced || !!(mediaQuery && mediaQuery.matches);
		}

		function updateToggle() {
			var state = controller.getState();
			var reduced = state.blockers.reduced;
			var label = reduced
				? toggle.getAttribute('data-reduced-label')
				: (state.manualPaused ? toggle.getAttribute('data-play-label') : toggle.getAttribute('data-pause-label'));

			toggle.disabled = reduced;
			toggle.setAttribute('aria-pressed', state.manualPaused ? 'true' : 'false');
			toggle.setAttribute('aria-label', label || '');
			toggle.querySelector('span').textContent = state.manualPaused ? '▶' : 'Ⅱ';
		}

		function updateFocusBlocker() {
			var focused = document.activeElement;
			controller.setBlocker('focus', carousel.contains(focused) && focused !== toggle);
			updateToggle();
		}

		function updateMotionBlocker() {
			controller.setBlocker('reduced', isReduced());
			updateToggle();
		}

		Array.prototype.forEach.call(dots, function (dot) {
			dot.addEventListener('click', function () {
				showSlide(parseInt(dot.getAttribute('data-hv-hero-dot'), 10) || 0, true);
				controller.restart();
			});
		});

		toggle.addEventListener('click', function () {
			var state = controller.getState();
			controller.setManualPaused(!state.manualPaused);
			updateToggle();
		});

		carousel.addEventListener('mouseenter', function () {
			controller.setBlocker('hover', true);
		});

		carousel.addEventListener('mouseleave', function () {
			controller.setBlocker('hover', false);
		});

		carousel.addEventListener('focusin', updateFocusBlocker);
		carousel.addEventListener('focusout', function () {
			window.setTimeout(updateFocusBlocker, 0);
		});

		document.addEventListener('visibilitychange', function () {
			controller.setBlocker('hidden', document.hidden);
		});

		if (mediaQuery) {
			if (typeof mediaQuery.addEventListener === 'function') {
				mediaQuery.addEventListener('change', updateMotionBlocker);
			} else if (typeof mediaQuery.addListener === 'function') {
				mediaQuery.addListener(updateMotionBlocker);
			}
		}

		window.addEventListener('pagehide', function () {
			controller.destroy();
			if (mediaQuery) {
				if (typeof mediaQuery.removeEventListener === 'function') {
					mediaQuery.removeEventListener('change', updateMotionBlocker);
				} else if (typeof mediaQuery.removeListener === 'function') {
					mediaQuery.removeListener(updateMotionBlocker);
				}
			}
		}, { once: true });

		showSlide(0, false);
		controller.setBlocker('hidden', document.hidden);
		updateMotionBlocker();
		updateFocusBlocker();
		controller.restart();
	});

	/* ---------------------------------------------------------------------
	 * Mobile navigation
	 * ------------------------------------------------------------------- */

	(function mobileNav() {
		var toggle = document.querySelector('.hv-nav-toggle');
		var nav = document.getElementById('hv-mobile-nav');

		if (!toggle || !nav) {
			return;
		}

		function close(returnFocus) {
			toggle.setAttribute('aria-expanded', 'false');
			nav.hidden = true;
			if (returnFocus) {
				toggle.focus();
			}
		}

		toggle.addEventListener('click', function () {
			var expanded = toggle.getAttribute('aria-expanded') === 'true';

			toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			nav.hidden = expanded;

			if (!expanded) {
				var firstLink = nav.querySelector('a');
				if (firstLink) {
					firstLink.focus();
				}
			}
		});

		nav.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				close(true);
			}
		});

		nav.addEventListener('click', function (event) {
			if (event.target.closest('a')) {
				close(false);
			}
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 960 && !nav.hidden) {
				close(false);
			}
		});
	})();

	/* ---------------------------------------------------------------------
	 * Service category filters
	 *
	 * Each bar names its own grid, so several services sections can coexist
	 * on one builder-authored page.
	 * ------------------------------------------------------------------- */

	Array.prototype.forEach.call(document.querySelectorAll('[data-hv-filter]'), function (bar) {
		var grid = document.getElementById(bar.getAttribute('data-hv-filter'));
		if (!grid) {
			return;
		}

		var chips = bar.querySelectorAll('.hv-filter-chip');
		var cards = grid.querySelectorAll('.hv-service-card');
		var status = bar.parentNode.querySelector('[data-hv-filter-status]');

		function announce(count) {
			if (!status) {
				return;
			}

			var template = count === 1 ? data.serviceShown : data.servicesShown;
			if (template) {
				status.textContent = template.replace('%d', count);
			}
		}

		Array.prototype.forEach.call(chips, function (chip) {
			chip.addEventListener('click', function () {
				var filter = chip.getAttribute('data-filter');
				var visible = 0;

				Array.prototype.forEach.call(chips, function (other) {
					other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
				});

				Array.prototype.forEach.call(cards, function (card) {
					var show = filter === 'all' || card.getAttribute('data-cat') === filter;
					card.hidden = !show;
					if (show) {
						visible += 1;
					}
				});

				announce(visible);
			});
		});

		announce(cards.length);
	});

	/* ---------------------------------------------------------------------
	 * Contact forms
	 * ------------------------------------------------------------------- */

	Array.prototype.forEach.call(document.querySelectorAll('.hv-contact-form'), function (form) {
		if (!data.ajaxUrl || !data.nonce) {
			return;
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
				return;
			}

			var status = form.querySelector('.hv-form-status');
			var button = form.querySelector('button[type="submit"]');
			var label = button ? button.innerHTML : '';
			var submissionInput = form.querySelector('[data-hv-submission-id]');
			if (!submissionInput) {
				return;
			}
			submissionInput.value = ensureContactSubmissionId(submissionInput.value, window.crypto);
			if (!submissionInput.value) {
				if (status) {
					status.classList.add('is-error');
					status.textContent = data.genericError;
				}
				return;
			}
			var payload = new FormData(form);

			payload.append('action', 'bookingly_contact');
			payload.append('nonce', data.nonce);

			if (button) {
				button.disabled = true;
				button.textContent = data.sending || 'Sending…';
			}

			if (status) {
				status.classList.remove('is-error');
				status.textContent = '';
			}

			function finish(message, isError) {
				if (status) {
					status.textContent = message;
					status.classList.toggle('is-error', !!isError);
				}
				if (button) {
					button.disabled = false;
					button.innerHTML = label;
				}
			}

			fetch(data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (result) {
					if (result && result.success) {
						form.reset();
						submissionInput.value = createContactSubmissionId(window.crypto);
						finish((result.data && result.data.message) || data.sent, false);
						return;
					}

					finish((result && result.data && result.data.message) || data.genericError, true);
				})
				.catch(function () {
					finish(data.genericError, true);
				});
		});
	});

	/* ---------------------------------------------------------------------
	 * Map consent
	 *
	 * The iframe is only created once the visitor asks for it, so no request
	 * reaches Google before consent.
	 * ------------------------------------------------------------------- */

	Array.prototype.forEach.call(document.querySelectorAll('[data-hv-map]'), function (placeholder) {
		var button = placeholder.querySelector('button');
		if (!button) {
			return;
		}

		button.addEventListener('click', function () {
			var src = placeholder.getAttribute('data-hv-map');
			var frame = document.createElement('iframe');

			frame.setAttribute('src', src);
			frame.setAttribute('loading', 'lazy');
			frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
			frame.setAttribute('title', placeholder.getAttribute('data-hv-map-title') || 'Map');

			placeholder.parentNode.replaceChild(frame, placeholder);
		});
	});
})(typeof window !== 'undefined' ? window : null);
