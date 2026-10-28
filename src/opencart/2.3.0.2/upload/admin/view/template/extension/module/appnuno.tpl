<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right"><button type="submit" form="form-appnuno" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button><a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?><li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li><?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($success) { ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php } ?>
    <?php if ($error_warning) { ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php } ?>
    <?php if ($error_config) { ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_config, ENT_QUOTES, 'UTF-8'); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3></div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-appnuno" class="form-horizontal">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10"><select name="module_appnuno_status" id="input-status" class="form-control"><option value="1"<?php echo in_array($module_appnuno_status, array('1', 1), true) ? ' selected="selected"' : ''; ?>><?php echo $text_enabled; ?></option><option value="0"<?php echo in_array($module_appnuno_status, array('0', 0), true) ? ' selected="selected"' : ''; ?>><?php echo $text_disabled; ?></option></select></div>
          </div>
          <input type="hidden" name="module_appnuno_config" id="input-config-json" value="">
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?php echo $text_default_tabs; ?></h3></div>
            <div class="panel-body">
              <div class="alert alert-info">Shown on routes without a matching route-specific tab set. Tab URLs must be paths on this store, beginning with /.</div>
              <div id="default-tabs"></div>
              <button type="button" class="btn btn-default" data-add-tab="default-tabs"><i class="fa fa-plus"></i> Add default tab</button>
              <hr>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="cart-tab-id">Cart badge tab</label>
                <div class="col-sm-10"><select id="cart-tab-id" class="form-control"></select><p class="help-block">The selected tab receives the confirmed quantity of products in the cart.</p></div>
              </div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Route-specific tab sets</h3></div>
            <div class="panel-body">
              <div class="alert alert-info">Add an exact OpenCart route, for example checkout/cart or product/product. Its complete tab set replaces the defaults on that route.</div>
              <div id="client-validation-error" class="alert alert-danger" role="alert" style="display:none"></div>
              <div id="route-sets"></div>
              <button type="button" class="btn btn-default" id="add-route"><i class="fa fa-plus"></i> Add route tab set</button>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Back button</h3></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="back-enabled">Enabled</label>
                <div class="col-sm-10"><input type="checkbox" id="back-enabled"></div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="back-placement">Placement</label>
                <div class="col-sm-10"><select id="back-placement" class="form-control"><option value="separate">Separate</option><option value="bar">In tab bar</option></select></div>
              </div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Glass button bar</h3></div>
            <div class="panel-body">
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-enabled">Glass enabled</label><div class="col-sm-10"><input type="checkbox" id="glass-enabled"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-color-scheme">Color scheme</label><div class="col-sm-10"><select id="glass-color-scheme" class="form-control"><option value="auto">Automatic</option><option value="light">Light</option><option value="dark">Dark</option></select></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-style">Style</label><div class="col-sm-10"><select id="glass-style" class="form-control"><option value="regular">Regular</option><option value="clear">Clear</option><option value="none">None</option></select></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-animate">Animate changes</label><div class="col-sm-10"><input type="checkbox" id="glass-animate"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-duration">Animation duration (seconds)</label><div class="col-sm-10"><input type="number" min="0" max="10" step="0.1" id="glass-duration" class="form-control" required="required"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-interactive">Interactive glass</label><div class="col-sm-10"><input type="checkbox" id="glass-interactive"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-tint-color">Tint color</label><div class="col-sm-7"><input type="color" id="glass-tint-color" class="form-control" value="#000000"></div><div class="col-sm-3"><label for="glass-tint-alpha">Opacity</label><input type="number" min="0" max="1" step="0.01" id="glass-tint-alpha" class="form-control" value="0.02" required="required"></div></div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Navigation colors</h3></div>
            <div class="panel-body">
              <div class="row">
                <div class="col-sm-6">
                  <h4>Light mode</h4>
                  <div class="form-group"><label class="col-sm-4 control-label" for="light-background">Background</label><div class="col-sm-8"><input type="color" id="light-background" class="form-control" value="#FFFFFF"></div></div>
                  <div class="form-group"><label class="col-sm-4 control-label" for="light-content">Content</label><div class="col-sm-8"><input type="color" id="light-content" class="form-control" value="#2C3136"></div></div>
                </div>
                <div class="col-sm-6">
                  <h4>Dark mode</h4>
                  <div class="form-group"><label class="col-sm-4 control-label" for="dark-background">Background</label><div class="col-sm-8"><input type="color" id="dark-background" class="form-control" value="#2C3136"></div></div>
                  <div class="form-group"><label class="col-sm-4 control-label" for="dark-content">Content</label><div class="col-sm-8"><input type="color" id="dark-content" class="form-control" value="#FFFFFF"></div></div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
