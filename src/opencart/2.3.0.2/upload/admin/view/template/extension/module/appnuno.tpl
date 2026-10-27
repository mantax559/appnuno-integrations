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
    <?php if ($error_warning) { ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php } ?>
    <?php if ($error_config) { ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_config; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3></div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-appnuno" class="form-horizontal">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10"><select name="module_appnuno_status" id="input-status" class="form-control"><option value="1"<?php echo in_array($module_appnuno_status, array('1', 1), true) ? ' selected="selected"' : ''; ?>><?php echo $text_enabled; ?></option><option value="0"<?php echo in_array($module_appnuno_status, array('0', 0), true) ? ' selected="selected"' : ''; ?>><?php echo $text_disabled; ?></option></select></div>
          </div>
          <input type="hidden" name="module_appnuno_config" id="input-config-json" value="<?php echo htmlspecialchars($module_appnuno_config, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?php echo $text_default_tabs; ?></h3></div>
            <div class="panel-body">
              <p class="help-block">Shown on routes without a matching route-specific tab set. Tab URLs must be paths on this store, beginning with /.</p>
              <div id="default-tabs"></div>
              <button type="button" class="btn btn-default" data-add-tab="default-tabs"><i class="fa fa-plus"></i> Add default tab</button>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Route-specific tab sets</h3></div>
            <div class="panel-body">
              <p class="help-block">Add an exact OpenCart route, for example checkout/cart or product/product. Its complete tab set replaces the defaults on that route.</p>
              <div id="route-sets"></div>
              <button type="button" class="btn btn-default" id="add-route"><i class="fa fa-plus"></i> Add route tab set</button>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Cart badge</h3></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="cart-tab-id">Cart tab</label>
                <div class="col-sm-10"><select id="cart-tab-id" class="form-control"></select><p class="help-block">The selected tab receives the confirmed quantity of products in the cart.</p></div>
              </div>
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
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-duration">Animation duration (seconds)</label><div class="col-sm-10"><input type="number" min="0" max="10" step="0.1" id="glass-duration" class="form-control"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-interactive">Interactive glass</label><div class="col-sm-10"><input type="checkbox" id="glass-interactive"></div></div>
              <div class="form-group"><label class="col-sm-2 control-label" for="glass-tint">Tint color</label><div class="col-sm-10"><input type="text" id="glass-tint" class="form-control" placeholder="rgba(0, 0, 0, 0.02)"></div></div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Navigation colors</h3></div>
            <div class="panel-body">
              <div class="row">
                <div class="col-sm-6">
                  <h4>Light mode</h4>
                  <div class="form-group"><label class="col-sm-4 control-label" for="light-background">Background</label><div class="col-sm-8"><input type="text" id="light-background" class="form-control" placeholder="#FFFFFF"></div></div>
                  <div class="form-group"><label class="col-sm-4 control-label" for="light-content">Content</label><div class="col-sm-8"><input type="text" id="light-content" class="form-control" placeholder="#2C3136"></div></div>
                </div>
                <div class="col-sm-6">
                  <h4>Dark mode</h4>
                  <div class="form-group"><label class="col-sm-4 control-label" for="dark-background">Background</label><div class="col-sm-8"><input type="text" id="dark-background" class="form-control" placeholder="#2C3136"></div></div>
                  <div class="form-group"><label class="col-sm-4 control-label" for="dark-content">Content</label><div class="col-sm-8"><input type="text" id="dark-content" class="form-control" placeholder="#FFFFFF"></div></div>
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
    var defaultContainer = document.getElementById('default-tabs');
    var routesContainer = document.getElementById('route-sets');
    var cartSelect = document.getElementById('cart-tab-id');

    function createField(label, value, key, type) {
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
        column.appendChild(input);
        group.appendChild(title);
        group.appendChild(column);
        return group;
    }

    function createTab(tab) {
        var card = document.createElement('div');
        card.className = 'well appnuno-tab';
        card.appendChild(createField('Tab ID', tab.id, 'id'));
        card.appendChild(createField('Label', tab.label, 'label'));
        card.appendChild(createField('URL', tab.url, 'url'));
        card.appendChild(createField('Icon', tab.icon, 'icon'));
        card.appendChild(createField('Active icon', tab.activeIcon, 'activeIcon'));
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

    function addTab(container, tab) {
        container.appendChild(createTab(tab || {}));
        refreshCartOptions();
    }

    function createRoute(route, tabs) {
        var card = document.createElement('div');
        card.className = 'panel panel-info appnuno-route';
        var heading = document.createElement('div');
        heading.className = 'panel-heading';
        heading.textContent = 'Route tab set';
        var body = document.createElement('div');
        body.className = 'panel-body';
        body.appendChild(createField('OpenCart route', route, 'route'));
        var tabContainer = document.createElement('div');
        tabContainer.className = 'route-tabs';
        (tabs || []).forEach(function (tab) { addTab(tabContainer, tab); });
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-default';
        add.textContent = 'Add tab to this route';
        add.addEventListener('click', function () { addTab(tabContainer, {}); });
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
            element.value = value || '';
        }
    }

    function setChecked(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.checked = Boolean(value);
        }
    }

    (configuration.defaultTabs || []).forEach(function (tab) { addTab(defaultContainer, tab); });
    Object.keys(configuration.routes || {}).forEach(function (route) { createRoute(route, configuration.routes[route]); });
    if (!defaultContainer.children.length) {
        addTab(defaultContainer, {});
    }
    document.querySelectorAll('[data-add-tab]').forEach(function (button) {
        button.addEventListener('click', function () { addTab(document.getElementById(button.getAttribute('data-add-tab')), {}); });
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
    setValue('glass-tint', glass.tintColor || '');
    setValue('light-background', configuration.colors && configuration.colors.light && configuration.colors.light.backgroundColor);
    setValue('light-content', configuration.colors && configuration.colors.light && configuration.colors.light.contentColor);
    setValue('dark-background', configuration.colors && configuration.colors.dark && configuration.colors.dark.backgroundColor);
    setValue('dark-content', configuration.colors && configuration.colors.dark && configuration.colors.dark.contentColor);
    refreshCartOptions();
    cartSelect.value = configuration.cartTabId || cartSelect.value;

    document.getElementById('form-appnuno').addEventListener('submit', function () {
        var routes = {};
        Array.prototype.forEach.call(routesContainer.querySelectorAll('.appnuno-route'), function (card) {
            var routeField = card.querySelector('[data-field="route"]');
            var route = routeField ? routeField.value.trim() : '';
            if (route) {
                routes[route] = readTabs(card.querySelector('.route-tabs'));
            }
        });
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
        var tint = document.getElementById('glass-tint').value.trim();
        if (tint) {
            result.glass.tintColor = tint;
        }
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
