<h1><?php echo __("ECRedPress", "ecrp") ?></h1>

<h2><?php echo __("Clear Cache", "ecrp") ?></h2>

<a href="<?= admin_url('admin.php?page=ecrp-clear-cache') ?>" class="button button-primary">
    <?= __("Clear Cache", "ecrp") ?>
</a>

<h2><?php echo __("ECRP Cache Config", "ecrp") ?></h2>

<table>
    <thead>
    <tr>
        <td><b><?php echo __("Setting", "ecrp") ?></b></td>
        <td><b><?php echo __("Value", "ecrp") ?></b></td>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($ecrp->get_config() as $item => $value): ?>
    <?php
        if(in_array($item, ['REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD']))
            continue;
        ?>
        <tr>
            <td><b><?php echo $item ?></b></td>
            <td><?php print_r($value) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2><?php echo __("ECRP Cached Pages", "ecrp") ?></h2>

<?php if (sizeof($cached_urls) === 0): ?>
    <p><?= __("Nothing cached yet!", "ecrp") ?></p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <td><b><?php echo __("URL", "ecrp") ?></b></td>
            <td><b><?php echo __("Cache Data", "ecrp") ?></b></td>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cached_urls as $url => $data): ?>
            <tr>
                <td><a href="<?php echo $url ?>"><?php echo $url ?></a></td>
                <td>
                    <?php if (is_array($data)): ?>
                        <ul>
                            <?php foreach ($data as $key => $value): ?>
                                <li>
                                    <b><?= $key ?></b>: <?= $value ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>