<h1><?php echo __("Cache Cleared", "ecrp"); ?></h1>

<h4><?php echo __("The following urls were cleared", "ecrp"); ?></h4>

<ul>
    <?php foreach ($permalinks as $permalink): ?>
        <li><?php echo $permalink ?></li>
    <?php endforeach; ?>
</ul>