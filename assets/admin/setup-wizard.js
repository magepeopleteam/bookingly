(function ($, wp) {
	'use strict';

	var $card = $('.bookingly-setup-card');
	if (!$card.length || !window.bookinglySetup) {
		return;
	}

	var $panels = $card.find('.bookingly-setup-panel');
	var $stepButtons = $('.bookingly-setup-stepper [data-step-target]');
	var current = parseInt($card.attr('data-start-step'), 10) || 0;
	var complete = $card.attr('data-complete') === '1' || !!bookinglySetup.complete;
	var visited = {};
	var maxImportSteps = 50;

	function announce(message, assertive) {
		if (message && wp && wp.a11y && typeof wp.a11y.speak === 'function') {
			wp.a11y.speak(message, assertive ? 'assertive' : 'polite');
		}
	}

	function setLog($panel, message, state) {
		var $log = $panel.find('.bookingly-setup-log');
		$log.removeClass('is-error is-success is-working');
		if (state) {
			$log.addClass('is-' + state);
		}
		$log.text(message || '');
		announce(message, state === 'error');
	}

	function responseData(response) {
		return response && response.data ? response.data : {};
	}

	function responseMessage(response, fallback) {
		var data = responseData(response);
		if (data.message) {
			return data.message;
		}
		if (data.errors && data.errors.length) {
			return data.errors.join(' ');
		}
		return fallback;
	}

	function markVisited(index) {
		visited[index] = true;
		$stepButtons.each(function () {
			var $button = $(this);
			var target = parseInt($button.attr('data-step-target'), 10);
			$button.prop('disabled', !visited[target]);
		});
	}

	function updateStepper(index) {
		$stepButtons.removeAttr('aria-current');
		$stepButtons.filter('[data-step-target="' + index + '"]').attr('aria-current', 'step');
		$stepButtons.each(function () {
			var target = parseInt($(this).attr('data-step-target'), 10);
			$(this).closest('li').toggleClass('is-complete', target < index && !!visited[target]);
		});
	}

	function goTo(index, moveFocus) {
		if (index < 0 || index >= $panels.length || !visited[index]) {
			return;
		}

		current = index;
		$panels.attr('hidden', true).removeClass('is-active');
		var $panel = $panels.filter('[data-step="' + index + '"]');
		$panel.removeAttr('hidden').addClass('is-active');
		updateStepper(index);

		if (moveFocus) {
			var $heading = $panel.find('h2').first();
			$heading.trigger('focus');
			announce($heading.text());
		}
	}

	function unlockAndGo(index) {
		markVisited(index);
		goTo(index, true);
	}

	function setBusy($button, busy, busyText) {
		if (!$button.data('default-label')) {
			$button.data('default-label', $button.text());
		}
		$button.prop('disabled', busy).toggleClass('is-busy', busy);
		$button.text(busy ? busyText : $button.data('default-label'));
	}

	function completeStep($panel) {
		$panel.addClass('is-complete');
		$panel.find('.bookingly-setup-action').each(function () {
			var $action = $(this);
			$action.prop('disabled', true).attr('aria-disabled', 'true');
			if ($action.data('done-label')) {
				$action.text($action.data('done-label'));
			}
		});
		$panel.find('.bookingly-setup-next')
			.prop('disabled', false)
			.removeAttr('aria-disabled')
			.addClass('button-primary');
	}

	function blockStep($panel) {
		$panel.removeClass('is-complete');
		$panel.find('.bookingly-setup-action').prop('disabled', false).removeAttr('aria-disabled');
		$panel.find('.bookingly-setup-next')
			.prop('disabled', true)
			.attr('aria-disabled', 'true')
			.removeClass('button-primary');
	}

	function updateReadiness(check, ready) {
		$('.bookingly-readiness-list [data-check="' + check + '"]')
			.toggleClass('is-ready', !!ready)
			.toggleClass('is-pending', !ready);
	}

	function setPluginStepReady(ready) {
		var $panel = $panels.filter('[data-step="1"]');
		if (ready) {
			completeStep($panel);
		} else {
			blockStep($panel);
		}
		updateReadiness('plugins', ready);
	}

	function requiredPluginsReady() {
		var ready = true;
		$('#bookingly-plugin-results [data-required="1"]').each(function () {
			if ($(this).attr('data-state') !== 'active') {
				ready = false;
			}
		});
		return ready;
	}

	function pluginAction(plugin) {
		var statusId = 'bookingly-plugin-status-' + plugin.key;
		if (plugin.action === 'install' || plugin.action === 'activate') {
			return $('<button>', {
				type: 'button',
				'class': 'button bookingly-plugin-button' + (plugin.required ? ' button-primary' : ''),
				'data-plugin-action': plugin.action,
				'data-plugin-key': plugin.key,
				'aria-describedby': statusId,
				text: plugin.action_label + ' ' + plugin.label
			});
		}
		if (plugin.action === 'link' && plugin.action_url) {
			return $('<a>', {
				'class': 'button',
				href: plugin.action_url,
				text: plugin.action_label
			});
		}
		return $('<span>', {
			'class': 'bookingly-plugin-ready',
			text: plugin.status_label
		});
	}

	function renderPlugins(plugins, focusKey, operationMessage, isError) {
		$.each(plugins || [], function (_, plugin) {
			var $item = $('#bookingly-plugin-results').find('[data-plugin-key="' + plugin.key + '"]');
			if (!$item.length) {
				return;
			}

			$item
				.attr('data-state', plugin.state)
				.attr('data-required', plugin.required ? '1' : '0')
				.attr('aria-busy', 'false')
				.removeClass('has-operation-error');
			$item.find('.bookingly-plugin-copy > div .bookingly-required, .bookingly-plugin-copy > div .bookingly-optional')
				.removeClass('bookingly-required bookingly-optional')
				.addClass(plugin.required ? 'bookingly-required' : 'bookingly-optional')
				.text(plugin.required ? bookinglySetup.i18n.required : bookinglySetup.i18n.optional);
			$item.find('.bookingly-plugin-copy > p').first().text(plugin.description || '');

			var $status = $item.find('.bookingly-plugin-status');
			var focused = plugin.key === focusKey;
			$status.text(focused && operationMessage ? operationMessage : plugin.status_label);
			if (focused && isError) {
				$item.addClass('has-operation-error');
			}
			$item.find('.bookingly-plugin-action').empty().append(pluginAction(plugin));
		});

		if (focusKey) {
			var $focusStatus = $('#bookingly-plugin-status-' + focusKey);
			if ($focusStatus.length) {
				$focusStatus.trigger('focus');
			}
		}
	}

	function pluginOperation($button) {
		var key = $button.attr('data-plugin-key');
		var action = $button.attr('data-plugin-action');
		var $item = $button.closest('.bookingly-plugin-card');
		var $panel = $panels.filter('[data-step="1"]');
		var busyText = action === 'install' ? bookinglySetup.i18n.installing : bookinglySetup.i18n.activating;

		$item.attr('aria-busy', 'true').removeClass('has-operation-error');
		setBusy($button, true, busyText);
		$item.find('.bookingly-plugin-status').text(busyText);
		announce(busyText);

		$.post(bookinglySetup.ajaxUrl, {
			action: action === 'install' ? 'bookingly_setup_install_plugin' : 'bookingly_setup_activate_plugin',
			nonce: bookinglySetup.nonce,
			plugin: key
		}).done(function (response) {
			var data = responseData(response);
			renderPlugins(data.plugins, key, data.message, false);
			setPluginStepReady(!!data.required_ready);
			setLog($panel, data.required_ready ? bookinglySetup.i18n.pluginsReady : bookinglySetup.i18n.pluginsBlocked, data.required_ready ? 'success' : 'working');
		}).fail(function (xhr) {
			var response = xhr.responseJSON || {};
			var data = responseData(response);
			var message = responseMessage(response, bookinglySetup.i18n.error);
			if (data.plugins && data.plugins.length) {
				renderPlugins(data.plugins, key, message, true);
			} else {
				setBusy($button, false, busyText);
				$item.addClass('has-operation-error');
				$item.find('.bookingly-plugin-status').text(message).trigger('focus');
			}
			setPluginStepReady(typeof data.required_ready === 'boolean' ? data.required_ready : requiredPluginsReady());
			setLog($panel, message, 'error');
		}).always(function () {
			$item.attr('aria-busy', 'false');
		});
	}

	function renderPageResults(pages) {
		var $list = $('#bookingly-page-results').empty();
		$('#bookingly-pages-empty').prop('hidden', true);
		$.each(pages || {}, function (slug, page) {
			var $item = $('<li>').attr('data-status', page.status || 'error');
			$('<strong>').text(page.label || slug).appendTo($item);
			$('<span>').text(page.message || '').appendTo($item);
			$list.append($item);
		});
		if (!$list.children().length) {
			$('#bookingly-pages-empty').prop('hidden', false);
		}
	}

	if (complete) {
		for (var completedIndex = 0; completedIndex < $panels.length; completedIndex++) {
			visited[completedIndex] = true;
		}
	} else {
		visited[0] = true;
	}

	$panels.each(function () {
		var $panel = $(this);
		if (!$panel.find('.bookingly-setup-next').prop('disabled') && $panel.find('.bookingly-setup-action').length) {
			completeStep($panel);
		}
	});

	markVisited(current);
	goTo(current, false);

	$card.on('click', '.bookingly-setup-next', function () {
		if (current < $panels.length - 1) {
			unlockAndGo(current + 1);
		}
	});
	$card.on('click', '.bookingly-setup-back', function () {
		if (current > 0) {
			goTo(current - 1, true);
		}
	});
	$stepButtons.on('click', function () {
		goTo(parseInt($(this).attr('data-step-target'), 10), true);
	});
	$card.on('click', '.bookingly-plugin-button', function () {
		pluginOperation($(this));
	});

	$('#bookingly-create-pages').on('click', function () {
		var $button = $(this);
		var $panel = $panels.filter('[data-step="2"]');
		setBusy($button, true, bookinglySetup.i18n.working);
		setLog($panel, bookinglySetup.i18n.working, 'working');
		$.post(bookinglySetup.ajaxUrl, {
			action: 'bookingly_setup_create_pages',
			nonce: bookinglySetup.nonce
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				setLog($panel, responseMessage(response, bookinglySetup.i18n.error), 'error');
				return;
			}
			renderPageResults(response.data.pages);
			updateReadiness('pages', true);
			completeStep($panel);
			setLog($panel, bookinglySetup.i18n.pagesReady, 'success');
		}).fail(function (xhr) {
			var response = xhr.responseJSON;
			if (response && response.data && response.data.pages) {
				renderPageResults(response.data.pages);
			}
			setLog($panel, responseMessage(response, bookinglySetup.i18n.error), 'error');
		}).always(function () {
			setBusy($button, false, bookinglySetup.i18n.working);
		});
	});

	function importFailed($panel, $button, message) {
		setBusy($button, false, bookinglySetup.i18n.importing);
		$button.data('default-label', bookinglySetup.i18n.retry).text(bookinglySetup.i18n.retry);
		setLog($panel, message || bookinglySetup.i18n.error, 'error');
	}

	function runImport(step, calls, previousStep) {
		var $panel = $panels.filter('[data-step="3"]');
		var $button = $('#bookingly-import-demo');
		var $progress = $panel.find('.bookingly-setup-progress').removeAttr('hidden');
		var $track = $progress.find('[role="progressbar"]');
		var $bar = $track.find('span');
		var $label = $progress.find('.bookingly-setup-progress-label');

		if (calls >= maxImportSteps || (calls > 0 && step === previousStep)) {
			importFailed($panel, $button, bookinglySetup.i18n.importLimit);
			return;
		}

		$.post(bookinglySetup.ajaxUrl, {
			action: 'bookingly_setup_import_demo',
			nonce: bookinglySetup.importNonce,
			step: step
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				importFailed($panel, $button, responseMessage(response, bookinglySetup.i18n.error));
				return;
			}

			var data = response.data;
			var percent = Math.max(0, Math.min(100, parseInt(data.percent, 10) || 0));
			$bar.css('width', percent + '%');
			$track.attr('aria-valuenow', percent);
			$label.text((data.label ? data.label + ' — ' : '') + percent + '%');
			if (data.done) {
				setBusy($button, false, bookinglySetup.i18n.importing);
				completeStep($panel);
				updateReadiness('demo', true);
				setLog($panel, data.skipped ? bookinglySetup.i18n.importSkipped : bookinglySetup.i18n.importComplete, 'success');
				return;
			}
			var nextStep = parseInt(data.step, 10);
			runImport(isNaN(nextStep) ? step + 1 : nextStep, calls + 1, step);
		}).fail(function (xhr) {
			importFailed($panel, $button, responseMessage(xhr.responseJSON, bookinglySetup.i18n.importLimit));
		});
	}

	$('#bookingly-import-demo').on('click', function () {
		var $panel = $panels.filter('[data-step="3"]');
		setBusy($(this), true, bookinglySetup.i18n.importing);
		setLog($panel, bookinglySetup.i18n.importing, 'working');
		runImport(0, 0, -1);
	});
	$('#bookingly-skip-demo').on('click', function () {
		var $panel = $panels.filter('[data-step="3"]');
		completeStep($panel);
		updateReadiness('demo', true);
		setLog($panel, bookinglySetup.i18n.importSkipped, 'success');
	});

	$('#bookingly-finish-setup').on('click', function () {
		if (complete) {
			window.location.href = bookinglySetup.themeOptions;
			return;
		}
		var $button = $(this);
		var $panel = $panels.filter('[data-step="4"]');
		setBusy($button, true, bookinglySetup.i18n.finishing);
		setLog($panel, bookinglySetup.i18n.finishing, 'working');
		$.post(bookinglySetup.ajaxUrl, {
			action: 'bookingly_setup_finish',
			nonce: bookinglySetup.nonce
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.redirect) {
				setLog($panel, responseMessage(response, bookinglySetup.i18n.finishBlocked), 'error');
				setBusy($button, false, bookinglySetup.i18n.finishing);
				return;
			}
			window.location.href = response.data.redirect;
		}).fail(function (xhr) {
			var response = xhr.responseJSON;
			if (response && response.data) {
				updateReadiness('plugins', response.data.plugins_ready);
				updateReadiness('pages', response.data.pages_ready);
			}
			setLog($panel, responseMessage(response, bookinglySetup.i18n.finishBlocked), 'error');
			setBusy($button, false, bookinglySetup.i18n.finishing);
		});
	});
})(jQuery, window.wp);
