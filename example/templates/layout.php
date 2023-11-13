<html>
<head>
    <title><?=$this->e($title)?> | <?=$this->e($company)?></title>
</head>
<body>

<?=$this->section('content')?>

<?=$this->section('scripts')?>


<?php if ($this->startSection('footer')) { ?>
    footer
<?php } $this->stopSection(); ?>

</body>
</html>