<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <link href="view/javascript/appnuno/settings.css" rel="stylesheet" />
  <link href="view/javascript/appnuno/adapter.css" rel="stylesheet" />
  <div class="page-header appnuno-host-header">
    <div class="container-fluid">
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid appnuno-page">
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
    <div class="panel panel-default appnuno-settings">
      <div class="panel-heading appnuno-toolbar">
        <div class="appnuno-watermark" aria-hidden="true"></div>
        <div class="appnuno-brand">
          <img class="appnuno-brand-mark" src="https://appnuno.com/logo-dark.png" alt="AppNuno">
        </div>
        <div class="appnuno-toolbar-actions appnuno-button-group">
          <a href="<?php echo $cancel; ?>" class="btn btn-default"><?php echo $button_cancel; ?></a>
          <button type="submit" form="form-appnuno" class="btn btn-primary"><?php echo $button_save; ?></button>
        </div>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-appnuno" class="form-horizontal">
          <div class="form-group appnuno-status-field">
            <label class="col-sm-2 control-label" id="input-status-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10">
              <div class="appnuno-switch" data-status-switch="input-status">
                <span class="appnuno-switch-indicator" aria-hidden="true"></span>
                <?php if ($module_appnuno_status) { ?>
                <button type="button" data-value="1" aria-pressed="true"><?php echo $text_enabled; ?></button>
                <button type="button" data-value="0" aria-pressed="false"><?php echo $text_disabled; ?></button>
                <?php } else { ?>
                <button type="button" data-value="1" aria-pressed="false"><?php echo $text_enabled; ?></button>
                <button type="button" data-value="0" aria-pressed="true"><?php echo $text_disabled; ?></button>
                <?php } ?>
              </div>
              <?php if ($module_appnuno_status) { ?>
              <input type="hidden" name="module_appnuno_status" id="input-status" value="1" />
              <?php } else { ?>
              <input type="hidden" name="module_appnuno_status" id="input-status" value="0" />
              <?php } ?>
            </div>
          </div>
          <input type="hidden" name="module_appnuno_config" id="input-config-json" value="" />
          <div class="appnuno-switch appnuno-section-switch">
            <span class="appnuno-switch-indicator" aria-hidden="true"></span>
            <ul class="nav nav-tabs">
              <li class="active"><a href="#tab-navigation" data-toggle="tab"><?php echo $text_default_tabs; ?></a></li>
              <li><a href="#tab-glass" data-toggle="tab"><?php echo $text['text_glass']; ?></a></li>
              <li><a href="#tab-colors" data-toggle="tab"><?php echo $text['text_navigation_colors']; ?></a></li>
            </ul>
          </div>
          <div class="tab-content">
            <div class="tab-pane active" id="tab-navigation">
              <div id="default-tabs"></div>
            </div>
            <div class="tab-pane" id="tab-glass">
              <fieldset class="appnuno-fieldset">
              <legend><?php echo $text['text_back_button']; ?></legend>
              <div class="appnuno-fieldset-body">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="back-enabled"><?php echo $text['entry_back_enabled']; ?></label>
                <div class="col-sm-10">
                  <select id="back-enabled" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="back-enabled">
                <label class="col-sm-2 control-label" for="back-placement"><?php echo $text['entry_back_placement']; ?></label>
                <div class="col-sm-10">
                  <select id="back-placement" class="form-control">
                    <option value="separate"><?php echo $text['text_placement_separate']; ?></option>
                    <option value="bar"><?php echo $text['text_placement_bar']; ?></option>
                  </select>
                </div>
              </div>
              </div>
              </fieldset>
              <fieldset class="appnuno-fieldset">
              <legend><?php echo $text['text_glass_button']; ?></legend>
              <div class="appnuno-fieldset-body">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="glass-enabled"><?php echo $text['entry_glass_enabled']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-enabled" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-color-scheme"><?php echo $text['entry_glass_color_scheme']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-color-scheme" class="form-control">
                    <option value="auto"><?php echo $text['text_color_auto']; ?></option>
                    <option value="light"><?php echo $text['text_color_light']; ?></option>
                    <option value="dark"><?php echo $text['text_color_dark']; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-style"><?php echo $text['entry_glass_style']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-style" class="form-control">
                    <option value="regular"><?php echo $text['text_style_regular']; ?></option>
                    <option value="clear"><?php echo $text['text_style_clear']; ?></option>
                    <option value="none"><?php echo $text['text_style_none']; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-animate"><?php echo $text['entry_glass_animate']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-animate" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-duration"><?php echo $text['entry_glass_duration']; ?></label>
                <div class="col-sm-10">
                  <input type="number" min="0" max="10" step="0.1" id="glass-duration" class="form-control" required="required" />
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-interactive"><?php echo $text['entry_glass_interactive']; ?></label>
                <div class="col-sm-10">
                  <select id="glass-interactive" class="form-control">
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                  </select>
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-tint-color"><?php echo $text['entry_glass_tint']; ?></label>
                <div class="col-sm-10">
                  <input type="color" id="glass-tint-color" class="form-control" value="#000000" />
                </div>
              </div>
              <div class="form-group" data-show-when="glass-enabled">
                <label class="col-sm-2 control-label" for="glass-tint-alpha"><?php echo $text['entry_glass_opacity']; ?></label>
                <div class="col-sm-10">
                  <input type="number" min="0" max="1" step="0.01" id="glass-tint-alpha" class="form-control" value="0.02" required="required" />
                </div>
              </div>
              </div>
              </fieldset>
            </div>
            <div class="tab-pane" id="tab-colors">
              <fieldset class="appnuno-fieldset">
              <legend><?php echo $text['text_light_mode']; ?></legend>
              <div class="appnuno-fieldset-body">
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
              </div>
              </fieldset>
              <fieldset class="appnuno-fieldset">
              <legend><?php echo $text['text_dark_mode']; ?></legend>
              <div class="appnuno-fieldset-body">
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
              </fieldset>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="icon-picker-dialog" tabindex="-1" role="dialog" aria-labelledby="icon-picker-title">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="appnuno-modal-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="<?php echo $button_cancel; ?>"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="icon-picker-title"><?php echo $text['text_icon']; ?></h4>
      </div>
      <div class="modal-body">
        <div class="appnuno-icon-toolbar">
          <input type="search" id="icon-picker-search" class="form-control" aria-label="<?php echo htmlspecialchars($icon_catalog_search, ENT_QUOTES, 'UTF-8'); ?>" maxlength="120" placeholder="<?php echo htmlspecialchars($icon_catalog_search, ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="appnuno-icon-meta">
          <p id="icon-picker-count"></p>
          <div class="appnuno-icon-pager appnuno-button-group" hidden></div>
        </div>
        <div class="appnuno-icon-stage">
          <div id="icon-picker-grid"></div>
          <div id="icon-picker-loading" class="appnuno-icon-loading" hidden>
            <span class="appnuno-spinner" aria-hidden="true"></span>
          </div>
        </div>
        <div id="icon-picker-status" role="status"></div>
        <div class="appnuno-icon-meta appnuno-icon-meta-bottom">
          <div class="appnuno-icon-pager appnuno-button-group" hidden></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="appnuno-configuration"><?php echo $configuration_json; ?></script>
