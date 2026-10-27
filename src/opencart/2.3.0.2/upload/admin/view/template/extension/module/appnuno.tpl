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
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-config"><?php echo $entry_config; ?></label>
            <div class="col-sm-10"><textarea name="module_appnuno_config" rows="24" id="input-config" class="form-control" spellcheck="false"><?php echo htmlspecialchars($module_appnuno_config, ENT_QUOTES, 'UTF-8'); ?></textarea><p class="help-block"><?php echo $help_config; ?></p></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>