(function () {
    var configuration = <?php echo $configuration_json; ?> || {};
    var languages = <?php echo $languages_json; ?> || [];
    var destinations = <?php echo $destinations_json; ?> || [];
    var routeOptions = <?php echo $route_options_json; ?> || [];
    var validationPath = <?php echo $error_config_path_json; ?>;
    var defaultLanguageCode = <?php echo json_encode($default_language_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    var defaultContainer = document.getElementById('default-tabs');
    var routesContainer = document.getElementById('route-sets');
    var cartSelect = document.getElementById('cart-tab-id');
    defaultContainer.setAttribute('data-validation-path', 'defaultTabs');
    routesContainer.setAttribute('data-validation-path', 'routes');

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
        title.textContent = 'Label translations';
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
            fallback.placeholder = 'Label';
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
        title.textContent = 'Destination';
        var column = document.createElement('div');
        column.className = 'col-sm-10';
        var select = document.createElement('select');
        select.className = 'form-control';
        select.setAttribute('data-url-select', 'true');
        select.appendChild(new Option('Choose a store page or custom URL', ''));
        destinations.forEach(function (destination) {
            select.appendChild(new Option(destination.label, destination.url));
        });
        select.appendChild(new Option('Custom URL', '__custom__'));
        var customUrls = document.createElement('div');
        customUrls.className = 'appnuno-custom-urls';
        var savedUrls = tab.urls || {};
        var languageList = languages.length ? languages : [{ code: 'default', name: 'Default', image: '' }];
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
            input.placeholder = 'Local URL for ' + language.name + ', starting with /';
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

    function createTab(tab, path) {
        var card = document.createElement('div');
        card.className = 'well appnuno-tab';
        card.setAttribute('data-validation-path', path);
        card.appendChild(createField('Tab ID', tab.id, 'id', 'text', path + '.id'));
        card.appendChild(createLabelFields(tab, path));
        card.appendChild(createUrlField(tab, path));
        card.appendChild(createField('Icon', tab.icon, 'icon', 'text', path + '.icon'));
        card.appendChild(createField('Active icon', tab.activeIcon, 'activeIcon', 'text', path + '.activeIcon'));
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-danger btn-sm';
        remove.textContent = 'Remove tab';
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

    function createRouteField(route) {
        var group = document.createElement('div');
        group.className = 'form-group';
        var title = document.createElement('label');
        title.className = 'col-sm-2 control-label';
        title.textContent = 'OpenCart route';
        var column = document.createElement('div');
        column.className = 'col-sm-10';
        var select = document.createElement('select');
        select.className = 'form-control';
        select.appendChild(new Option('Choose a common route or custom route', ''));
        routeOptions.forEach(function (option) {
            select.appendChild(new Option(option.label + ' (' + option.route + ')', option.route));
        });
        select.appendChild(new Option('Custom route', '__custom__'));
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.placeholder = 'checkout/cart';
        input.setAttribute('data-field', 'route');
        input.setAttribute('data-validation-path', 'routes.' + (route || 'route') + '.route');
        input.value = route || '';
        var known = routeOptions.some(function (option) { return option.route === input.value; });
        select.value = known ? input.value : (input.value ? '__custom__' : '');
        input.style.display = known ? 'none' : '';
        select.addEventListener('change', function () {
            if (select.value === '__custom__') {
                input.style.display = '';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = select.value;
            }
        });
        column.appendChild(select);
        column.appendChild(input);
        group.appendChild(title);
        group.appendChild(column);
        return group;
    }

    function refreshRouteValidationPaths(card) {
        var routeField = card.querySelector('[data-field="route"]');
        var pathRoot = 'routes.' + (routeField && routeField.value ? routeField.value.trim() : 'route');
        if (routeField) {
            routeField.setAttribute('data-validation-path', pathRoot + '.route');
        }
        card.setAttribute('data-validation-path', pathRoot);
        Array.prototype.forEach.call(card.querySelectorAll('.appnuno-tab'), function (tab, index) {
            var tabPath = pathRoot + '[' + index + ']';
            tab.setAttribute('data-validation-path', tabPath);
            Array.prototype.forEach.call(tab.querySelectorAll('[data-field]'), function (field) {
                field.setAttribute('data-validation-path', tabPath + '.' + field.getAttribute('data-field'));
            });
            var labels = tab.querySelector('[data-validation-path$=".labels"]');
            if (labels) {
                labels.setAttribute('data-validation-path', tabPath + '.labels');
            }
            Array.prototype.forEach.call(tab.querySelectorAll('[data-language-label]'), function (field) {
                field.setAttribute('data-validation-path', tabPath + '.labels.' + field.getAttribute('data-language-label'));
            });
            Array.prototype.forEach.call(tab.querySelectorAll('[data-language-url]'), function (field) {
                field.setAttribute('data-validation-path', tabPath + '.urls.' + field.getAttribute('data-language-url'));
            });
        });
    }

    function createRoute(route, tabs) {
        var card = document.createElement('div');
        card.className = 'panel panel-info appnuno-route';
        var heading = document.createElement('div');
        heading.className = 'panel-heading';
        heading.textContent = 'Route tab set';
        var body = document.createElement('div');
        body.className = 'panel-body';
        body.appendChild(createRouteField(route));
        var tabContainer = document.createElement('div');
        tabContainer.className = 'route-tabs';
        (tabs || []).forEach(function (tab) { addTab(tabContainer, tab, 'routes.' + route); });
        var routeField = body.querySelector('[data-field="route"]');
        var routeSelect = body.querySelector('select');
        routeField.addEventListener('input', function () { refreshRouteValidationPaths(card); });
        routeSelect.addEventListener('change', function () { refreshRouteValidationPaths(card); });
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-default';
        add.textContent = 'Add tab to this route';
        add.addEventListener('click', function () {
            var routeField = card.querySelector('[data-field="route"]');
            addTab(tabContainer, {}, 'routes.' + (routeField && routeField.value ? routeField.value : 'route'));
        });
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-danger pull-right';
        remove.textContent = 'Remove route set';
        remove.addEventListener('click', function () {
            routesContainer.removeChild(card);
            refreshCartOptions();
        });
        body.appendChild(tabContainer);
        body.appendChild(add);
        body.appendChild(remove);
        card.appendChild(heading);
        card.appendChild(body);
        card.addEventListener('input', refreshCartOptions);
        refreshRouteValidationPaths(card);
        routesContainer.appendChild(card);
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
            return tab;
        });
    }

    function refreshCartOptions() {
        var current = cartSelect.value || configuration.cartTabId || 'cart';
        var ids = {};
        readTabs(defaultContainer).concat(Array.prototype.reduce.call(routesContainer.querySelectorAll('.route-tabs'), function (all, container) {
            return all.concat(readTabs(container));
        }, [])).forEach(function (tab) {
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
            fallback.textContent = current + ' (not currently in a tab set)';
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

    function setChecked(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.checked = Boolean(value);
        }
    }

    (configuration.defaultTabs || []).forEach(function (tab) { addTab(defaultContainer, tab, 'defaultTabs'); });
    Object.keys(configuration.routes || {}).forEach(function (route) { createRoute(route, configuration.routes[route]); });
    if (!defaultContainer.children.length) {
        addTab(defaultContainer, {}, 'defaultTabs');
    }
    document.querySelectorAll('[data-add-tab]').forEach(function (button) {
        button.addEventListener('click', function () { addTab(document.getElementById(button.getAttribute('data-add-tab')), {}, 'defaultTabs'); });
    });
    document.getElementById('add-route').addEventListener('click', function () { createRoute('', []); });

    setChecked('back-enabled', !configuration.back || configuration.back.enabled !== false);
    setValue('back-placement', configuration.back && configuration.back.placement || 'separate');
    var glass = configuration.glass || {};
    setChecked('glass-enabled', glass.enabled !== false);
    setValue('glass-color-scheme', glass.colorScheme || 'auto');
    setValue('glass-style', glass.style || 'regular');
    setChecked('glass-animate', glass.animate !== false);
    setValue('glass-duration', typeof glass.animationDuration === 'number' ? glass.animationDuration : 0.3);
    setChecked('glass-interactive', glass.isInteractive !== false);
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
        if (!matched && path.indexOf('routes.') === 0) {
            var routeCard = routesContainer.querySelector('.appnuno-route');
            matched = routeCard ? routeCard.querySelector('[data-field="route"]') : null;
        }
        if (!matched && path === 'routes') {
            matched = routesContainer;
        }
        if (!matched && path === 'defaultTabs') {
            matched = defaultContainer;
        }
        if (!matched && path === 'module_appnuno_config') {
            matched = document.getElementById('input-config-json');
        }
        if (matched) {
            var group = matched;
            while (group && !(' ' + group.className + ' ').match(/ form-group | appnuno-route /) && group.id !== 'default-tabs' && group.id !== 'route-sets') {
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

    document.getElementById('form-appnuno').addEventListener('submit', function (event) {
        var clientError = document.getElementById('client-validation-error');
        clientError.style.display = 'none';
        var routes = Object.create(null);
        var routeError = '';
        Array.prototype.forEach.call(routesContainer.querySelectorAll('.appnuno-route'), function (card) {
            var routeField = card.querySelector('[data-field="route"]');
            var route = routeField ? routeField.value.trim() : '';
            if (!route) {
                routeError = 'Choose a route for each route-specific tab set.';
                if (routeField) {
                    routeField.parentNode.parentNode.className += ' has-error';
                    routeField.focus();
                }
                return;
            }
            if (Object.prototype.hasOwnProperty.call(routes, route)) {
                routeError = 'Each OpenCart route can have only one route-specific tab set.';
                if (routeField) {
                    routeField.parentNode.parentNode.className += ' has-error';
                    routeField.focus();
                }
                return;
            }
            routes[route] = readTabs(card.querySelector('.route-tabs'));
        });
        if (routeError) {
            event.preventDefault();
            clientError.textContent = routeError;
            clientError.style.display = '';
            clientError.scrollIntoView({ block: 'center' });
            return;
        }
        var result = {
            cartTabId: cartSelect.value || 'cart',
            defaultTabs: readTabs(defaultContainer),
            routes: routes,
            back: {
                enabled: document.getElementById('back-enabled').checked,
                placement: document.getElementById('back-placement').value
            },
            glass: {
                enabled: document.getElementById('glass-enabled').checked,
                colorScheme: document.getElementById('glass-color-scheme').value,
                style: document.getElementById('glass-style').value,
                animate: document.getElementById('glass-animate').checked,
                animationDuration: Number(document.getElementById('glass-duration').value),
                isInteractive: document.getElementById('glass-interactive').checked
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
</script>
<?php echo $footer; ?>