<script type="application/json" id="appnuno-text"><?php echo $text_json; ?></script>
<script type="application/json" id="appnuno-languages"><?php echo $languages_json; ?></script>
<script type="application/json" id="appnuno-destinations"><?php echo $destinations_json; ?></script>
<script type="application/json" id="appnuno-route-options"><?php echo $route_options_json; ?></script>
<script type="application/json" id="appnuno-validation-path"><?php echo $error_config_path_json; ?></script>
<script type="application/json" id="appnuno-default-language"><?php echo json_encode($default_language_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script type="text/javascript">
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
	var adminMarkup = window.bootstrap && window.bootstrap.Modal ? 'bootstrap5' : 'bootstrap3';
	defaultContainer.className = 'row';
	var tabNavigationColumn = document.createElement('div');
	tabNavigationColumn.className = 'col-sm-3';
	var tabNavigation = document.createElement('ul');
	tabNavigation.className = adminMarkup === 'bootstrap5' ? 'nav nav-pills flex-column' : 'nav nav-pills nav-stacked';
	tabNavigation.setAttribute('role', 'tablist');
	tabNavigation.setAttribute('aria-orientation', 'vertical');
	var tabPanels = document.createElement('div');
	var tabSequence = 0;
	tabPanels.className = 'col-sm-10 tab-content';
	var addTabButton = document.createElement('button');
	addTabButton.type = 'button';
	addTabButton.className = 'btn btn-primary btn-block';
	addTabButton.setAttribute('data-add-tab', 'default-tabs');
	addTabButton.innerHTML = '<i class="' + (adminMarkup === 'bootstrap5' ? 'fas fa-solid fa-plus-circle fa-circle-plus' : 'fa fa-plus-circle') + '"></i> ' + text.button_add_default_tab;
	tabNavigationColumn.appendChild(tabNavigation);
	tabNavigationColumn.appendChild(addTabButton);
	defaultContainer.appendChild(tabNavigationColumn);
	defaultContainer.appendChild(tabPanels);
	defaultContainer.setAttribute('data-validation-path', 'defaultTabs');

	function appendHelp(label, message) {
		if (!message) {
			return;
		}
		var tooltip = document.createElement('span');
		tooltip.setAttribute(adminMarkup === 'bootstrap5' ? 'data-bs-toggle' : 'data-toggle', 'tooltip');
		tooltip.setAttribute('title', message);
		tooltip.textContent = label.textContent;
		label.textContent = '';
		label.appendChild(tooltip);
	}

	function appendFieldHelp(label, key) {
		var aliases = { id: 'tab_id', icon: 'icon_catalog' };
		appendHelp(label, text['help_' + (aliases[key] || key)]);
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

	function placeSwitchIndicator(control) {
		var indicator = control.querySelector('.appnuno-switch-indicator');
		var selected = control.querySelector('[aria-pressed="true"]') || control.querySelector('.nav-tabs > li.active > a, .nav-tabs > li > a.active');
		if (!indicator || !selected) {
			return;
		}
		var controlRect = control.getBoundingClientRect();
		var selectedRect = selected.getBoundingClientRect();
		var controlStyle = window.getComputedStyle(control);
		indicator.style.width = selectedRect.width + 'px';
		indicator.style.height = selectedRect.height + 'px';
		indicator.style.transform = 'translate(' + (selectedRect.left - controlRect.left - (parseFloat(controlStyle.borderLeftWidth) || 0)) + 'px, ' + (selectedRect.top - controlRect.top - (parseFloat(controlStyle.borderTopWidth) || 0)) + 'px)';
	}

	function revealSwitchMotion(control) {
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				control.setAttribute('data-placed', 'true');
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
					marker = document.createElement('i');
					marker.className = adminMarkup === 'bootstrap5' ? 'float-end fas fa-solid fa-eye-slash' : 'fa fa-eye-slash';
					marker.setAttribute('data-visibility-indicator', 'true');
					marker.setAttribute('data-appnuno-tooltip', text.text_visibility_indicator);
					marker.setAttribute('aria-label', text.text_visibility_indicator);
					button.insertBefore(marker, remove);
				}
			} else if (marker) {
				marker.parentNode.removeChild(marker);
			}
		});
	}

	function activateTab(card) {
		Array.prototype.forEach.call(tabPanels.querySelectorAll('.appnuno-tab'), function (panel) {
			var active = panel === card;
			panel.classList.toggle('active', active);
			panel.classList.toggle('show', active && adminMarkup === 'bootstrap5');
			var link = tabNavigation.querySelector('[data-tab-target="' + panel.getAttribute('data-tab-key') + '"]');
			if (link) {
				link.className = adminMarkup === 'bootstrap5' ? 'nav-link' + (active ? ' active' : '') : '';
				link.parentNode.className = active ? 'active' : '';
				link.setAttribute('aria-selected', active ? 'true' : 'false');
			}
		});
	}

	function appendControlHelp(id, key) {
		var control = document.getElementById(id);
		if (!control) {
			return;
		}
		var group = control.parentNode.parentNode;
		var label = group.querySelector('label');
		appendHelp(label, text[key]);
	}

	function createGroup() {
		var group = document.createElement('div');
		group.className = adminMarkup === 'bootstrap5' ? 'row mb-3' : 'form-group';
		group.setAttribute('data-field-group', 'true');
		return group;
	}

	function createGroupLabel(label, helpKey) {
		var title = document.createElement('label');
		title.className = adminMarkup === 'bootstrap5' ? 'col-sm-3 col-form-label' : 'col-sm-3 control-label';
		title.textContent = label;
		appendFieldHelp(title, helpKey || '');
		return title;
	}

	function createGroupColumn() {
		var column = document.createElement('div');
		column.className = 'col-sm-9';
		return column;
	}

	var iconPickerTarget = null;
	var iconPickerStatus = document.getElementById('icon-picker-status');
	var iconPickerGrid = document.getElementById('icon-picker-grid');
	var iconPickerSearch = document.getElementById('icon-picker-search');
	var iconPickerPage = 1;
	var iconPickerCount = document.getElementById('icon-picker-count');
	var iconPickerLoading = document.getElementById('icon-picker-loading');
	var iconPickerPageSize = 130;
	var iconPickerTimer = null;
	var iconPickerRequest = 0;
	var iconPickerCatalog = null;
	var iconPickerCatalogQuery = null;
	var iconPickerDialog = document.getElementById('icon-picker-dialog');

	function showIconPicker() {
		if (window.bootstrap && window.bootstrap.Modal) {
			window.bootstrap.Modal.getOrCreateInstance(iconPickerDialog).show();
		} else {
			window.jQuery(iconPickerDialog).modal('show');
		}
	}

	function hideIconPicker() {
		if (window.bootstrap && window.bootstrap.Modal) {
			window.bootstrap.Modal.getOrCreateInstance(iconPickerDialog).hide();
		} else {
			window.jQuery(iconPickerDialog).modal('hide');
		}
	}

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
		button.textContent = icon ? icon.id : text.text_icon;
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
			option.className = 'btn appnuno-icon-option';
			option.title = icon.id;
			option.setAttribute('aria-label', icon.id);
			option.setAttribute('data-appnuno-tooltip', icon.id);
			var image = document.createElement('img');
			image.src = icon.uri;
			image.alt = '';
			image.width = 28;
			image.height = 28;
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
				button.className = 'btn btn-default';
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

	function presentIconPage(icons, currentPage, lastPage, total, local) {
		var page = Math.min(Math.max(currentPage, 1), Math.max(lastPage, 1));
		var visible = local ? icons.slice((page - 1) * iconPickerPageSize, page * iconPickerPageSize) : icons;
		iconPickerPage = page;
		renderIconOptions(visible);
		iconPickerCount.textContent = String(text.text_icon_catalog_total || '%s').replace('%s', String(total));
		renderIconPager(page, total ? lastPage : 0);
		iconPickerLoading.hidden = true;
		iconPickerStatus.textContent = '';
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
		iconPickerLoading.hidden = false;
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
				iconPickerLoading.hidden = true;
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
		button.className = 'btn btn-default';
		button.setAttribute('data-icon-picker', 'true');
		button.addEventListener('click', function () {
			iconPickerTarget = input;
			showIconPicker();
			loadIconCatalog();
		});
		column.appendChild(input);
		column.appendChild(button);
		group.appendChild(title);
		group.appendChild(column);
		updateIconField(input, selected);
		appendFieldHelp(title, 'icon');
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
		input.className = 'form-control';
		input.value = value || '';
		input.setAttribute('data-field', key);
		input.setAttribute('data-validation-path', path);
		column.appendChild(input);
		appendFieldHelp(title, key);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createLabelFields(tab, path) {
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		group.setAttribute('data-validation-path', path + '.labels');
		var title = createGroupLabel(text.text_language_labels, 'tab_labels');
		var column = createGroupColumn();
		var labels = tab.labels || {};
		var legacyLabel = tab.label || '';
		var legacyAssigned = false;
		languages.forEach(function (language) {
			var inputGroup = document.createElement('div');
			inputGroup.className = 'input-group';
			var flag = document.createElement('span');
			flag.className = adminMarkup === 'bootstrap5' ? 'input-group-text' : 'input-group-addon';
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
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		group.setAttribute('data-validation-path', path + '.url');
		var title = createGroupLabel(text.text_destination, 'tab_destination');
		var column = createGroupColumn();
		var select = document.createElement('select');
		select.id = 'appnuno-destination-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		title.htmlFor = select.id;
		select.className = 'form-control';
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
			inputGroup.className = 'input-group';
			var flag = document.createElement('span');
			flag.className = adminMarkup === 'bootstrap5' ? 'input-group-text' : 'input-group-addon';
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
		group.appendChild(title);
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
		if (adminMarkup === 'bootstrap5') {
			row.className = 'form-check';
			input.className = 'form-check-input';
			input.id = 'appnuno-page-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-') + '-' + String(value).replace(/[^A-Za-z0-9_-]/g, '-');
			var caption = document.createElement('label');
			caption.className = 'form-check-label';
			caption.htmlFor = input.id;
			caption.textContent = label;
			row.appendChild(input);
			row.appendChild(caption);
		} else {
			row.className = 'checkbox';
			var caption = document.createElement('label');
			caption.appendChild(input);
			caption.appendChild(document.createTextNode(' ' + label));
			row.appendChild(caption);
		}
		list.appendChild(row);
	}

	function createPagesField(tab, path) {
		var group = createGroup();
		group.className += ' appnuno-field-wide';
		var title = createGroupLabel(text.text_shown_on_pages, 'shown_on_pages');
		var column = createGroupColumn();
		var allPagesChoice = document.createElement('div');
		allPagesChoice.className = adminMarkup === 'bootstrap5' ? 'form-check' : 'checkbox';
		var allPagesLabel = document.createElement('label');
		var allPages = document.createElement('input');
		allPages.type = 'checkbox';
		allPages.id = 'appnuno-all-pages-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		allPagesLabel.htmlFor = allPages.id;
		title.htmlFor = allPages.id;
		allPages.checked = typeof tab.allPages === 'boolean' ? tab.allPages : (!Array.isArray(tab.pages) || !tab.pages.length);
		allPages.setAttribute('data-all-pages', 'true');
		allPages.setAttribute('data-validation-path', path + '.allPages');
		if (adminMarkup === 'bootstrap5') {
			allPages.className = 'form-check-input';
			allPagesLabel.className = 'form-check-label';
			allPagesLabel.appendChild(document.createTextNode(text.text_all_pages));
			allPagesChoice.appendChild(allPages);
			allPagesChoice.appendChild(allPagesLabel);
		} else {
			allPagesLabel.appendChild(allPages);
			allPagesLabel.appendChild(document.createTextNode(' ' + text.text_all_pages));
			allPagesChoice.appendChild(allPagesLabel);
		}
		var list = document.createElement('div');
		list.className = 'appnuno-page-list';
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
		var actions = document.createElement('div');
		actions.className = 'btn-group appnuno-button-group';
		var selectAll = document.createElement('button');
		selectAll.type = 'button';
		selectAll.className = 'btn btn-default btn-sm';
		selectAll.setAttribute('data-page-action', 'select-all');
		selectAll.textContent = text.button_select_all_pages;
		selectAll.addEventListener('click', function () {
			Array.prototype.forEach.call(list.querySelectorAll('input[type="checkbox"]'), function (input) { input.checked = true; });
		});
		var clearButton = document.createElement('button');
		clearButton.type = 'button';
		clearButton.className = 'btn btn-default btn-sm';
		clearButton.setAttribute('data-page-action', 'deselect-all');
		clearButton.textContent = text.button_clear_pages;
		clearButton.addEventListener('click', function () {
			Array.prototype.forEach.call(list.querySelectorAll('input[type="checkbox"]'), function (input) { input.checked = false; });
		});
		actions.appendChild(selectAll);
		actions.appendChild(clearButton);
		allPages.addEventListener('change', function () {
			list.style.display = allPages.checked ? 'none' : '';
			actions.style.display = allPages.checked ? 'none' : '';
		});
		list.style.display = allPages.checked ? 'none' : '';
		actions.style.display = allPages.checked ? 'none' : '';
		column.appendChild(allPagesChoice);
		column.appendChild(actions);
		column.appendChild(list);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createVisibilityField(tab, path) {
		var group = createGroup();
		var title = createGroupLabel(text.text_tab_visible, 'tab_visibility');
		var column = createGroupColumn();
		var input = document.createElement('select');
		input.className = 'form-control';
		input.id = 'appnuno-visible-' + String(path).replace(/[^A-Za-z0-9_-]/g, '-');
		title.htmlFor = input.id;
		input.appendChild(new Option(text.text_visibility_on, '1'));
		input.appendChild(new Option(text.text_visibility_indicator, '0'));
		input.value = tab.visible === false ? '0' : '1';
		input.setAttribute('data-tab-visibility', 'true');
		input.setAttribute('data-validation-path', path + '.visible');
		column.appendChild(input);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function createCartBadgeField(path) {
		var group = createGroup();
		group.className += ' appnuno-cart-field';
		group.setAttribute('data-validation-path', path + '.cartBadge');
		var title = createGroupLabel(text.entry_cart_tab, 'cart_tab');
		var column = createGroupColumn();
		var control = document.createElement('div');
		control.className = 'appnuno-switch appnuno-cart-switch';
		control.setAttribute('data-cart-switch', 'true');
		control.setAttribute('role', 'group');
		control.setAttribute('aria-label', text.entry_cart_tab);
		var indicator = document.createElement('span');
		indicator.className = 'appnuno-switch-indicator';
		indicator.setAttribute('aria-hidden', 'true');
		control.appendChild(indicator);
		[['1', text.text_enabled], ['0', text.text_disabled]].forEach(function (option) {
			var button = document.createElement('button');
			button.type = 'button';
			button.setAttribute('data-cart-value', option[0]);
			button.setAttribute('aria-pressed', 'false');
			button.textContent = option[1];
			control.appendChild(button);
		});
		control.addEventListener('click', function (event) {
			var button = event.target.closest('button[data-cart-value]');
			if (!button) {
				return;
			}
			var idField = group.closest('.appnuno-tab').querySelector('[data-field="id"]');
			var tabId = idField.value.trim();
			if (button.getAttribute('data-cart-value') === '1') {
				if (tabId) {
					cartTabId = tabId;
				}
			} else if (cartTabId === tabId) {
				cartTabId = '';
			}
			refreshCartSwitches();
		});
		column.appendChild(control);
		group.appendChild(title);
		group.appendChild(column);
		return group;
	}

	function refreshCartSwitches() {
		Array.prototype.forEach.call(tabPanels.querySelectorAll('.appnuno-tab'), function (card) {
			var switchControl = card.querySelector('[data-cart-switch]');
			var idField = card.querySelector('[data-field="id"]');
			var enabled = !!idField && idField.value.trim() === cartTabId && !!cartTabId;
			Array.prototype.forEach.call(switchControl.querySelectorAll('button[data-cart-value]'), function (button) {
				button.setAttribute('aria-pressed', button.getAttribute('data-cart-value') === (enabled ? '1' : '0') ? 'true' : 'false');
			});
			placeSwitchIndicator(switchControl);
			revealSwitchMotion(switchControl);
		});
	}

	function createTab(tab, path) {
		var card = document.createElement('div');
		card.className = 'tab-pane appnuno-tab';
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
		tabRow.className = adminMarkup === 'bootstrap5' ? 'nav-item' : '';
		tabRow.setAttribute('role', 'presentation');
		var link = document.createElement('a');
		link.href = '#' + key;
		link.className = adminMarkup === 'bootstrap5' ? 'nav-link' : '';
		link.setAttribute(adminMarkup === 'bootstrap5' ? 'data-bs-toggle' : 'data-toggle', 'tab');
		link.setAttribute('data-tab-target', key);
		link.setAttribute('role', 'tab');
		link.setAttribute('aria-controls', key);
		link.addEventListener('click', function () { activateTab(card); });
		var remove = document.createElement('i');
		remove.className = adminMarkup === 'bootstrap5' ? 'fas fa-solid fa-trash fa-trash-can' : 'fa fa-trash';
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
					urls[field.getAttribute('data-language-url')] = field.value.trim();
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
	document.querySelectorAll('[data-add-tab]').forEach(function (button) {
		button.addEventListener('click', function () { addTab(document.getElementById(button.getAttribute('data-add-tab')), {}, 'defaultTabs'); });
	});

	var sectionSwitch = document.querySelector('.appnuno-section-switch');
	if (sectionSwitch) {
		placeSwitchIndicator(sectionSwitch);
		revealSwitchMotion(sectionSwitch);
	}
	document.querySelectorAll('[data-status-switch]').forEach(function (control) {
		var input = document.getElementById(control.getAttribute('data-status-switch'));
		placeSwitchIndicator(control);
		revealSwitchMotion(control);
		control.addEventListener('click', function (event) {
			var button = event.target.closest('button[data-value]');
			if (!button || !input) {
				return;
			}
			input.value = button.getAttribute('data-value');
			Array.prototype.forEach.call(control.querySelectorAll('button[data-value]'), function (item) {
				item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
			});
			placeSwitchIndicator(control);
		});
	});
	function showSectionLink(link) {
		var pane = document.querySelector(link.getAttribute('href'));
		if (pane && pane.parentNode) {
			Array.prototype.forEach.call(pane.parentNode.children, function (child) {
				if (child.classList && child.classList.contains('tab-pane')) {
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
		}
		Array.prototype.forEach.call(link.parentNode.parentNode.children, function (item) {
			item.classList.toggle('active', item === link.parentNode);
		});
		placeSwitchIndicator(link.closest('.appnuno-section-switch'));
	}
	var sectionTimer = 0;
	document.addEventListener('click', function (event) {
		var source = event.target;
		if (source && source.nodeType !== 1) {
			source = source.parentElement;
		}
		var link = source && source.closest ? source.closest('a') : null;
		if (!link || !link.closest('.appnuno-section-switch')) {
			return;
		}
		showSectionLink(link);
		window.clearTimeout(sectionTimer);
		sectionTimer = window.setTimeout(function () { showSectionLink(link); }, 250);
	}, true);
	window.addEventListener('resize', function () {
		Array.prototype.forEach.call(document.querySelectorAll('.appnuno-switch'), placeSwitchIndicator);
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
	function syncConditionalFields() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-show-when]'), function (group) {
			var control = document.getElementById(group.getAttribute('data-show-when'));
			group.hidden = !control || control.value !== '1';
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
		['input-status', 'help_status'],
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
			var section = matched.closest('#form-appnuno > .tab-content > .tab-pane');
			if (section) {
				var sectionLink = document.querySelector('.nav-tabs a[href="#' + section.id + '"]');
				if (adminMarkup === 'bootstrap5') {
					window.bootstrap.Tab.getOrCreateInstance(sectionLink).show();
				} else {
					window.jQuery(sectionLink).tab('show');
				}
				window.requestAnimationFrame(function () { placeSwitchIndicator(sectionSwitch); });
			}
			matched.setAttribute('aria-invalid', 'true');
			var group = matched;
			while (group && group.getAttribute('data-field-group') !== 'true' && !group.classList.contains('appnuno-tab') && group.id !== 'default-tabs') {
				group = group.parentNode;
			}
			group = group || matched;
			if (adminMarkup === 'bootstrap5') {
				matched.classList.add('is-invalid');
			} else {
				group.className += ' has-error';
			}
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
</script>
<?php echo $footer; ?>
