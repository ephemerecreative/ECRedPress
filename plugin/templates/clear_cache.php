<h1><?php echo __("Cache Cleared", "ecrp"); ?></h1>

<h4><?php echo __("The following urls were cleared", "ecrp"); ?></h4>

<ul>
    <?php foreach ($to_delete as $url => $value): ?>
        <li><a target="_blank" href="<?php echo $url ?>"><?php echo $url ?></a></li>
    <?php endforeach; ?>
</ul>