<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-appnuno" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($success) { ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if ($error_config) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_config, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-appnuno" class="form-horizontal">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10">
              <select name="module_appnuno_status" id="input-status" class="form-control">
                <?php if ($module_appnuno_status) { ?>
                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                <option value="0"><?php echo $text_disabled; ?></option>
                <?php } else { ?>
                <option value="1"><?php echo $text_enabled; ?></option>
                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <input type="hidden" name="module_appnuno_config" id="input-config-json" value="" />
          <ul class="nav nav-tabs">
            <li class="active"><a href="#tab-navigation" data-toggle="tab"><?php echo $text_default_tabs; ?></a></li>
            <li><a href="#tab-back" data-toggle="tab"><?php echo $text['text_back_button']; ?></a></li>
            <li><a href="#tab-glass" data-toggle="tab"><?php echo $text['text_glass']; ?></a></li>
            <li><a href="#tab-colors" data-toggle="tab"><?php echo $text['text_navigation_colors']; ?></a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane active" id="tab-navigation">
              <div class="alert alert-info"><?php echo $text['text_default_tabs_help']; ?></div>
              <div id="default-tabs"></div>
              <button type="button" class="btn btn-default" data-add-tab="default-tabs"><i class="fa fa-plus"></i> <?php echo $text['button_add_default_tab']; ?></button>
              <hr />
              <div class="form-group">
                <label class="col-sm-2 control-label" for="cart-tab-id"><?php echo $text['entry_cart_tab']; ?></label>
                <div class="col-sm-10">
                  <select id="cart-tab-id" class="form-control"></select>
                  <p class="help-block"><?php echo $text['help_cart_tab']; ?></p>
                </div>
              </div>
            </div>
            <div class="tab-pane" id="tab-back">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="back-enabled"><?php echo $text['entry_back_enabled']; ?></label>
                <div class="col-sm-10">
                  <select id="back-enabled" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="back-placement"><?php echo $text['entry_back_placement']; ?></label>
                <div class="col-sm-10">
                  <select id="back-placement" class="form-control">
                    <option value="separate"><?php echo $text['text_placement_separate']; ?></option>
                    <option value="bar"><?php echo $text['text_placement_bar']; ?></option>
                  </select>
                </div>
              </div>
            </div>
            <div class="tab-pane" id="tab-glass">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-enabled"><?php echo $text['entry_glass_enabled']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-enabled" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-color-scheme"><?php echo $text['entry_glass_color_scheme']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-color-scheme" class="form-control">
                    <option value="auto"><?php echo $text['text_color_auto']; ?></option>
                    <option value="light"><?php echo $text['text_color_light']; ?></option>
                    <option value="dark"><?php echo $text['text_color_dark']; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-style"><?php echo $text['entry_glass_style']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-style" class="form-control">
                    <option value="regular"><?php echo $text['text_style_regular']; ?></option>
                    <option value="clear"><?php echo $text['text_style_clear']; ?></option>
                    <option value="none"><?php echo $text['text_style_none']; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-animate"><?php echo $text['entry_glass_animate']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-animate" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-duration"><?php echo $text['entry_glass_duration']; ?></label>
                <div class="col-sm-10">
                  <input type="number" min="0" max="10" step="0.1" id="glass-duration" class="form-control" required="required" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-interactive"><?php echo $text['entry_glass_interactive']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-interactive" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-tint-color"><?php echo $text['entry_glass_tint']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="glass-tint-color" class="form-control" value="#000000" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-tint-alpha"><?php echo $text['entry_glass_opacity']; ?></label>
                <div class="col-sm-10">
                  <input type="number" min="0" max="1" step="0.01" id="glass-tint-alpha" class="form-control" value="0.02" required="required" />
                </div>
              </div>
            </div>
            <div class="tab-pane" id="tab-colors">
              <h4><?php echo $text['text_light_mode']; ?></h4>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="light-background"><?php echo $text['entry_background']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="light-background" class="form-control" value="#FFFFFF" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="light-content"><?php echo $text['entry_content']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="light-content" class="form-control" value="#2C3136" />
                </div>
              </div>
              <h4><?php echo $text['text_dark_mode']; ?></h4>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="dark-background"><?php echo $text['entry_background']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="dark-background" class="form-control" value="#2C3136" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="dark-content"><?php echo $text['entry_content']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="dark-content" class="form-control" value="#FFFFFF" />
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript"><!--
(function () {
	var configuration = <?php echo $configuration_json; ?> || {};
	var text = <?php echo $text_json; ?> || {};
	var languages = <?php echo $languages_json; ?> || [];
	var destinations = <?php echo $destinations_json; ?> || [];
	var routeOptions = <?php echo $route_options_json; ?> || [];
	var validationPath = <?php echo $error_config_path_json; ?>;
	var defaultLanguageCode = <?php echo json_encode($default_language_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	var defaultContainer = document.getElementById('default-tabs');
	var cartSelect = document.getElementById('cart-tab-id');
	defaultContainer.setAttribute('data-validation-path', 'defaultTabs');

	function createField(label, value, key, type, path) {
		var group = document.createElement('div');
		group.className = 'form-group';
		var title = document.createElement('label');
		title.className = 'col-sm-2 control-label';
		title.textContent = label;
		var column = document.createElement('div');
		column.className = 'col-sm-10';
		var input = document.createElement('input');
		input.type = type || 'text';
		input.className = 'form-control';
		input.value = value || '';
		input.setAttribute('data-field', key);
		input.setAttribute('data-validation-path', path);
		column.appendChild(input);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createLabelFields(tab, path) {
		var group = document.createElement('div');
		group.className = 'form-group';
		group.setAttribute('data-validation-path', path + '.labels');
		var title = document.createElement('label');
		title.className = 'col-sm-2 control-label';
		title.textContent = text.text_language_labels;
		var column = document.createElement('div');
		column.className = 'col-sm-10';
		var labels = tab.labels || {};
		var legacyLabel = tab.label || '';
		var legacyAssigned = false;
		languages.forEach(function (language) {
			var inputGroup = document.createElement('div');
			inputGroup.className = 'input-group';
			inputGroup.style.marginBottom = '8px';
			var flag = document.createElement('span');
			flag.className = 'input-group-addon';
			if (/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(language.code)) {
				var image = document.createElement('img');
				image.src = 'language/' + language.code + '/' + language.code + '.png';
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
			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'form-control';
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
			fallback.className = 'form-control';
			fallback.maxLength = 80;
			fallback.placeholder = text.text_label;
			fallback.value = legacyLabel;
			fallback.setAttribute('data-language-label', 'default');
			fallback.setAttribute('data-validation-path', path + '.labels.default');
			column.appendChild(fallback);
		}
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createUrlField(tab, path) {
		var group = document.createElement('div');
		group.className = 'form-group';
		group.setAttribute('data-validation-path', path + '.url');
		var title = document.createElement('label');
		title.className = 'col-sm-2 control-label';
		title.textContent = text.text_destination;
		var column = document.createElement('div');
		column.className = 'col-sm-10';
		var select = document.createElement('select');
		select.className = 'form-control';
		select.setAttribute('data-url-select', 'true');
		select.appendChild(new Option(text.text_choose_destination, ''));
		destinations.forEach(function (destination) {
			select.appendChild(new Option(destination.label, destination.url));
		});
		select.appendChild(new Option(text.text_custom_url, '__custom__'));
		var customUrls = document.createElement('div');
		customUrls.className = 'appnuno-custom-urls';
		var savedUrls = tab.urls || {};
		var languageList = languages.length ? languages : [{ code: 'default', name: text.text_default_language, image: '' }];
		languageList.forEach(function (language) {
			var inputGroup = document.createElement('div');
			inputGroup.className = 'input-group';
			inputGroup.style.marginTop = '8px';
			var flag = document.createElement('span');
			flag.className = 'input-group-addon';
			if (language.code !== 'default' && /^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(language.code)) {
				var image = document.createElement('img');
				image.src = 'language/' + language.code + '/' + language.code + '.png';
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
			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'form-control';
			input.placeholder = text.text_url_placeholder.replace('%s', language.name);
			input.setAttribute('data-language-url', language.code);
			input.setAttribute('data-validation-path', path + '.urls.' + language.code);
			input.value = savedUrls[language.code] || tab.url || '';
			inputGroup.appendChild(flag);
			inputGroup.appendChild(input);
			customUrls.appendChild(inputGroup);
		});
		var hasLocalizedCustomUrls = Object.keys(savedUrls).length > 0;
		var known = !hasLocalizedCustomUrls && destinations.some(function (destination) { return destination.url === (tab.url || ''); });
		select.value = known ? tab.url : (tab.url || hasLocalizedCustomUrls ? '__custom__' : '');
		customUrls.style.display = select.value === '__custom__' ? '' : 'none';
		select.addEventListener('change', function () {
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
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createPagesField(tab, path) {
		var group = document.createElement('div');
		group.className = 'form-group';
		var title = document.createElement('label');
		title.className = 'col-sm-2 control-label';
		title.textContent = text.text_shown_on_pages;
		var column = document.createElement('div');
		column.className = 'col-sm-10';
		var select = document.createElement('select');
		select.multiple = true;
		select.size = routeOptions.length > 8 ? 8 : (routeOptions.length || 1);
		select.className = 'form-control';
		select.setAttribute('data-pages', 'true');
		select.setAttribute('data-validation-path', path + '.pages');
		var selected = {};
		(tab.pages || []).forEach(function (page) {
			selected[page] = true;
		});
		var known = {};
		routeOptions.forEach(function (option) {
			known[option.route] = true;
			var choice = new Option(option.label, option.route);
			choice.selected = !!selected[option.route];
			select.appendChild(choice);
		});
		(tab.pages || []).forEach(function (page) {
			if (!known[page]) {
				var extra = new Option(page, page);
				extra.selected = true;
				select.appendChild(extra);
			}
		});
		var help = document.createElement('p');
		help.className = 'help-block';
		help.textContent = text.help_shown_on_pages;
		column.appendChild(select);
		column.appendChild(help);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createTab(tab, path) {
		var card = document.createElement('div');
		card.className = 'well appnuno-tab';
		card.setAttribute('data-validation-path', path);
		card.appendChild(createField(text.text_tab_id, tab.id, 'id', 'text', path + '.id'));
		card.appendChild(createLabelFields(tab, path));
		card.appendChild(createUrlField(tab, path));
		card.appendChild(createField(text.text_icon, tab.icon, 'icon', 'text', path + '.icon'));
		card.appendChild(createField(text.text_active_icon, tab.activeIcon, 'activeIcon', 'text', path + '.activeIcon'));
		card.appendChild(createPagesField(tab, path));
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'btn btn-danger btn-sm';
		remove.textContent = text.button_remove_tab;
		remove.addEventListener('click', function () {
			card.parentNode.removeChild(card);
			refreshCartOptions();
		});
		card.appendChild(remove);
		card.addEventListener('input', refreshCartOptions);
		return card;
	}

	function addTab(container, tab, pathRoot) {
		var path = pathRoot + '[' + container.querySelectorAll('.appnuno-tab').length + ']';
		container.appendChild(createTab(tab || {}, path));
		refreshCartOptions();
	}

	function readTabs(container) {
		return Array.prototype.map.call(container.querySelectorAll('.appnuno-tab'), function (card) {
			var tab = {};
			Array.prototype.forEach.call(card.querySelectorAll('[data-field]'), function (field) {
				if (field.value.trim()) {
					tab[field.getAttribute('data-field')] = field.value.trim();
				}
			});
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
					urls[field.getAttribute('data-language-url')] = field.value.trim();
				});
				tab.urls = urls;
				tab.url = urls[defaultLanguageCode] || urls.default || Object.keys(urls).map(function (languageCode) { return urls[languageCode]; })[0] || '';
			} else if (destination && destination.value) {
				tab.url = destination.value;
				delete tab.urls;
			}
			var pagesField = card.querySelector('[data-pages]');
			if (pagesField) {
				var pages = [];
				Array.prototype.forEach.call(pagesField.options, function (option) {
					if (option.selected) {
						pages.push(option.value);
					}
				});
				if (pages.length) {
					tab.pages = pages;
				}
			}
			return tab;
		});
	}

	function refreshCartOptions() {
		var current = cartSelect.value || configuration.cartTabId || 'cart';
		var ids = {};
		readTabs(defaultContainer).forEach(function (tab) {
			if (tab.id) {
				ids[tab.id] = true;
			}
		});
		cartSelect.textContent = '';
		Object.keys(ids).forEach(function (id) {
			var option = document.createElement('option');
			option.value = id;
			option.textContent = id;
			cartSelect.appendChild(option);
		});
		if (!ids[current]) {
			var fallback = document.createElement('option');
			fallback.value = current;
			fallback.textContent = text.text_tab_missing.replace('%s', current);
			cartSelect.appendChild(fallback);
		}
		cartSelect.value = current;
	}

	function setValue(id, value) {
		var element = document.getElementById(id);
		if (element) {
			element.value = value === null || typeof value === 'undefined' ? '' : value;
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
			imported.pages = pages;
			addTab(defaultContainer, imported, 'defaultTabs');
		});
	});
	if (!defaultContainer.querySelector('.appnuno-tab')) {
		addTab(defaultContainer, {}, 'defaultTabs');
	}
	document.querySelectorAll('[data-add-tab]').forEach(function (button) {
		button.addEventListener('click', function () { addTab(document.getElementById(button.getAttribute('data-add-tab')), {}, 'defaultTabs'); });
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
	refreshCartOptions();
	cartSelect.value = configuration.cartTabId || cartSelect.value;

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
			cartTabId: 'cart-tab-id',
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
			var group = matched;
			while (group && !(' ' + group.className + ' ').match(/ form-group /) && group.id !== 'default-tabs') {
				group = group.parentNode;
			}
			group = group || matched;
			group.className += ' has-error';
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
			cartTabId: cartSelect.value || 'cart',
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
}());
//--></script>
<?php echo $footer; ?>
