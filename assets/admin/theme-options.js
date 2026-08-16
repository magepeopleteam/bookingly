(function ($, wp) {
	'use strict';

	var $wrap = $('.bookingly-options-wrap');
	var config = window.bookinglyThemeOptions || {};

	if (!$wrap.length) {
		return;
	}

	var $form = $wrap.find('.bookingly-options-form');
	var $panels = $wrap.find('.bookingly-options-panel');
	var $navigation = $wrap.find('[data-section-target]');
	var $saveStatus = $wrap.find('.bookingly-options-save-status');
	var sectionSlugs = $panels.map(function () {
		return $(this).attr('data-section');
	}).get();
	var currentSection = $wrap.attr('data-start-section') || sectionSlugs[0];
	var dirty = false;

	function announce(message) {
		if (message && wp && wp.a11y && typeof wp.a11y.speak === 'function') {
			wp.a11y.speak(message, 'polite');
		}
	}

	function sectionIndex(slug) {
		return sectionSlugs.indexOf(slug);
	}

	function updateReturnUrl(slug) {
		var $referer = $form.find('input[name="_wp_http_referer"]');
		var url;

		try {
			url = new URL(window.location.href);
			url.searchParams.delete('tab');
			url.searchParams.set('section', slug);
			url.hash = '';
			window.history.replaceState({ bookinglySection: slug }, '', url.toString());
			if ($referer.length) {
				$referer.val(url.pathname + url.search);
			}
		} catch (error) {
			// Older browsers can still use every section and save normally.
		}
	}

	function showSection(slug, moveFocus) {
		if (sectionIndex(slug) < 0) {
			return;
		}

		currentSection = slug;
		$panels.attr('hidden', true).removeClass('is-current');
		var $panel = $panels.filter('[data-section="' + slug + '"]');
		$panel.removeAttr('hidden').addClass('is-current');
		$navigation.removeAttr('aria-current');
		var $currentLink = $navigation.filter('[data-section-target="' + slug + '"]');
		$currentLink.attr('aria-current', 'step');
		updateReturnUrl(slug);

		if (moveFocus) {
			var $heading = $panel.find('h2, h3').first();
			$heading.attr('tabindex', '-1').trigger('focus');
			announce((config.sectionChanged || '') + ' ' + $currentLink.text().trim());
		}
	}

	function markDirty() {
		if (dirty) {
			return;
		}
		dirty = true;
		$saveStatus.text(config.unsaved || 'You have unsaved changes.').addClass('is-unsaved');
	}

	function bindMediaFields() {
		$wrap.on('click', '.bookingly-upload', function (event) {
			event.preventDefault();

			var $field = $(this).closest('.bookingly-media-field');
			var frame = $field.data('media-frame');

			if (!frame) {
				frame = wp.media({
					title: config.mediaTitle || 'Select an image',
					button: { text: config.mediaButton || 'Use this image' },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var imageUrl = attachment.url;

					if (attachment.sizes && attachment.sizes.medium) {
						imageUrl = attachment.sizes.medium.url;
					}

					$field.find('input[type="hidden"]').val(attachment.id).trigger('change');
					$field.find('.bookingly-media-preview').empty().append($('<img>', { src: imageUrl, alt: '' }));
					$field.find('.bookingly-remove-media').prop('hidden', false);
					announce(config.imageSelected || 'Image selected.');
				});

				$field.data('media-frame', frame);
			}

			frame.open();
		});

		$wrap.on('click', '.bookingly-remove-media', function (event) {
			event.preventDefault();

			var $field = $(this).closest('.bookingly-media-field');
			$field.find('input[type="hidden"]').val('').trigger('change');
			$field.find('.bookingly-media-preview').empty().append($('<span>').text(config.noImage || 'No image selected'));
			$(this).prop('hidden', true);
			announce(config.imageRemoved || 'Image removed.');
		});
	}

	$wrap.addClass('has-js');
	showSection(currentSection, false);
	bindMediaFields();

	$navigation.on('click', function (event) {
		event.preventDefault();
		showSection($(this).attr('data-section-target'), true);
	});

	$form.on('click', '.bookingly-options-next, .bookingly-options-previous', function () {
		var offset = $(this).hasClass('bookingly-options-next') ? 1 : -1;
		var target = sectionSlugs[sectionIndex(currentSection) + offset];
		if (target) {
			showSection(target, true);
		}
	});

	$form.on('input change', ':input:not([type="submit"]):not([name^="_"])', markDirty);

	$form.on('submit', function () {
		dirty = false;
		$saveStatus.removeClass('is-unsaved').text(config.saving || 'Saving theme options…');
	});

	window.addEventListener('beforeunload', function (event) {
		if (!dirty) {
			return;
		}
		event.preventDefault();
		event.returnValue = '';
	});
})(jQuery, window.wp);
