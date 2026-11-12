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
  <?php echo $appnuno_settings; ?>
</div>
<?php echo $footer; ?>
