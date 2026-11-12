(function () {
	function readSettingsData(id, fallback) {
		var element = document.getElementById(id);
		return element ? JSON.parse(element.textContent || 'null') : fallback;
	}
	var configuration = readSettingsData('appnuno-configuration', {}) || {};
	var text = readSettingsData('appnuno-text', {}) || {};
	var languages = readSettingsData('appnuno-languages', []) || [];
	var destinations = readSettingsData('appnuno-destinations', []) || [];
	var routeOptions = readSettingsData('appnuno-route-options', []) || [];
	var validationPath = readSettingsData('appnuno-validation-path', null);
	var defaultLanguageCode = readSettingsData('appnuno-default-language', '');
	var cartTabId = typeof configuration.cartTabId === 'string' ? configuration.cartTabId : 'cart';
	var iconCatalogUrl = 'https://appnuno.com/api/icons';
	var defaultContainer = document.getElementById('default-tabs');
	function appnunoIcon(name) {
		var file = name === 'eyeOff' ? 'eye-off' : name;
		return '<span class="appnuno-icon appnuno-icon-' + file + '" aria-hidden="true"></span>';
	}
	function languageAddon(language) {
		var flag = document.createElement('span');
		flag.className = 'appnuno-input-addon';
		if (language.image === false) {
			flag.textContent = language.code;
			return flag;
		}
		if (/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(language.code)) {
			var image = document.createElement('img');
			image.src = (typeof language.image === 'string' && language.image.indexOf('/') !== -1) ? language.image : 'language/' + language.code + '/' + language.code + '.png';
			image.alt = language.name;
			image.width = 16;
			image.height = 11;
			image.onerror = function () {
				var fallback = document.createElement('span');
				fallback.textContent = language.code;
				if (image.parentNode) {
					image.parentNode.replaceChild(fallback, image);
				}
			};
			flag.appendChild(image);
		} else {
			flag.textContent = language.code;
		}
		return flag;
	}
	function createChoiceSwitch(className, label, options, pressedValue, valueAttribute) {
		var control = document.createElement('div');
		control.className = className;
		control.setAttribute('role', 'group');
		control.setAttribute('aria-label', label);
		var indicator = document.createElement('span');
		indicator.className = 'appnuno-switch-indicator';
		indicator.setAttribute('aria-hidden', 'true');
		control.appendChild(indicator);
		options.forEach(function (option) {
			var button = document.createElement('button');
			button.type = 'button';
			button.setAttribute(valueAttribute, option[0]);
			button.setAttribute('aria-pressed', option[0] === pressedValue ? 'true' : 'false');
			button.textContent = option[1];
			control.appendChild(button);
		});
		return control;
	}
	function localUrl(value) {
		var url = String(value || '').replace(/^\s+|\s+$/g, '');
		var match;
		if (!url || /[\u0000-\u001F\u007F\\ ]/.test(url)) {
			return '';
		}
		match = url.match(/^(https?:)\/\/([^/?#]+)([^?#]*)(\?[^#]*)?(#.*)?$/i);
		if (match) {
			if (match[2].indexOf('@') !== -1) {
				return '';
			}
			return (match[3] && match[3].charAt(0) === '/' ? match[3] : '/' + (match[3] || '')) + (match[4] || '') + (match[5] || '');
		}
		if (url.indexOf(':') !== -1 || url.indexOf('//') === 0) {
			return '';
		}
		return url.charAt(0) === '/' ? url : (/^[A-Za-z0-9]/.test(url) ? '/' + url : '');
	}
	var tabNavigationColumn = document.createElement('div');
	tabNavigationColumn.className = 'appnuno-tab-column';
	var tabNavigation = document.createElement('ul');
	tabNavigation.className = 'appnuno-tab-list';
	tabNavigation.setAttribute('role', 'tablist');
	tabNavigation.setAttribute('aria-orientation', 'vertical');
	var tabPanels = document.createElement('div');
	var tabSequence = 0;
	tabPanels.className = 'appnuno-tab-panels';
	var addTabButton = document.createElement('button');
	addTabButton.type = 'button';
	addTabButton.className = 'appnuno-button';
	addTabButton.setAttribute('data-add-tab', 'default-tabs');
	addTabButton.innerHTML = appnunoIcon('plus') + ' ' + text.button_add_default_tab;
	var emptyState = document.createElement('div');
	emptyState.className = 'appnuno-default-empty-state';
	var emptyStateTitle = document.createElement('strong');
	emptyStateTitle.className = 'appnuno-default-empty-title';
	emptyStateTitle.textContent = text.text_default_tabs_empty;
	var emptyStateMessage = document.createElement('p');
	emptyStateMessage.className = 'appnuno-default-empty-message';
	emptyStateMessage.textContent = text.text_default_tabs_empty_help;
	emptyState.appendChild(emptyStateTitle);
	emptyState.appendChild(emptyStateMessage);
	tabNavigationColumn.appendChild(tabNavigation);
	tabNavigationColumn.appendChild(addTabButton);
	defaultContainer.appendChild(tabNavigationColumn);
	defaultContainer.appendChild(tabPanels);
	defaultContainer.appendChild(emptyState);
	defaultContainer.setAttribute('data-validation-path', 'defaultTabs');

	function appendInlineHelp(label, message) {
		if (!label) {
			return null;
		}
		var copy = document.createElement('div');
		copy.className = 'appnuno-field-copy';
		if (label.parentNode) {
			label.parentNode.insertBefore(copy, label);
		}
		copy.appendChild(label);
		if (message) {
			var description = document.createElement('p');
			description.className = 'appnuno-field-description';
			description.textContent = message;
			copy.appendChild(description);
		}
		return copy;
	}

	function appendFieldHelp(label, key) {
		var aliases = { id: 'tab_id', icon: 'icon_catalog' };
		return appendInlineHelp(label, text['help_' + (aliases[key] || key)]);
	}

	var appnunoTooltip = document.createElement('div');
	appnunoTooltip.id = 'appnuno-tooltip';
	appnunoTooltip.setAttribute('role', 'tooltip');
	appnunoTooltip.hidden = true;
	document.body.appendChild(appnunoTooltip);

	function showAppnunoTooltip(anchor) {
		var message = anchor.getAttribute('data-appnuno-tooltip');
		if (!message) {
			return;
		}
		var rect = anchor.getBoundingClientRect();
		appnunoTooltip.textContent = message;
		appnunoTooltip.hidden = false;
		appnunoTooltip.style.left = (rect.left + (rect.width / 2)) + 'px';
		appnunoTooltip.style.top = (rect.top - 8) + 'px';
	}

	function hideAppnunoTooltip(anchor) {
		if (!anchor || !anchor.isConnected || anchor.getAttribute('data-appnuno-tooltip') === appnunoTooltip.textContent) {
			appnunoTooltip.hidden = true;
		}
	}

	document.addEventListener('mouseover', function (event) {
		var anchor = event.target.closest('[data-appnuno-tooltip]');
		if (anchor) {
			showAppnunoTooltip(anchor);
		}
	});
	document.addEventListener('mouseout', function (event) {
		var anchor = event.target.closest('[data-appnuno-tooltip]');
		if (anchor && (!event.relatedTarget || !anchor.contains(event.relatedTarget))) {
			hideAppnunoTooltip(anchor);
		}
	});
	document.addEventListener('focusin', function (event) {
		if (event.target.getAttribute && event.target.getAttribute('data-appnuno-tooltip')) {
			showAppnunoTooltip(event.target);
		}
	});
	document.addEventListener('focusout', function (event) {
		if (event.target.getAttribute && event.target.getAttribute('data-appnuno-tooltip')) {
			hideAppnunoTooltip(event.target);
		}
	});

	var switchResizeObserver = window.ResizeObserver ? new ResizeObserver(function (entries) {
		entries.forEach(function (entry) { placeSwitchIndicator(entry.target); });
	}) : null;

	function placeSwitchIndicator(control) {
		if (switchResizeObserver && !control.__appnunoResizeObserved) {
			control.__appnunoResizeObserved = true;
			switchResizeObserver.observe(control);
		}
		var indicator = control.querySelector('.appnuno-switch-indicator');
		var selected = control.querySelector('[aria-pressed="true"]') || control.querySelector('.appnuno-section-list > li.active > a');
		if (!indicator || !selected) {
			return;
		}
		var selectedRect = selected.getBoundingClientRect();
		if (selectedRect.width < 1 || selectedRect.height < 1) {
			control.removeAttribute('data-placed');
			return;
		}
		var placed = control.getAttribute('data-placed') === 'true';
		if (!placed) {
			indicator.style.transition = 'none';
		}
		var controlRect = control.getBoundingClientRect();
		var controlStyle = window.getComputedStyle(control);
		indicator.style.width = selectedRect.width + 'px';
		indicator.style.height = selectedRect.height + 'px';
		indicator.style.transform = 'translate(' + (selectedRect.left - controlRect.left - (parseFloat(controlStyle.borderLeftWidth) || 0)) + 'px, ' + (selectedRect.top - controlRect.top - (parseFloat(controlStyle.borderTopWidth) || 0)) + 'px)';
		if (!placed) {
			indicator.offsetWidth;
			indicator.style.transition = '';
			control.setAttribute('data-placed', 'true');
		}
	}

	function revealSwitchMotion(control) {
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				placeSwitchIndicator(control);
			});
		});
	}

	function refreshTabNavigation() {
		Array.prototype.forEach.call(tabPanels.querySelectorAll('.appnuno-tab'), function (card) {
			var button = tabNavigation.querySelector('[data-tab-target="' + card.getAttribute('data-tab-key') + '"]');
			if (!button) {
				return;
			}
			var label = card.querySelector('[data-language-label="' + defaultLanguageCode + '"]');
			if (!label || !label.value.trim()) {
				label = card.querySelector('[data-language-label]');
			}
			var id = card.querySelector('[data-field="id"]');
			var title = label && label.value.trim() ? label.value.trim() : (id && id.value.trim() ? id.value.trim() : text.text_tab_list_empty);
			button.querySelector('[data-tab-title]').textContent = title;
			var slot = button.querySelector('[data-tab-icon]');
			slot.textContent = '';
			var iconValue = card.querySelector('[data-field="icon"]');
			var icon = null;
			try {
				icon = iconValue && iconValue.value ? JSON.parse(iconValue.value) : null;
			} catch (error) {
				icon = null;
			}
			if (validCatalogIcon(icon)) {
				var image = document.createElement('img');
				image.src = icon.uri;
				image.alt = '';
				image.width = 24;
				image.height = 24;
				slot.appendChild(image);
			}
			var marker = button.querySelector('[data-visibility-indicator]');
			var remove = button.querySelector('[data-remove-tab]');
			if (card.querySelector('[data-tab-visibility]').value !== '1') {
				if (!marker) {
					marker = document.createElement('span');
					marker.innerHTML = appnunoIcon('eyeOff');
					marker.setAttribute('data-visibility-indicator', 'true');
					marker.setAttribute('data-appnuno-tooltip', text.text_visibility_indicator);
					marker.setAttribute('aria-label', text.text_visibility_indicator);
					button.insertBefore(marker, remove);
				}
			} else if (marker) {
				marker.parentNode.removeChild(marker);
			}
		});
		var isEmpty = !tabPanels.querySelector('.appnuno-tab');
		defaultContainer.classList.toggle('appnuno-default-empty', isEmpty);
		if (isEmpty) {
			addTabButton.innerHTML = appnunoIcon('plus') + ' ' + text.button_add_first_tab;
			emptyState.appendChild(addTabButton);
		} else {
			addTabButton.innerHTML = appnunoIcon('plus') + ' ' + text.button_add_default_tab;
			tabNavigationColumn.appendChild(addTabButton);
		}
	}

	function activateTab(card) {
		Array.prototype.forEach.call(tabPanels.querySelectorAll('.appnuno-tab'), function (panel) {
			var active = panel === card;
			panel.classList.toggle('active', active);
			var link = tabNavigation.querySelector('[data-tab-target="' + panel.getAttribute('data-tab-key') + '"]');
			if (link) {
				link.parentNode.className = active ? 'active' : '';
				link.setAttribute('aria-selected', active ? 'true' : 'false');
			}
		});
		Array.prototype.forEach.call(card.querySelectorAll('.appnuno-switch'), placeSwitchIndicator);
	}

	function appendControlHelp(id, key) {
		var control = document.getElementById(id);
		if (!control) {
			return;
		}
		var group = control.closest('.appnuno-field');
		var label = group && group.querySelector('label');
		appendInlineHelp(label, text[key]);
	}

	function enhanceSelectSwitch(id) {
		var select = document.getElementById(id);
		if (!select || select.tagName !== 'SELECT') {
			return;
		}
		var options = [];
		Array.prototype.forEach.call(select.options, function (option) {
			options.push([option.value, option.textContent]);
		});
		var control = createChoiceSwitch('appnuno-switch appnuno-select-switch', select.parentNode.parentNode.querySelector('label').textContent.trim(), options, select.value, 'data-value');
		control.setAttribute('data-status-switch', id);
		select.classList.add('appnuno-select-switch-source');
		select.parentNode.insertBefore(control, select);
		bindStatusSwitch(control);
	}

	function bindStatusSwitch(control) {
		var input = document.getElementById(control.getAttribute('data-status-switch'));
		if (!input || control.getAttribute('data-switch-bound') === 'true') {
			return;
		}
		control.setAttribute('data-switch-bound', 'true');
		control.addEventListener('click', function (event) {
			var button = event.target.closest('button[data-value]');
			if (!button || !input) {
				return;
			}
			input.value = button.getAttribute('data-value');
			Array.prototype.forEach.call(control.querySelectorAll('button[data-value]'), function (item) {
				item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
			});
			input.dispatchEvent(new Event('change', { bubbles: true }));
			placeSwitchIndicator(control);
		});
		if (input.tagName === 'SELECT') {
			input.addEventListener('change', function () {
				Array.prototype.forEach.call(control.querySelectorAll('button[data-value]'), function (item) {
					item.setAttribute('aria-pressed', item.getAttribute('data-value') === input.value ? 'true' : 'false');
				});
				placeSwitchIndicator(control);
			});
		}
	}

	function createGroup() {
		var group = document.createElement('div');
		group.className = 'appnuno-field';
		group.setAttribute('data-field-group', 'true');
		return group;
	}

	function createGroupLabel(label) {
		var title = document.createElement('label');
		title.className = 'appnuno-field-label';
		title.textContent = label;
		return title;
	}

	function createGroupColumn() {
		var column = document.createElement('div');
		column.className = 'appnuno-field-control';
		return column;
	}

	var iconPickerTarget = null;
	var iconPickerStatus = document.getElementById('icon-picker-status');
	var iconPickerGrid = document.getElementById('icon-picker-grid');
	var iconPickerSearch = document.getElementById('icon-picker-search');
	var iconPickerPage = 1;
	var iconPickerCount = document.getElementById('icon-picker-count');
	var iconPickerLoading = document.getElementById('icon-picker-loading');
	var iconPickerStage = iconPickerLoading ? iconPickerLoading.parentNode : null;
	var iconPickerPageSize = 130;
	var iconPickerTimer = null;
	var iconPickerRequest = 0;
	var iconPickerCatalog = null;
	var iconPickerCatalogQuery = null;
	var iconPickerDialog = document.getElementById('icon-picker-dialog');
	var pagePickerDialog = document.getElementById('page-picker-dialog');
	var pagePickerGroup = null;

	var dialogReturnFocus = null;

	function showDialog(dialog) {
		if (!dialog || dialog.classList.contains('is-open')) {
			return;
		}
		dialogReturnFocus = document.activeElement;
		var backdrop = document.createElement('div');
		backdrop.className = 'appnuno-backdrop';
		backdrop.addEventListener('click', function () { hideDialog(dialog); });
		dialog.__appnunoBackdrop = backdrop;
		document.body.appendChild(backdrop);
		document.body.classList.add('appnuno-dialog-open');
		dialog.hidden = false;
		dialog.classList.add('is-open');
		if (!dialog.__appnunoDismissBound) {
			dialog.__appnunoDismissBound = true;
			dialog.addEventListener('click', function (event) {
				if (event.target === dialog) {
					hideDialog(dialog);
				}
			});
		}
		var focusTarget = dialog.querySelector('input:not([type="hidden"]), select, textarea') || dialog.querySelector('button:not([data-appnuno-dismiss])');
		if (focusTarget && typeof focusTarget.focus === 'function') {
			focusTarget.focus();
		}
	}

	function hideDialog(dialog) {
		if (!dialog || !dialog.classList.contains('is-open')) {
			return;
		}
		dialog.classList.remove('is-open');
		dialog.hidden = true;
		if (dialog.__appnunoBackdrop && dialog.__appnunoBackdrop.parentNode) {
			dialog.__appnunoBackdrop.parentNode.removeChild(dialog.__appnunoBackdrop);
		}
		dialog.__appnunoBackdrop = null;
		if (!document.querySelector('.appnuno-dialog.is-open')) {
			document.body.classList.remove('appnuno-dialog-open');
		}
		if (dialogReturnFocus && typeof dialogReturnFocus.focus === 'function') {
			dialogReturnFocus.focus();
		}
		dialogReturnFocus = null;
	}

	function showIconPicker() {
		showDialog(iconPickerDialog);
	}

	function hideIconPicker() {
		hideDialog(iconPickerDialog);
	}

	document.addEventListener('click', function (event) {
		var source = event.target;
		if (source && source.nodeType !== 1) {
			source = source.parentElement;
		}
		var dismiss = source && source.closest ? source.closest('[data-appnuno-dismiss]') : null;
		if (!dismiss) {
			return;
		}
		if (dismiss.getAttribute('data-appnuno-dismiss') === 'dialog') {
			var dialog = dismiss.closest('.appnuno-dialog');
			if (dialog) {
				event.preventDefault();
				hideDialog(dialog);
			}
		}
		if (dismiss.getAttribute('data-appnuno-dismiss') === 'alert') {
			var alertBox = dismiss.closest('.appnuno-alert');
			if (alertBox && alertBox.parentNode) {
				alertBox.parentNode.removeChild(alertBox);
			}
		}
	});
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape' && event.key !== 'Esc') {
			return;
		}
		var openDialog = document.querySelector('.appnuno-dialog.is-open');
		if (openDialog) {
			event.preventDefault();
			hideDialog(openDialog);
		}
	});

	function validCatalogIcon(icon) {
		if (!icon || typeof icon.id !== 'string' || typeof icon.uri !== 'string' || typeof icon.content_hash !== 'string' || !/^[a-f0-9]{64}$/.test(icon.content_hash)) {
			return false;
		}
		var id = /^(duo|custom):([a-z0-9]+(?:-[a-z0-9]+)*)$/.exec(icon.id);
		try {
			var uri = new URL(icon.uri);
			var asset = /^\/icon-assets\/(duo|custom)\/([a-z0-9]+(?:-[a-z0-9]+)*)\/([a-f0-9]{64})\.svg$/.exec(uri.pathname);
			return !!id && !!asset && uri.protocol === 'https:' && uri.hostname === 'appnuno.com' && !uri.username && !uri.password && !uri.port && !uri.search && !uri.hash && id[1] === asset[1] && id[2] === asset[2] && icon.content_hash === asset[3];
		} catch (error) {
			return false;
		}
	}

	function updateIconField(input, icon) {
		icon = validCatalogIcon(icon) ? icon : null;
		input.value = icon ? JSON.stringify(icon) : '';
		var button = input.parentNode.querySelector('[data-icon-picker]');
	button.textContent = icon ? icon.id : text.text_choose_icon;
	button.classList.toggle('appnuno-icon-empty', !icon);
	if (icon) {
			var image = document.createElement('img');
			image.src = icon.uri;
			image.alt = '';
			image.width = 24;
			image.height = 24;
			button.insertBefore(image, button.firstChild);
		}
	}

	function renderIconOptions(icons) {
		hideAppnunoTooltip();
		iconPickerGrid.textContent = '';
		icons.forEach(function (icon) {
			if (!validCatalogIcon(icon)) {
				return;
			}
			var option = document.createElement('button');
			option.type = 'button';
			option.className = 'appnuno-button appnuno-icon-option';
			option.title = icon.id;
			option.setAttribute('aria-label', icon.id);
			option.setAttribute('data-appnuno-tooltip', icon.id);
			var image = document.createElement('img');
			image.alt = '';
			image.width = 28;
			image.height = 28;
			image.src = icon.uri;
			option.appendChild(image);
			option.addEventListener('click', function () {
				if (iconPickerTarget) {
					updateIconField(iconPickerTarget, { id: icon.id, uri: icon.uri, content_hash: icon.content_hash });
					iconPickerTarget.dispatchEvent(new Event('input', { bubbles: true }));
				}
				hideIconPicker();
			});
			iconPickerGrid.appendChild(option);
		});
	}

	function waitForIconImages() {
		return Promise.all(Array.prototype.map.call(iconPickerGrid.querySelectorAll('img'), function (image) {
			if (typeof image.decode === 'function') {
				return image.decode().catch(function () {});
			}
			if (image.complete) {
				return Promise.resolve();
			}
			return new Promise(function (resolve) {
				image.addEventListener('load', resolve, { once: true });
				image.addEventListener('error', resolve, { once: true });
			});
		}));
	}

	function renderIconPager(currentPage, lastPage) {
		Array.prototype.forEach.call(document.querySelectorAll('.appnuno-icon-pager'), function (pager) {
			pager.textContent = '';
			pager.hidden = lastPage < 2;
			if (pager.hidden) {
				return;
			}
			function addButton(label, pageNumber, disabled, current) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'appnuno-button';
				button.textContent = label;
				button.disabled = disabled;
				if (current) {
					button.setAttribute('aria-current', 'page');
				}
				if (!disabled && !current) {
					button.addEventListener('click', function () { loadIconCatalog(pageNumber); });
				}
				pager.appendChild(button);
			}
			addButton(text.text_icon_page_previous, currentPage - 1, currentPage <= 1, false);
			var start = Math.max(1, currentPage - 2);
			var end = Math.min(lastPage, start + 4);
			start = Math.max(1, end - 4);
			var pageNumber;
			for (pageNumber = start; pageNumber <= end; pageNumber += 1) {
				addButton(String(pageNumber), pageNumber, false, pageNumber === currentPage);
			}
			addButton(text.text_icon_page_next, currentPage + 1, currentPage >= lastPage, false);
		});
	}

	function setIconPickerLoading(loading) {
		iconPickerLoading.hidden = !loading;
		if (iconPickerStage) {
			iconPickerStage.classList.toggle('is-loading', loading);
		}
	}

	function presentIconPage(icons, currentPage, lastPage, total, local) {
		var page = Math.min(Math.max(currentPage, 1), Math.max(lastPage, 1));
		var visible = local ? icons.slice((page - 1) * iconPickerPageSize, page * iconPickerPageSize) : icons;
		var requestId = iconPickerRequest;
		iconPickerPage = page;
		setIconPickerLoading(true);
		renderIconOptions(visible);
		iconPickerCount.textContent = String(text.text_icon_catalog_total || '%s').replace('%s', String(total));
		renderIconPager(page, total ? lastPage : 0);
		iconPickerStatus.textContent = '';
		waitForIconImages().then(function () {
			if (requestId === iconPickerRequest) {
				setIconPickerLoading(false);
			}
		});
	}

	function revealIconPage(requestId, started, apply) {
		var wait = 200 - (Date.now() - started);
		if (wait < 0) {
			wait = 0;
		}
		window.setTimeout(function () {
			if (requestId !== iconPickerRequest) {
				return;
			}
			apply();
		}, wait);
	}

	function loadIconCatalog(page) {
		iconPickerPage = page || 1;
		var search = iconPickerSearch.value.trim().slice(0, 120);
		var requestId = iconPickerRequest + 1;
		iconPickerRequest = requestId;
		var started = Date.now();
		setIconPickerLoading(true);
		if (iconPickerCatalog && iconPickerCatalogQuery === search) {
			var cachedLastPage = Math.ceil(iconPickerCatalog.length / iconPickerPageSize) || 1;
			revealIconPage(requestId, started, function () {
				presentIconPage(iconPickerCatalog, iconPickerPage, cachedLastPage, iconPickerCatalog.length, true);
			});
			return;
		}
		var url = new URL(iconCatalogUrl, window.location.href);
		if (search) {
			url.searchParams.set('q', search);
		}
		url.searchParams.set('page', '1');
		url.searchParams.set('per_page', String(iconPickerPageSize));
		fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (response) {
				if (!response.ok) {
					throw new Error(text.text_icon_catalog_error);
				}
				return response.json();
			})
			.then(function (catalog) {
				if (requestId !== iconPickerRequest) {
					return;
				}
				var icons = catalog && Array.isArray(catalog.data) ? catalog.data : [];
				var meta = catalog && catalog.meta ? catalog.meta : {};
				var lastPage = meta.last_page || 1;
				var currentPage = meta.current_page || 1;
				var total = typeof meta.total === 'number' ? meta.total : icons.length;
				var complete = (lastPage < 2 && icons.length === total) || icons.length > iconPickerPageSize;
				if (complete) {
					iconPickerCatalog = icons;
					iconPickerCatalogQuery = search;
					revealIconPage(requestId, started, function () {
						presentIconPage(icons, iconPickerPage, Math.ceil(icons.length / iconPickerPageSize) || 1, icons.length, true);
					});
					return;
				}
				iconPickerCatalog = null;
				iconPickerCatalogQuery = null;
				if (iconPickerPage < 2) {
					revealIconPage(requestId, started, function () {
						presentIconPage(icons, currentPage, lastPage, total, false);
					});
					return;
				}
				url.searchParams.set('page', String(iconPickerPage));
				return fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (response) {
						if (!response.ok) {
							throw new Error(text.text_icon_catalog_error);
						}
						return response.json();
					})
					.then(function (pageCatalog) {
						if (requestId !== iconPickerRequest) {
							return;
						}
						var pageIcons = pageCatalog && Array.isArray(pageCatalog.data) ? pageCatalog.data : [];
						var pageMeta = pageCatalog && pageCatalog.meta ? pageCatalog.meta : {};
						revealIconPage(requestId, started, function () {
							presentIconPage(pageIcons, pageMeta.current_page || iconPickerPage, pageMeta.last_page || lastPage, typeof pageMeta.total === 'number' ? pageMeta.total : total, false);
						});
					});
			})
			.catch(function () {
				if (requestId !== iconPickerRequest) {
					return;
				}
				setIconPickerLoading(false);
				iconPickerStatus.textContent = text.text_icon_catalog_error;
			});
	}

	function createIconField(tab, path) {
		var group = createGroup();
		var title = createGroupLabel(text.text_icon);
		var column = createGroupColumn();
		var input = document.createElement('input');
		input.type = 'hidden';
		input.setAttribute('data-field', 'icon');
		input.setAttribute('data-validation-path', path + '.icon');
		var selected = validCatalogIcon(tab.icon) ? tab.icon : null;
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'appnuno-button';
		button.setAttribute('data-icon-picker', 'true');
		button.addEventListener('click', function () {
			iconPickerTarget = input;
			showIconPicker();
			loadIconCatalog();
		});
		column.appendChild(input);
		column.appendChild(button);
		group.appendChild(appendFieldHelp(title, 'icon'));
		group.appendChild(column);
		updateIconField(input, selected);
		return group;
	}

	iconPickerSearch.addEventListener('input', function () {
		hideAppnunoTooltip();
		iconPickerCatalog = null;
		iconPickerCatalogQuery = null;
		window.clearTimeout(iconPickerTimer);
		iconPickerTimer = window.setTimeout(function () { loadIconCatalog(1); }, 400);
	});

	function createField(label, value, key, type, path) {
		var group = createGroup();
		var title = createGroupLabel(label);
		var column = createGroupColumn();
		var input = document.createElement('input');
		input.id = 'appnuno-field-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		title.htmlFor = input.id;
		input.type = type || 'text';
		input.className = 'appnuno-input';
		input.value = value || '';
		input.setAttribute('data-field', key);
		input.setAttribute('data-validation-path', path);
		if (key === 'id') input.placeholder = text.text_tab_id_placeholder;
		column.appendChild(input);
		group.appendChild(appendFieldHelp(title, key));
		group.appendChild(column);
		return group;
	}

	function createLabelFields(tab, path) {
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		group.setAttribute('data-validation-path', path + '.labels');
		var title = createGroupLabel(text.text_language_labels);
		var column = createGroupColumn();
		var labels = tab.labels || {};
		var legacyLabel = tab.label || '';
		var legacyAssigned = false;
		languages.forEach(function (language) {
			var inputGroup = document.createElement('div');
			inputGroup.className = 'appnuno-input-group';
			var flag = languageAddon(language);
			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'appnuno-input';
			input.maxLength = 80;
			input.placeholder = language.name + ' (' + language.code + ')';
			input.title = language.name + ' (' + language.code + ')';
			input.setAttribute('data-language-label', language.code);
			input.setAttribute('data-validation-path', path + '.labels.' + language.code);
			if (labels[language.code]) {
				input.value = labels[language.code];
			} else if (legacyLabel && (language.code === defaultLanguageCode || !legacyAssigned)) {
				input.value = legacyLabel;
				legacyAssigned = true;
			}
			inputGroup.appendChild(flag);
			inputGroup.appendChild(input);
			column.appendChild(inputGroup);
		});
		if (!languages.length) {
			var fallback = document.createElement('input');
			fallback.type = 'text';
			fallback.className = 'appnuno-input';
			fallback.maxLength = 80;
			fallback.placeholder = text.text_label;
			fallback.value = legacyLabel;
			fallback.setAttribute('data-language-label', 'default');
			fallback.setAttribute('data-validation-path', path + '.labels.default');
			column.appendChild(fallback);
		}
		group.appendChild(appendFieldHelp(title, 'tab_labels'));
		group.appendChild(column);
		return group;
	}

	function createUrlField(tab, path) {
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		group.setAttribute('data-validation-path', path + '.url');
		var title = createGroupLabel(text.text_destination);
		var column = createGroupColumn();
		var select = document.createElement('select');
		select.id = 'appnuno-destination-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		title.htmlFor = select.id;
		select.className = 'appnuno-input';
		select.setAttribute('data-url-select', 'true');
		select.appendChild(new Option(text.text_custom_url, '__custom__'));
		destinations.forEach(function (destination) {
			select.appendChild(new Option(destination.label, destination.url));
		});
		var customUrls = document.createElement('div');
		customUrls.className = 'appnuno-custom-urls';
		var savedUrls = tab.urls || {};
		var languageList = languages.length ? languages : [{ code: 'default', name: text.text_default_language, image: '' }];
		languageList.forEach(function (language) {
			var inputGroup = document.createElement('div');
			inputGroup.className = 'appnuno-input-group';
			var flag = languageAddon(language);
			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'appnuno-input';
			input.placeholder = text.text_url_placeholder;
			input.addEventListener('change', function () {
				var normalized = localUrl(input.value);
				if (normalized) {
					input.value = normalized;
				}
			});
			input.setAttribute('data-language-url', language.code);
			input.setAttribute('data-validation-path', path + '.urls.' + language.code);
			input.value = savedUrls[language.code] || tab.url || '';
			inputGroup.appendChild(flag);
			inputGroup.appendChild(input);
			customUrls.appendChild(inputGroup);
		});
		var hasLocalizedCustomUrls = Object.keys(savedUrls).length > 0;
		var known = !hasLocalizedCustomUrls && destinations.some(function (destination) { return destination.url === (tab.url || ''); });
		var chosen = known ? tab.url : (tab.url || hasLocalizedCustomUrls ? '__custom__' : '');
		if (!chosen) {
			var placeholder = new Option(text.text_choose_destination, '');
			placeholder.disabled = true;
			select.insertBefore(placeholder, select.firstChild);
		}
		select.value = chosen;
		customUrls.style.display = select.value === '__custom__' ? '' : 'none';
		select.addEventListener('change', function () {
			var blank = select.querySelector('option[value=""]');
			if (blank) {
				blank.remove();
			}
			if (select.value === '__custom__') {
				customUrls.style.display = '';
				var firstInput = customUrls.querySelector('[data-language-url]');
				if (firstInput) {
					firstInput.focus();
				}
			} else {
				customUrls.style.display = 'none';
			}
		});
		column.appendChild(select);
		column.appendChild(customUrls);
		group.appendChild(appendFieldHelp(title, 'tab_destination'));
		group.appendChild(column);
		return group;
	}

	function appendPageChoice(list, label, value, checked, path) {
		var row = document.createElement('div');
		var input = document.createElement('input');
		input.type = 'checkbox';
		input.value = value;
		input.checked = !!checked;
		input.setAttribute('data-page-choice', 'true');
		row.className = 'appnuno-check';
		input.id = 'appnuno-page-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-') + '-' + String(value).replace(/[^A-Za-z0-9_-]/g, '-');
		var caption = document.createElement('label');
		caption.className = 'appnuno-check-label';
		caption.htmlFor = input.id;
		caption.textContent = label;
		row.appendChild(input);
		row.appendChild(caption);
		list.appendChild(row);
	}

	function createPagesField(tab, path) {
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		var title = createGroupLabel(text.text_shown_on_pages);
		var column = createGroupColumn();
		var allPages = document.createElement('input');
		allPages.type = 'checkbox';
		allPages.id = 'appnuno-all-pages-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		allPages.checked = typeof tab.allPages === 'boolean' ? tab.allPages : (!Array.isArray(tab.pages) || !tab.pages.length);
		allPages.setAttribute('data-all-pages', 'true');
		allPages.setAttribute('data-validation-path', path + '.allPages');
		var allPagesChoice = document.createElement('div');
		allPagesChoice.hidden = true;
		allPagesChoice.appendChild(allPages);
		var list = document.createElement('div');
		list.className = 'appnuno-page-list';
		list.hidden = true;
		list.setAttribute('data-pages', 'true');
		list.setAttribute('data-validation-path', path + '.pages');
		var selected = {};
		(tab.pages || []).forEach(function (page) {
			selected[page] = true;
		});
		var known = {};
		routeOptions.forEach(function (option) {
			known[option.route] = true;
			appendPageChoice(list, option.label, option.route, !!selected[option.route], path);
		});
		(tab.pages || []).forEach(function (page) {
			if (!known[page]) {
				appendPageChoice(list, page, page, true, path);
			}
		});
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'appnuno-button appnuno-page-manage';
		button.setAttribute('data-page-manage', 'true');
		button.setAttribute('aria-label', text.button_manage_pages);
		button.title = text.button_manage_pages;
		button.setAttribute('data-validation-path', path + '.pages');
		button.addEventListener('click', function () { showPagePicker(group); });
		column.appendChild(allPagesChoice);
		column.appendChild(list);
		column.appendChild(button);
		group.appendChild(appendFieldHelp(title, 'shown_on_pages'));
		group.appendChild(column);
		updatePageSummary(group, button);
		return group;
	}

	function updatePageSummary(group, button) {
		var allPages = group.querySelector('[data-all-pages]');
		var selectedCount = group.querySelectorAll('[data-pages] [data-page-choice]:checked').length;
		if (allPages.checked) {
			button.textContent = text.text_show_on_all_pages;
		} else if (selectedCount) {
			button.textContent = text.text_show_on_selected_pages.replace('%s', String(selectedCount));
		} else {
			button.textContent = text.text_show_on_no_pages;
		}
		button.setAttribute('aria-label', text.button_manage_pages + ': ' + button.textContent);
		button.title = button.textContent;
	}

	function showPagePicker(group) {
		var sourceList = group.querySelector('[data-pages]');
		var sourceAllPages = group.querySelector('[data-all-pages]');
		var choiceContainer = document.getElementById('page-picker-choices');
		var allPagesContainer = document.getElementById('page-picker-all-pages');
		var allPages = document.createElement('input');
		allPages.type = 'checkbox';
		allPages.id = 'page-picker-all';
		allPages.checked = sourceAllPages.checked;
		allPages.setAttribute('data-modal-all-pages', 'true');
		var allPagesRow = document.createElement('div');
		allPagesRow.className = 'appnuno-check appnuno-page-all-choice';
		var allPagesLabel = document.createElement('label');
		allPagesLabel.className = 'appnuno-check-label';
		allPagesLabel.htmlFor = allPages.id;
		allPagesLabel.textContent = text.text_all_pages;
		allPagesRow.appendChild(allPages);
		allPagesRow.appendChild(allPagesLabel);
		var choices = sourceList.cloneNode(true);
		choices.hidden = false;
		choices.removeAttribute('data-validation-path');
		Array.prototype.forEach.call(choices.querySelectorAll('[data-page-choice]'), function (input) {
			input.id += '-dialog';
			var label = choices.querySelector('label[for="' + input.id.replace(/-dialog$/, '') + '"]');
			if (label) {
				label.htmlFor = input.id;
			}
		});
		allPages.addEventListener('change', function () { choices.hidden = allPages.checked; });
		allPagesContainer.textContent = '';
		allPagesContainer.appendChild(allPagesRow);
		choiceContainer.textContent = '';
		choiceContainer.appendChild(choices);
		var selectAll = document.getElementById('page-picker-select-all');
		var clear = document.getElementById('page-picker-clear');
		selectAll.onclick = function () {
			allPages.checked = false;
			choices.hidden = false;
			Array.prototype.forEach.call(choices.querySelectorAll('[data-page-choice]'), function (input) { input.checked = true; });
		};
		clear.onclick = function () {
			allPages.checked = false;
			choices.hidden = false;
			Array.prototype.forEach.call(choices.querySelectorAll('[data-page-choice]'), function (input) { input.checked = false; });
		};
		choices.hidden = allPages.checked;
		pagePickerGroup = group;
		showDialog(pagePickerDialog);
	}

	function hidePagePicker() {
		hideDialog(pagePickerDialog);
	}

	document.getElementById('page-picker-apply').addEventListener('click', function () {
		if (!pagePickerGroup) {
			return;
		}
		var allPages = pagePickerGroup.querySelector('[data-all-pages]');
		var sourceList = pagePickerGroup.querySelector('[data-pages]');
		var modalAllPages = pagePickerDialog.querySelector('[data-modal-all-pages]');
		var modalChoices = pagePickerDialog.querySelectorAll('[data-page-choice]');
		allPages.checked = modalAllPages.checked;
		Array.prototype.forEach.call(sourceList.querySelectorAll('[data-page-choice]'), function (input) {
			input.checked = Array.prototype.some.call(modalChoices, function (modalInput) { return modalInput.value === input.value && modalInput.checked; });
		});
		updatePageSummary(pagePickerGroup, pagePickerGroup.querySelector('[data-page-manage]'));
		hidePagePicker();
	});

	function createVisibilityField(tab, path) {
		var group = createGroup();
		var title = createGroupLabel(text.text_tab_visible);
		var column = createGroupColumn();
		var input = document.createElement('input');
		input.type = 'hidden';
		input.id = 'appnuno-visible-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		title.htmlFor = input.id;
		input.value = tab.visible === false ? '0' : '1';
		input.setAttribute('data-tab-visibility', 'true');
		input.setAttribute('data-validation-path', path + '.visible');
		var control = createChoiceSwitch('appnuno-switch appnuno-select-switch', text.text_tab_visible, [['1', text.text_visibility_on], ['0', text.text_visibility_indicator]], input.value, 'data-value');
		control.setAttribute('data-status-switch', input.id);
		column.appendChild(control);
		column.appendChild(input);
		group.appendChild(appendFieldHelp(title, 'tab_visibility'));
		group.appendChild(column);
		bindStatusSwitch(control);
		return group;
	}

	function createCartBadgeField(path) {
		var group = createGroup();
		group.className += ' appnuno-cart-field';
		group.setAttribute('data-validation-path', path + '.cartBadge');
		var title = createGroupLabel(text.entry_cart_tab);
		var column = createGroupColumn();
		var control = createChoiceSwitch('appnuno-switch appnuno-cart-switch', text.entry_cart_tab, [['1', text.text_enabled], ['0', text.text_disabled]], '', 'data-cart-value');
		control.setAttribute('data-cart-switch', 'true');
		control.addEventListener('click', function (event) {
			var button = event.target.closest('button[data-cart-value]');
			if (!button || !control.contains(button)) {
				return;
			}
			var card = control.closest('.appnuno-tab');
			var idField = card && card.querySelector('[data-field="id"]');
			var tabId = idField ? idField.value.trim() : '';
			var enable = button.getAttribute('data-cart-value') === '1';
			if (enable) {
				cartTabId = tabId;
				Array.prototype.forEach.call(tabPanels.querySelectorAll('[data-cart-switch]'), function (other) {
					if (other !== control) {
						other.setAttribute('data-cart-on', '0');
					}
				});
			} else if (cartTabId === tabId) {
				cartTabId = '';
			}
			control.setAttribute('data-cart-on', enable ? '1' : '0');
			refreshCartSwitches();
		});
		column.appendChild(control);
		group.appendChild(appendFieldHelp(title, 'cart_tab'));
		group.appendChild(column);
		return group;
	}

	function refreshCartSwitches() {
		Array.prototype.forEach.call(tabPanels.querySelectorAll('.appnuno-tab'), function (card) {
			var switchControl = card.querySelector('[data-cart-switch]');
			var idField = card.querySelector('[data-field="id"]');
			if (!switchControl || !idField) {
				return;
			}
			var tabId = idField.value.trim();
			var marked = switchControl.getAttribute('data-cart-on');
			var enabled = marked === '1' || (marked !== '0' && tabId !== '' && tabId === cartTabId);
			if (marked === '1' && tabId) {
				cartTabId = tabId;
			}
			Array.prototype.forEach.call(switchControl.querySelectorAll('button[data-cart-value]'), function (button) {
				button.setAttribute('aria-pressed', button.getAttribute('data-cart-value') === (enabled ? '1' : '0') ? 'true' : 'false');
			});
			placeSwitchIndicator(switchControl);
			revealSwitchMotion(switchControl);
		});
	}

	function createTab(tab, path) {
		var card = document.createElement('div');
		card.className = 'appnuno-tab';
		card.setAttribute('data-validation-path', path);
		card.appendChild(createVisibilityField(tab, path));
		card.appendChild(createField(text.text_tab_id, tab.id, 'id', 'text', path + '.id'));
		card.appendChild(createIconField(tab, path));
		card.appendChild(createLabelFields(tab, path));
		card.appendChild(createUrlField(tab, path));
		card.appendChild(createCartBadgeField(path));
		card.appendChild(createPagesField(tab, path));
		card.addEventListener('input', function (event) {
			var switchControl = card.querySelector('[data-cart-switch]');
			if (event.target.matches('[data-field="id"]') && switchControl.querySelector('[data-cart-value="1"][aria-pressed="true"]')) {
				cartTabId = event.target.value.trim();
			}
			refreshCartSwitches();
			refreshTabNavigation();
		});
		card.addEventListener('change', refreshTabNavigation);
		return card;
	}

	function addTab(container, tab, pathRoot) {
		var path = pathRoot + '[' + tabPanels.querySelectorAll('.appnuno-tab').length + ']';
		var card = createTab(tab || {}, path);
		var key = 'appnuno-tab-' + tabSequence;
		tabSequence += 1;
		card.id = key;
		card.setAttribute('data-tab-key', key);
		tabPanels.appendChild(card);
		var tabRow = document.createElement('li');
		tabRow.setAttribute('role', 'presentation');
		var link = document.createElement('a');
		link.href = '#' + key;
	link.setAttribute('data-tab-target', key);
	link.setAttribute('role', 'tab');
	link.setAttribute('aria-controls', key);
	link.addEventListener('click', function (event) {
		event.preventDefault();
		activateTab(card);
	});
		var remove = document.createElement('span');
		remove.innerHTML = appnunoIcon('trash');
		remove.setAttribute('data-remove-tab', 'true');
		remove.setAttribute('role', 'button');
		remove.setAttribute('tabindex', '0');
		remove.setAttribute('data-appnuno-tooltip', text.button_remove_tab);
		remove.setAttribute('aria-label', text.button_remove_tab);
		function removeTab(event) {
			event.preventDefault();
			event.stopPropagation();
			var wasActive = card.classList.contains('active');
			var idField = card.querySelector('[data-field="id"]');
			if (idField && cartTabId === idField.value.trim()) {
				cartTabId = '';
			}
			tabRow.parentNode.removeChild(tabRow);
			card.parentNode.removeChild(card);
			var next = tabPanels.querySelector('.appnuno-tab');
			if (next && wasActive) {
				activateTab(next);
			}
			var nextLink = tabNavigation.querySelector('[aria-selected="true"]');
			(nextLink || addTabButton).focus();
			refreshCartSwitches();
			refreshTabNavigation();
		}
		remove.addEventListener('click', removeTab);
		remove.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				removeTab(event);
			}
		});
		var iconSlot = document.createElement('span');
		iconSlot.className = 'appnuno-tab-icon';
		iconSlot.setAttribute('data-tab-icon', 'true');
		var title = document.createElement('span');
		title.setAttribute('data-tab-title', 'true');
		link.appendChild(iconSlot);
		link.appendChild(title);
		link.appendChild(remove);
		tabRow.appendChild(link);
		tabNavigation.appendChild(tabRow);
		activateTab(card);
		Array.prototype.forEach.call(card.querySelectorAll('[data-status-switch]'), function (control) {
			bindStatusSwitch(control);
			placeSwitchIndicator(control);
			revealSwitchMotion(control);
		});
		refreshTabNavigation();
		refreshCartSwitches();
	}

	function readTabs(container) {
		return Array.prototype.map.call(container.querySelectorAll('.appnuno-tab'), function (card) {
			var tab = {};
			Array.prototype.forEach.call(card.querySelectorAll('[data-field]'), function (field) {
				if (field.value.trim()) {
					tab[field.getAttribute('data-field')] = field.value.trim();
				}
			});
			if (tab.icon) {
				try {
					tab.icon = JSON.parse(tab.icon);
				} catch (error) {
					delete tab.icon;
				}
			}
			var labels = {};
			Array.prototype.forEach.call(card.querySelectorAll('[data-language-label]'), function (field) {
				if (field.value.trim()) {
					labels[field.getAttribute('data-language-label')] = field.value.trim();
				}
			});
			tab.labels = labels;
			var destination = card.querySelector('[data-url-select]');
			if (destination && destination.value === '__custom__') {
				var urls = {};
				Array.prototype.forEach.call(card.querySelectorAll('[data-language-url]'), function (field) {
					urls[field.getAttribute('data-language-url')] = localUrl(field.value) || field.value.trim();
				});
				tab.urls = urls;
				tab.url = urls[defaultLanguageCode] || urls.default || Object.keys(urls).map(function (languageCode) { return urls[languageCode]; })[0] || '';
			} else if (destination && destination.value) {
				tab.url = destination.value;
				delete tab.urls;
			}
			var visibilityField = card.querySelector('[data-tab-visibility]');
			tab.visible = visibilityField.value === '1';
			var pagesField = card.querySelector('[data-pages]');
			var allPagesField = card.querySelector('[data-all-pages]');
			if (pagesField && allPagesField) {
				tab.allPages = allPagesField.checked;
				tab.pages = [];
				Array.prototype.forEach.call(pagesField.querySelectorAll('[data-page-choice]:checked'), function (input) {
					tab.pages.push(input.value);
				});
			}
			return tab;
		});
	}

	function setValue(id, value) {
		var element = document.getElementById(id);
		if (element) {
			element.value = value === null || typeof value === 'undefined' ? '' : String(value);
		}
	}

	function setEnabled(id, enabled) {
		setValue(id, enabled ? '1' : '0');
	}

	(configuration.defaultTabs || []).forEach(function (tab) { addTab(defaultContainer, tab, 'defaultTabs'); });
	Object.keys(configuration.routes || {}).forEach(function (route) {
		(configuration.routes[route] || []).forEach(function (tab) {
			var imported = JSON.parse(JSON.stringify(tab || {}));
			var pages = Array.isArray(imported.pages) ? imported.pages.slice() : [];
			if (pages.indexOf(route) === -1) {
				pages.push(route);
			}
			imported.allPages = false;
			imported.pages = pages;
			addTab(defaultContainer, imported, 'defaultTabs');
		});
	});
	var firstDefaultTab = tabPanels.querySelector('.appnuno-tab');
	if (firstDefaultTab) {
		activateTab(firstDefaultTab);
	}
	refreshTabNavigation();
	document.querySelectorAll('[data-add-tab]').forEach(function (button) {
		button.addEventListener('click', function () { addTab(document.getElementById(button.getAttribute('data-add-tab')), {}, 'defaultTabs'); });
	});

	var sectionSwitch = document.querySelector('.appnuno-section-switch');
	if (sectionSwitch) {
		placeSwitchIndicator(sectionSwitch);
		revealSwitchMotion(sectionSwitch);
	}
	if (window.ResizeObserver) {
		var switchResizeObserver = new ResizeObserver(function (entries) {
			Array.prototype.forEach.call(entries, function (entry) {
				placeSwitchIndicator(entry.target);
			});
		});
		Array.prototype.forEach.call(document.querySelectorAll('.appnuno-switch'), function (control) {
			switchResizeObserver.observe(control);
		});
	}
	function showSectionLink(link) {
		var pane = document.querySelector(link.getAttribute('href'));
		if (pane && pane.parentNode) {
			Array.prototype.forEach.call(pane.parentNode.children, function (child) {
				if (child.classList && child.classList.contains('appnuno-section')) {
					var selected = child === pane;
					child.classList.toggle('active', selected);
					child.classList.toggle('appnuno-section-open', selected);
					var display = 'none';
					if (selected) {
						display = child.id === 'tab-navigation' ? 'block' : 'grid';
					}
					child.style.setProperty('display', display, 'important');
				}
			});
			Array.prototype.forEach.call(pane.querySelectorAll('.appnuno-switch'), function (control) {
				placeSwitchIndicator(control);
				revealSwitchMotion(control);
			});
		}
		Array.prototype.forEach.call(link.parentNode.parentNode.children, function (item) {
			item.classList.toggle('active', item === link.parentNode);
		});
		placeSwitchIndicator(link.closest('.appnuno-section-switch'));
	}
	document.addEventListener('click', function (event) {
		var source = event.target;
		if (source && source.nodeType !== 1) {
			source = source.parentElement;
		}
		var link = source && source.closest ? source.closest('a') : null;
		if (!link || !link.closest('.appnuno-section-switch')) {
			return;
		}
		event.preventDefault();
		showSectionLink(link);
	}, true);
	window.addEventListener('resize', function () {
		window.requestAnimationFrame(function () {
			Array.prototype.forEach.call(document.querySelectorAll('.appnuno-switch'), placeSwitchIndicator);
		});
	});
	window.addEventListener('load', function () {
		Array.prototype.forEach.call(document.querySelectorAll('.appnuno-switch'), placeSwitchIndicator);
	});

	setEnabled('back-enabled', !configuration.back || configuration.back.enabled !== false);
	setValue('back-placement', configuration.back && configuration.back.placement || 'separate');
	var glass = configuration.glass || {};
	setEnabled('glass-enabled', glass.enabled !== false);
	setValue('glass-color-scheme', glass.colorScheme || 'auto');
	setValue('glass-style', glass.style || 'regular');
	setEnabled('glass-animate', glass.animate !== false);
	setValue('glass-duration', typeof glass.animationDuration === 'number' ? glass.animationDuration : 0.3);
	setEnabled('glass-interactive', glass.isInteractive !== false);
	var tintColor = glass.tintColor || 'rgba(0, 0, 0, 0.02)';
	var tintHex = '#000000';
	var tintAlpha = 0.02;
	var tintRgb = tintColor.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?\s*\)$/i);
	if (tintRgb) {
		tintHex = '#' + [tintRgb[1], tintRgb[2], tintRgb[3]].map(function (channel) { return ('0' + Number(channel).toString(16)).slice(-2); }).join('');
		if (typeof tintRgb[4] !== 'undefined') {
			tintAlpha = Number(tintRgb[4]);
		}
	} else if (/^#[0-9A-Fa-f]{6}$/.test(tintColor)) {
		tintHex = tintColor;
		tintAlpha = 1;
	}
	setValue('glass-tint-color', tintHex);
	setValue('glass-tint-alpha', tintAlpha);
	setValue('light-background', configuration.colors && configuration.colors.light && configuration.colors.light.backgroundColor || '#FFFFFF');
	setValue('light-content', configuration.colors && configuration.colors.light && configuration.colors.light.contentColor || '#2C3136');
	setValue('dark-background', configuration.colors && configuration.colors.dark && configuration.colors.dark.backgroundColor || '#2C3136');
	setValue('dark-content', configuration.colors && configuration.colors.dark && configuration.colors.dark.contentColor || '#FFFFFF');
	['back-enabled', 'back-placement', 'glass-enabled', 'glass-color-scheme', 'glass-style', 'glass-animate', 'glass-interactive'].forEach(enhanceSelectSwitch);

	function syncConditionalFields() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-show-when]'), function (group) {
			var control = document.getElementById(group.getAttribute('data-show-when'));
			group.hidden = !control || control.value !== '1';
			if (!group.hidden) {
				Array.prototype.forEach.call(group.querySelectorAll('.appnuno-switch'), placeSwitchIndicator);
			}
		});
	}
	['back-enabled', 'glass-enabled'].forEach(function (id) {
		var control = document.getElementById(id);
		if (control) {
			control.addEventListener('change', syncConditionalFields);
		}
	});
	syncConditionalFields();
	refreshCartSwitches();
	[
		['back-enabled', 'help_back_enabled'],
		['back-placement', 'help_back_placement'],
		['glass-enabled', 'help_glass_enabled'],
		['glass-color-scheme', 'help_glass_color_scheme'],
		['glass-style', 'help_glass_style'],
		['glass-animate', 'help_glass_animate'],
		['glass-duration', 'help_glass_duration'],
		['glass-interactive', 'help_glass_interactive'],
		['glass-tint-color', 'help_glass_tint'],
		['glass-tint-alpha', 'help_glass_opacity'],
		['light-background', 'help_background'],
		['light-content', 'help_content'],
		['dark-background', 'help_background'],
		['dark-content', 'help_content']
	].forEach(function (entry) { appendControlHelp(entry[0], entry[1]); });
	document.querySelectorAll('[data-status-switch]').forEach(function (control) {
		placeSwitchIndicator(control);
		revealSwitchMotion(control);
		if (document.getElementById(control.getAttribute('data-status-switch')).tagName !== 'SELECT') {
			bindStatusSwitch(control);
		}
	});

	function markValidationError(path) {
		if (!path) {
			return;
		}
		var fields = document.querySelectorAll('[data-validation-path]');
		var matched = null;
		Array.prototype.forEach.call(fields, function (field) {
			if (field.getAttribute('data-validation-path') === path) {
				matched = field;
			}
		});
		var controlPaths = {
			cartTabId: 'default-tabs',
			back: 'back-enabled',
			'back.enabled': 'back-enabled',
			'back.placement': 'back-placement',
			glass: 'glass-enabled',
			'glass.enabled': 'glass-enabled',
			'glass.colorScheme': 'glass-color-scheme',
			'glass.style': 'glass-style',
			'glass.animate': 'glass-animate',
			'glass.animationDuration': 'glass-duration',
			'glass.isInteractive': 'glass-interactive',
			'glass.tintColor': 'glass-tint-color',
			'colors.light.backgroundColor': 'light-background',
			'colors.light.contentColor': 'light-content',
			'colors.dark.backgroundColor': 'dark-background',
			'colors.dark.contentColor': 'dark-content'
		};
		if (!matched && controlPaths[path]) {
			matched = document.getElementById(controlPaths[path]);
		}
		if (!matched && path.indexOf('colors.') === 0) {
			matched = document.getElementById('light-background');
		}
		if (!matched && path.indexOf('glass.') === 0) {
			matched = document.getElementById('glass-enabled');
		}
		if (!matched && path === 'routes') {
			matched = defaultContainer;
		}
		if (!matched && path === 'defaultTabs') {
			matched = defaultContainer;
		}
		if (!matched && path === 'module_appnuno_config') {
			matched = document.getElementById('input-config-json');
		}
		if (matched) {
			var card = matched.closest('.appnuno-tab');
			if (card) {
				activateTab(card);
			}
			var section = matched.closest('#form-appnuno > .appnuno-sections > .appnuno-section');
			if (section) {
				var sectionLink = document.querySelector('.appnuno-section-list a[href="#' + section.id + '"]');
				if (sectionLink) {
					showSectionLink(sectionLink);
				}
			}
			matched.setAttribute('aria-invalid', 'true');
			matched.classList.add('appnuno-invalid');
			var group = matched;
			while (group && group.getAttribute('data-field-group') !== 'true' && !group.classList.contains('appnuno-tab') && group.id !== 'default-tabs') {
				group = group.parentNode;
			}
			group = group || matched;
			group.classList.add('appnuno-invalid');
			if (typeof group.scrollIntoView === 'function') {
				group.scrollIntoView({ block: 'center' });
			}
			if (typeof matched.focus === 'function') {
				matched.focus();
			}
		}
	}

	markValidationError(validationPath);

	document.getElementById('form-appnuno').addEventListener('submit', function () {
		function enabledValue(id) {
			return document.getElementById(id).value === '1';
		}
		var result = {
			cartTabId: cartTabId,
			defaultTabs: readTabs(defaultContainer),
			routes: {},
			back: {
				enabled: enabledValue('back-enabled'),
				placement: document.getElementById('back-placement').value
			},
			glass: {
				enabled: enabledValue('glass-enabled'),
				colorScheme: document.getElementById('glass-color-scheme').value,
				style: document.getElementById('glass-style').value,
				animate: enabledValue('glass-animate'),
				animationDuration: Number(document.getElementById('glass-duration').value),
				isInteractive: enabledValue('glass-interactive')
			},
			colors: { light: {}, dark: {} }
		};
		var tintHexValue = document.getElementById('glass-tint-color').value;
		var tintAlphaValue = Number(document.getElementById('glass-tint-alpha').value);
		result.glass.tintColor = 'rgba(' + parseInt(tintHexValue.slice(1, 3), 16) + ', ' + parseInt(tintHexValue.slice(3, 5), 16) + ', ' + parseInt(tintHexValue.slice(5, 7), 16) + ', ' + tintAlphaValue + ')';
		[['light-background', 'light', 'backgroundColor'], ['light-content', 'light', 'contentColor'], ['dark-background', 'dark', 'backgroundColor'], ['dark-content', 'dark', 'contentColor']].forEach(function (entry) {
			var value = document.getElementById(entry[0]).value.trim();
			if (value) {
				result.colors[entry[1]][entry[2]] = value;
			}
		});
		if (!Object.keys(result.colors.light).length && !Object.keys(result.colors.dark).length) {
			delete result.colors;
		}
		document.getElementById('input-config-json').value = JSON.stringify(result);
	});

	function fillWatermark() {
		var host = document.querySelector('.appnuno-watermark');
		if (!host || !host.parentElement) {
			return;
		}
		var header = host.parentElement.getBoundingClientRect();
		var cover = Math.ceil(Math.hypot(header.width, header.height));
		var plane = host.querySelector('.appnuno-watermark-plane');
		if (!plane) {
			plane = document.createElement('div');
			plane.className = 'appnuno-watermark-plane';
			host.appendChild(plane);
		}
		var probe = document.createElement('span');
		probe.textContent = 'APPNUNO ';
		host.appendChild(probe);
		var phraseWidth = probe.getBoundingClientRect().width || 120;
		host.removeChild(probe);
		var copies = Math.ceil(cover / phraseWidth) + 4;
		var rows = Math.ceil(cover / 22) + 4;
		var signature = String(rows) + ':' + String(copies) + ':' + String(Math.round(phraseWidth * 100));
		if (host.getAttribute('data-watermark') === signature) {
			return;
		}
		host.setAttribute('data-watermark', signature);
		if (host.watermarkFrame) {
			cancelAnimationFrame(host.watermarkFrame);
			host.watermarkFrame = 0;
		}
		plane.textContent = '';
		var tracks = [];
		var rowIndex;
		for (rowIndex = 0; rowIndex < rows; rowIndex += 1) {
			var row = document.createElement('div');
			row.className = 'appnuno-watermark-row';
			var copyIndex;
			for (copyIndex = 0; copyIndex < copies; copyIndex += 1) {
				var piece = document.createElement('span');
				piece.textContent = 'APPNUNO ';
				piece.style.width = phraseWidth + 'px';
				row.appendChild(piece);
			}
			var track = { el: row, x: -((rowIndex * 0.6180339887) % 1) * phraseWidth };
			row.style.transform = 'translateX(' + track.x + 'px)';
			plane.appendChild(row);
			tracks.push(track);
		}
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		var last = 0;
		function step(now) {
			if (!last) {
				last = now;
			}
			var elapsed = now - last;
			last = now;
			if (elapsed > 50) {
				elapsed = 50;
			}
			var distance = phraseWidth * elapsed / 10000;
			var index;
			for (index = 0; index < tracks.length; index += 1) {
				var track = tracks[index];
				track.x -= distance;
				while (track.x <= -phraseWidth) {
					track.x += phraseWidth;
					var first = track.el.firstElementChild;
					if (first) {
						track.el.appendChild(first);
					}
				}
				track.el.style.transform = 'translateX(' + track.x + 'px)';
			}
			host.watermarkFrame = requestAnimationFrame(step);
		}
		host.watermarkFrame = requestAnimationFrame(step);
	}

	fillWatermark();
	window.addEventListener('resize', fillWatermark);
}());
