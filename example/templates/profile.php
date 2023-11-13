<?php $this->layout('layout', ['title' => 'User Profile']) ?>

<h1>User Profile</h1>
<p>Hello, <?=$this->e($name)?>!</p>

<?php $this->insert('sidebar') ?>

<?php $this->push('scripts') ?>
    <script>
        // Some JavaScript
    </script>
<?php $this->end() ?>

<?php $this->push('footer') ?>
    <br>Profile Footer
<?php $this->end() ?>